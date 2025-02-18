<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Generator;

use Kauffinger\Codemap\Dto\CodemapFileDto;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class CodemapGenerator
{
    private ?PhpVersion $phpParserVersion = null;

    /**
     * Optionally set the PHP version used by PhpParser.
     */
    public function setPhpParserVersion(?PhpVersion $version): void
    {
        $this->phpParserVersion = $version;
    }

    /**
     * Scans the provided path (directory or single file).
     * If it's a directory, scans recursively for PHP files.
     * If it's a single file, parses just that file.
     *
     * @return array<string, CodemapFileDto> A map of fileName => codemap DTO
     */
    public function generate(string $pathToScan): array
    {
        if (! file_exists($pathToScan)) {
            return [];
        }

        // If path is a file, parse that single file
        if (is_file($pathToScan)) {
            return $this->processSingleFile($pathToScan);
        }

        // Otherwise, if path is a directory, scan recursively
        if (! is_dir($pathToScan)) {
            return [];
        }

        $scanResults = [];
        $directoryIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pathToScan));

        /** @var SplFileInfo $file */
        foreach ($directoryIterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $parsedFileResults = $this->processSingleFile($file->getRealPath());
            foreach ($parsedFileResults as $fileName => $codemapFileDto) {
                $scanResults[$fileName] = $codemapFileDto;
            }
        }

        return $scanResults;
    }

    /**
     * Processes a single PHP file, returning codemap data in a DTO.
     *
     * @return array<string, CodemapFileDto> The codemap results for this file
     */
    private function processSingleFile(string $filePath): array
    {
        if (! file_exists($filePath) || pathinfo($filePath, PATHINFO_EXTENSION) !== 'php') {
            return [];
        }

        $parser = (new ParserFactory)->createForVersion(
            $this->phpParserVersion ?? PhpVersion::getHostVersion()
        );

        $fileContents = file_get_contents($filePath);
        if ($fileContents === false) {
            return [];
        }

        try {
            $abstractSyntaxTree = $parser->parse($fileContents);
        } catch (Error $parseError) {
            echo 'Parse Error: '.$parseError->getMessage().PHP_EOL;

            return [];
        }

        $fileName = basename($filePath);

        $classCollectionVisitor = new ClassCollectionVisitor;

        $nodeTraverser = new NodeTraverser;
        $nodeTraverser->addVisitor(new NameResolver);
        $nodeTraverser->addVisitor($classCollectionVisitor);
        $nodeTraverser->traverse((array) $abstractSyntaxTree);

        // Now we can directly use the typed DTOs collected by ClassCollectionVisitor:
        $codemapFileDto = new CodemapFileDto($classCollectionVisitor->collectedClasses);

        return [$fileName => $codemapFileDto];
    }
}
