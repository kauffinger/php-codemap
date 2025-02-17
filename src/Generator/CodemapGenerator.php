<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Generator;

use Kauffinger\Codemap\Dto\CodemapClassDto;
use Kauffinger\Codemap\Dto\CodemapFileDto;
use Kauffinger\Codemap\Dto\CodemapMethodDto;
use Kauffinger\Codemap\Dto\CodemapPropertyDto;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
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
    public function generate(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        // If path is a file, just parse that single file.
        if (is_file($path)) {
            return $this->processFile($path);
        }

        // Otherwise, path is a directory; scan recursively.
        if (! is_dir($path)) {
            return [];
        }

        $results = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $parsed = $this->processFile($file->getRealPath());
            foreach ($parsed as $fileName => $dto) {
                $results[$fileName] = $dto;
            }
        }

        return $results;
    }

    /**
     * Processes a single PHP file, returning codemap data in a DTO.
     *
     * @return array<string, CodemapFileDto> The codemap results for this file
     */
    private function processFile(string $filePath): array
    {
        if (! file_exists($filePath) || pathinfo($filePath, PATHINFO_EXTENSION) !== 'php') {
            return [];
        }

        $parser = (new ParserFactory)->createForVersion(
            $this->phpParserVersion ?? PhpVersion::getHostVersion()
        );

        $code = file_get_contents($filePath);
        if ($code === false) {
            return [];
        }

        try {
            $ast = $parser->parse($code);
        } catch (Error $e) {
            echo 'Parse Error: '.$e->getMessage().PHP_EOL;

            return [];
        }

        $fileName = basename($filePath);

        $visitor = new class extends NodeVisitorAbstract
        {
            public array $classes = [];

            private ?string $currentClassName = null;

            public function enterNode(Node $node)
            {
                if ($node instanceof Class_) {
                    $this->currentClassName = $node->namespacedName
                        ? $node->namespacedName->toString()
                        : (string) $node->name;

                    $this->classes[$this->currentClassName] = [
                        'methods' => [],
                        'properties' => [],
                    ];
                } elseif ($node instanceof ClassMethod && $this->currentClassName !== null) {
                    $visibility = $node->isPublic()
                        ? 'public'
                        : ($node->isProtected() ? 'protected' : 'private');

                    $returnType = $node->getReturnType();
                    if ($returnType instanceof Node\Identifier) {
                        $returnType = $returnType->name;
                    } elseif ($returnType instanceof Node\Name) {
                        $returnType = $returnType->toString();
                    } else {
                        $returnType = 'mixed';
                    }

                    $this->classes[$this->currentClassName]['methods'][] = [
                        'visibility' => $visibility,
                        'name' => $node->name->toString(),
                        'returnType' => $returnType,
                    ];
                } elseif ($node instanceof Property && $this->currentClassName !== null) {
                    $visibility = $node->isPublic()
                        ? 'public'
                        : ($node->isProtected() ? 'protected' : 'private');

                    $propertyType = $node->type;
                    if ($propertyType instanceof Node\Identifier) {
                        $propertyType = $propertyType->name;
                    } elseif ($propertyType instanceof Node\Name) {
                        $propertyType = $propertyType->toString();
                    } else {
                        $propertyType = 'mixed';
                    }

                    foreach ($node->props as $prop) {
                        $this->classes[$this->currentClassName]['properties'][] = [
                            'visibility' => $visibility,
                            'name' => $prop->name->toString(),
                            'type' => $propertyType,
                        ];
                    }
                }

                return null;
            }
        };

        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver);
        $traverser->addVisitor($visitor);
        $traverser->traverse((array) $ast);

        // Convert arrays to typed DTOs
        $classes = [];
        foreach ($visitor->classes as $className => $classData) {
            $methods = [];
            foreach ($classData['methods'] as $methodData) {
                $methods[] = new CodemapMethodDto(
                    $methodData['visibility'],
                    $methodData['name'],
                    $methodData['returnType']
                );
            }

            $properties = [];
            foreach ($classData['properties'] as $propData) {
                $properties[] = new CodemapPropertyDto(
                    $propData['visibility'],
                    $propData['name'],
                    $propData['type']
                );
            }

            $classes[$className] = new CodemapClassDto($methods, $properties);
        }

        $dto = new CodemapFileDto($classes);

        return [$fileName => $dto];
    }
}
