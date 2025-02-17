<?php

declare(strict_types=1);

namespace Kauffinger\Codemap;

use Kauffinger\Codemap\Enum\PhpVersion;

final class CodemapConfig
{
    /**
     * @var string[]
     */
    private array $paths = [];

    private ?PhpVersion $phpVersion = null;

    private function __construct()
    {
        //
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
     * @param  string[]  $paths
     */
    public function withPaths(array $paths): self
    {
        $this->paths = $paths;

        return $this;
    }

    /**
     * Sets the PHP version to use for parsing.
     */
    public function withPhpVersion(PhpVersion $phpVersion): self
    {
        $this->phpVersion = $phpVersion;

        return $this;
    }

    /**
     * Returns the configured paths.
     *
     * @return string[]
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * Returns the configured PHP version or null if none.
     */
    public function getPhpVersion(): ?PhpVersion
    {
        return $this->phpVersion;
    }
}
