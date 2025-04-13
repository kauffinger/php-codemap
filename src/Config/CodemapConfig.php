<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Config;

use Kauffinger\Codemap\Enum\PhpVersion;

final class CodemapConfig
{
    /**
     * @var string[]
     */
    private array $configuredScanPaths = [];

    private ?PhpVersion $configuredPhpVersion = null;

    /**
     * @var string[] Default visibility levels for properties
     */
    private array $propertyVisibilityLevels = ['public'];

    /**
     * @var string[] Default visibility levels for methods (all)
     */
    private array $methodVisibilityLevels = ['public', 'protected', 'private'];

    /**
     * @var string[] Paths or patterns to exclude from scanning
     */
    private array $excludePaths = [];

    private function __construct()
    {
        // Builder pattern
    }

    /**
     * Entry point to create a fresh CodemapConfig instance.
     */
    public static function configure(): self
    {
        return new self;
    }

    /**
     * Sets the paths to scan.
     *
     * @param  string[]  $scanPaths
     */
    public function withScanPaths(array $scanPaths): self
    {
        $this->configuredScanPaths = $scanPaths;

        return $this;
    }

    /**
     * Sets the PHP version to use for parsing.
     */
    public function withPhpVersion(PhpVersion $phpVersion): self
    {
        $this->configuredPhpVersion = $phpVersion;

        return $this;
    }

    /**
     * Returns the configured scan paths.
     *
     * @return string[]
     */
    public function getScanPaths(): array
    {
        return $this->configuredScanPaths;
    }

    /**
     * Returns the configured PHP version or null if none was set.
     */
    public function getConfiguredPhpVersion(): ?PhpVersion
    {
        return $this->configuredPhpVersion;
    }

    /**
     * Sets the visibility levels for properties to include in the output.
     * Default: ['public']
     *
     * @param  string[]  $levels  e.g., ['public', 'protected']
     */
    public function withPropertyVisibility(array $levels): self
    {
        // Basic validation could be added (e.g., ensure only 'public', 'protected', 'private' are used)
        $this->propertyVisibilityLevels = $levels;

        return $this;
    }

    /**
     * Sets the visibility levels for methods to include in the output.
     * Default: ['public', 'protected', 'private']
     *
     * @param  string[]  $levels  e.g., ['public']
     */
    public function withMethodVisibility(array $levels): self
    {
        // Basic validation could be added
        $this->methodVisibilityLevels = $levels;

        return $this;
    }

    /**
     * Sets the paths or patterns to exclude from the scan.
     *
     * @param  string[]  $paths  Array of paths or glob patterns to exclude
     */
    public function withExcludePaths(array $paths): self
    {
        $this->excludePaths = $paths;

        return $this;
    }

    /**
     * Returns the configured property visibility levels.
     *
     * @return string[]
     */
    public function getPropertyVisibilityLevels(): array
    {
        return $this->propertyVisibilityLevels;
    }

    /**
     * Returns the configured method visibility levels.
     *
     * @return string[]
     */
    public function getMethodVisibilityLevels(): array
    {
        return $this->methodVisibilityLevels;
    }

    /**
     * Returns the configured exclusion paths/patterns.
     *
     * @return string[]
     */
    public function getExcludePaths(): array
    {
        return $this->excludePaths;
    }
}
