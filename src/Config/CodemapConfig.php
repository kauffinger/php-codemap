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
}
