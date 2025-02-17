<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Console;

use Kauffinger\Codemap\Config\CodemapConfig;
use Kauffinger\Codemap\Enum\PhpVersion;
use Kauffinger\Codemap\Formatter\TextCodemapFormatter;
use Kauffinger\Codemap\Generator\CodemapGenerator;

final class CodemapCommand
{
    /**
     * Invokes the codemap command.
     *
     * @param  string[]  $commandArguments  The command-line arguments (if any).
     * @return int Exit code.
     */
    public function __invoke(array $commandArguments): int
    {
        // The first element is usually the script name, so remove it
        array_shift($commandArguments);

        // Attempt to load codemap.php from project root:
        $codemapConfigFilePath = __DIR__.'/../../codemap.php';
        $configuredScanPaths = [];
        $configuredPhpVersion = null;

        // Check if codemap.php exists, otherwise attempt to parse composer.json
        if (! file_exists($codemapConfigFilePath)) {
            // Attempt to parse composer.json
            $composerJsonPath = __DIR__.'/../../composer.json';
            $composerContents = file_get_contents($composerJsonPath) ?: '';
            $composerData = json_decode($composerContents, true);
            $composerPhpVersionString = $composerData['require']['php'] ?? '^8.4.0';

            // Extract the minor version from something like ^8.3.0
            $parsedComposerVersion = '8.4';
            if (preg_match('/(\d+\.\d+)/', (string) $composerPhpVersionString, $matches)) {
                $parsedComposerVersion = $matches[1]; // e.g. "8.3"
            }

            // Map parsedComposerVersion to our enum
            $mappedPhpVersion = PhpVersion::PHP_8_4;
            foreach (PhpVersion::cases() as $phpVersionCase) {
                if ($phpVersionCase->value === $parsedComposerVersion) {
                    $mappedPhpVersion = $phpVersionCase;
                    break;
                }
            }

            // Create a default codemap.php
            $generatedDefaultConfig = $this->generateDefaultConfig($mappedPhpVersion);

            file_put_contents($codemapConfigFilePath, $generatedDefaultConfig);
            echo "Created default codemap config at: {$codemapConfigFilePath}".PHP_EOL;
        }

        if (file_exists($codemapConfigFilePath)) {
            $codemapConfiguration = require $codemapConfigFilePath;
            if ($codemapConfiguration instanceof CodemapConfig) {
                $configuredScanPaths = $codemapConfiguration->getScanPaths();
                $configuredPhpVersion = $codemapConfiguration->getConfiguredPhpVersion();
            }
        }

        // If no CLI paths are provided, default to config paths or fallback to src folder
        if ($commandArguments === []) {
            $commandArguments = $configuredScanPaths === [] ? [__DIR__.'/../../src'] : $configuredScanPaths;
        }

        $targetScanPaths = $commandArguments;
        $outputFilePath = __DIR__.'/../../codemap.txt';

        $codemapGenerator = new CodemapGenerator;

        // If a configured PHP version is specified, map it to PhpParser's version if possible.
        if ($configuredPhpVersion instanceof PhpVersion) {
            $codemapGenerator->setPhpParserVersion(\PhpParser\PhpVersion::fromString($configuredPhpVersion->value));
        }

        $aggregatedCodemapResults = [];

        foreach ($targetScanPaths as $scanPath) {
            if (! file_exists($scanPath)) {
                echo "Warning: Path '$scanPath' does not exist.".PHP_EOL;

                continue;
            }

            $fileResults = $codemapGenerator->generate($scanPath);

            foreach ($fileResults as $fileName => $codemapDto) {
                $aggregatedCodemapResults[$fileName] = $codemapDto;
            }
        }

        $formatter = new TextCodemapFormatter;
        $formattedCodemapOutput = $formatter->format($aggregatedCodemapResults);

        file_put_contents($outputFilePath, $formattedCodemapOutput);
        echo "Codemap generated at: {$outputFilePath}".PHP_EOL;

        return 0;
    }

    private function generateDefaultConfig(PhpVersion $mappedPhpVersion): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

use Kauffinger\\Codemap\\Config\\CodemapConfig;
use Kauffinger\\Codemap\\Enum\\PhpVersion;

return CodemapConfig::configure()
    ->withScanPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap',
        __DIR__.'/config',
        __DIR__.'/public',
        __DIR__.'/resources',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withPhpVersion(PhpVersion::{$mappedPhpVersion->name});
PHP;
    }
}
