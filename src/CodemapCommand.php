<?php

declare(strict_types=1);

namespace Kauffinger\Codemap;

final class CodemapCommand
{
    /**
     * Invokes the codemap command.
     *
     * @param  string[]  $args  The command-line arguments (if any).
     * @return int Exit code.
     */
    public function __invoke(array $args): int
    {
        // Shift off the script name
        array_shift($args);

        // Attempt to load codemap.php from project root:
        $configFile = __DIR__.'/../codemap.php';
        $configPaths = [];
        $configPhpVersion = null;

        if (! file_exists($configFile)) {
            // Attempt to parse composer.json
            $composerJsonPath = __DIR__.'/../composer.json';
            $composerJson = json_decode(file_get_contents($composerJsonPath) ?: '', true);
            /* @phpstan-ignore-next-line */
            $versionString = $composerJson['require']['php'] ?? '^8.4.0';

            // Extract the minor version from something like ^8.3.0
            $parsedVersion = '8.4';
            /* @phpstan-ignore-next-line */
            if (preg_match('/(\d+\.\d+)/', (string) $versionString, $matches)) {
                $parsedVersion = $matches[1]; // e.g. "8.3"
            }

            // Map parsedVersion to our enum
            $enumVersion = Enum\PhpVersion::PHP_8_4;
            foreach (Enum\PhpVersion::cases() as $case) {
                if ($case->value === $parsedVersion) {
                    $enumVersion = $case;
                    break;
                }
            }

            // Create a default codemap.php
            $defaultConfig = <<<PHP
<?php

declare(strict_types=1);

use Kauffinger\\Codemap\\CodemapConfig;
use Kauffinger\\Codemap\\Enum\\PhpVersion;

return CodemapConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap',
        __DIR__.'/config',
        __DIR__.'/public',
        __DIR__.'/resources',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withPhpVersion(PhpVersion::{$enumVersion->name});
PHP;

            file_put_contents($configFile, $defaultConfig);
            echo "Created default codemap config at: {$configFile}".PHP_EOL;
        }

        if (file_exists($configFile)) {
            $config = require $configFile;
            if ($config instanceof CodemapConfig) {
                $configPaths = $config->getPaths();
                $configPhpVersion = $config->getPhpVersion();
            }
        }

        // If no CLI paths are provided, default to config paths or fallback to src folder
        if ($args === []) {
            $args = $configPaths === [] ? [__DIR__.'/../src'] : $configPaths;
        }

        $pathsToScan = $args;
        $outputFile = __DIR__.'/../codemap.txt';

        $generator = new CodemapGenerator;

        // If configPhpVersion is specified, map it to PhpParser's version if possible.
        if ($configPhpVersion instanceof Enum\PhpVersion) {
            $generator->setPhpParserVersion(\PhpParser\PhpVersion::fromString($configPhpVersion->value));
        }
        /** @var array<string, Dto\CodemapFileDto> $allResults */
        $allResults = [];

        foreach ($pathsToScan as $path) {
            if (! file_exists($path)) {
                echo "Warning: Path '$path' does not exist.".PHP_EOL;

                continue;
            }

            // Each $results is array<string, CodemapFileDto>
            $results = $generator->generate($path);

            // Merge them by filename
            foreach ($results as $fileName => $dto) {
                $allResults[$fileName] = $dto;
            }
        }

        $formatter = new TextCodemapFormatter;
        // Now $allResults is array<string, CodemapFileDto>
        $output = $formatter->format($allResults);

        file_put_contents($outputFile, $output);
        echo "Codemap generated at: {$outputFile}".PHP_EOL;

        return 0;
    }
}
