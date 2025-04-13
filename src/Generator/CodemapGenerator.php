<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Generator;

use Closure;
use Kauffinger\Codemap\Config\CodemapConfig;
use Kauffinger\Codemap\Dto\CodemapFileDto;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class CodemapGenerator
{
    private ?PhpVersion $phpParserVersion = null;

    /**
     * @var string[]
     */
    private array $scanPaths = [];

    private ?Closure $errorHandler = null; // Store the config

    /**
     * Initialize the generator with optional configuration.
     */
    public function __construct(private readonly ?CodemapConfig $config = null)
    {
        // Store the config instance
        if ($this->config instanceof CodemapConfig) {
            $this->phpParserVersion = $this->config->getConfiguredPhpVersion()?->toParserPhpVersion();
            $this->scanPaths = $this->config->getScanPaths();
            // Exclude paths are now accessible via $this->config->getExcludePaths()
        }
    }

    /**
     * Set the PHP version for parsing, fluently.
     *
     * @param  ?PhpVersion  $version  The PHP version to use for parsing.
     * @return $this
     */
    public function setPhpParserVersion(?PhpVersion $version): self
    {
        $this->phpParserVersion = $version;

        return $this;
    }

    /**
     * Set the paths to scan, fluently.
     *
     * @param  string[]  $paths  The paths to scan for PHP files.
     * @return $this
     */
    public function setScanPaths(array $paths): self
    {
        $this->scanPaths = $paths;

        return $this;
    }

    public function setErrorHandler(Closure $handler): self
    {
        $this->errorHandler = $handler;

        return $this;
    }

    /**
     * Generate the codemap by scanning the configured or provided path.
     *
     * @param  string|null  $pathToScan  Optional path to override configured scan paths.
     * @return array<string, CodemapFileDto> A map of filename => codemap DTO.
     *
     * @throws RuntimeException If file reading or parsing fails.
     */
    public function generate(?string $pathToScan = null): array
    {
        $paths = $pathToScan ? [$pathToScan] : $this->scanPaths;

        if ($paths === []) {
            throw new RuntimeException('No scan paths provided or configured.');
        }

        $results = [];
        foreach ($paths as $path) {
            $results = array_merge($results, $this->scanPath($path));
        }

        return $results;
    }

    /**
     * Scan a single path (file or directory) and return codemap results.
     *
     * @param  string  $pathToScan  The path to scan.
     * @return array<string, CodemapFileDto>
     *
     * @throws RuntimeException
     */
    private function scanPath(string $pathToScan): array
    {
        if (! file_exists($pathToScan)) {
            throw new RuntimeException("Path '$pathToScan' does not exist.");
        }

        if (is_file($pathToScan)) {
            try {
                $codemapFileDto = $this->processSingleFile($pathToScan);
                $relativePath = basename($pathToScan);

                return [$relativePath => $codemapFileDto];
            } catch (RuntimeException $e) {
                if ($this->errorHandler instanceof Closure) {
                    ($this->errorHandler)('Error processing file "'.$pathToScan.'": '.$e->getMessage());

                    return [];
                }

                throw $e;
            }
        }

        if (! is_dir($pathToScan)) {
            throw new RuntimeException("Path '$pathToScan' is neither a file nor a directory.");
        }

        $basePath = realpath($pathToScan);
        if ($basePath === false) {
            throw new RuntimeException("Cannot resolve base path '$pathToScan'");
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
            $filePath = $file->getRealPath();
            if ($filePath === false) {
                // Handle case where realpath fails, e.g., broken symlink
                if ($this->errorHandler instanceof Closure) {
                    ($this->errorHandler)('Warning: Could not resolve real path for "'.$file->getPathname().'". Skipping.');
                }

                continue;
            }

            // Check against exclusion paths/patterns from config
            $excludePaths = $this->config?->getExcludePaths() ?? [];
            $isExcluded = false;
            foreach ($excludePaths as $excludePattern) {
                // Simple directory prefix check (relative to project root assuming scans start there)
                // Or handle absolute paths based on config context. Let's assume relative for now.
                $normalizedExclude = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $excludePattern), DIRECTORY_SEPARATOR);
                $absoluteExcludePath = realpath($basePath.DIRECTORY_SEPARATOR.$normalizedExclude); // Check if exclude is relative to base

                if ($absoluteExcludePath !== false && str_starts_with($filePath, $absoluteExcludePath.DIRECTORY_SEPARATOR)) {
                    $isExcluded = true;
                    break;
                }
                // TODO: Add fnmatch() support for glob patterns if needed
                // if (fnmatch($excludePattern, $filePath)) { $isExcluded = true; break; }
            }

            if ($isExcluded) {
                continue; // Skip excluded file
            }

            $relativePath = str_replace($basePath.DIRECTORY_SEPARATOR, '', $filePath);
            try {
                $codemapFileDto = $this->processSingleFile($filePath);
                $scanResults[$relativePath] = $codemapFileDto;
            } catch (RuntimeException $e) {
                if ($this->errorHandler instanceof Closure) {
                    ($this->errorHandler)('Error processing file "'.$filePath.'": '.$e->getMessage());
                }
            }
        }

        return $scanResults;
    }

    /**
     * Process a single PHP file and return its codemap data.
     *
     * @param  string  $filePath  The path to the PHP file.
     *
     * @throws RuntimeException
     */
    private function processSingleFile(string $filePath): CodemapFileDto
    {
        if (! file_exists($filePath) || pathinfo($filePath, PATHINFO_EXTENSION) !== 'php') {
            throw new RuntimeException("Invalid PHP file: '$filePath'.");
        }

        $parser = (new ParserFactory)->createForVersion(
            $this->phpParserVersion ?? PhpVersion::getHostVersion()
        );

        $fileContents = file_get_contents($filePath);
        if ($fileContents === false) {
            throw new RuntimeException("Failed to read file: '$filePath'.");
        }

        try {
            $abstractSyntaxTree = $parser->parse($fileContents);
        } catch (Error $parseError) {
            throw new RuntimeException("Parse error in '$filePath': ".$parseError->getMessage(), $parseError->getCode(), $parseError);
        }

        $symbolCollectionVisitor = new SymbolCollectionVisitor;

        $nodeTraverser = new NodeTraverser;
        $nodeTraverser->addVisitor(new \PhpParser\NodeVisitor\NameResolver);
        $nodeTraverser->addVisitor($symbolCollectionVisitor);
        $nodeTraverser->traverse((array) $abstractSyntaxTree);

        return new CodemapFileDto(
            $symbolCollectionVisitor->collectedClasses,
            $symbolCollectionVisitor->collectedEnums,
            $symbolCollectionVisitor->collectedTraits // <-- Add this parameter
        );
    }
}
