<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Console;

use Kauffinger\Codemap\Config\CodemapConfig;
use Kauffinger\Codemap\Enum\PhpVersion;
use Kauffinger\Codemap\Formatter\TextCodemapFormatter;
use Kauffinger\Codemap\Generator\CodemapGenerator;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Generates a codemap of PHP code, scanning specified paths and writing the output to a file or stdout.
 */
final class CodemapCommand extends Command
{
    /**
     * Configures the command with arguments and options.
     */
    #[Override]
    protected function configure(): void
    {
        $this
            ->setName('codemap')
            ->setDescription('Generate a codemap of PHP code')
            ->addArgument('paths', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Paths to scan', [])
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path (use "-" for stdout)', 'codemap.txt')
            ->addOption('php-version', null, InputOption::VALUE_REQUIRED, 'PHP version to use for parsing (e.g., "8.3")');
    }

    /**
     * Executes the codemap generation process.
     *
     * @param  InputInterface  $input  The command input
     * @param  OutputInterface  $output  The command output
     * @return int Exit code (0 for success, 1 for failure)
     */
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $codemapConfigFilePath = __DIR__.'/../../codemap.php';
        $configuredScanPaths = [];
        $configuredPhpVersion = null;

        if (file_exists($codemapConfigFilePath)) {
            $codemapConfiguration = require $codemapConfigFilePath;
            if ($codemapConfiguration instanceof CodemapConfig) {
                $configuredScanPaths = $codemapConfiguration->getScanPaths();
                $configuredPhpVersion = $codemapConfiguration->getConfiguredPhpVersion();
            }
        } else {
            // Attempt to parse composer.json for PHP version
            $composerJsonPath = __DIR__.'/../../composer.json';
            $composerContents = file_get_contents($composerJsonPath) ?: '';
            $composerData = json_decode($composerContents, true);
            $composerPhpVersionString = $composerData['require']['php'] ?? '^8.4.0';

            // Extract minor version (e.g., "8.4")
            $parsedComposerVersion = '8.4';
            if (preg_match('/(\d+\.\d+)/', (string) $composerPhpVersionString, $matches)) {
                $parsedComposerVersion = $matches[1];
            }

            // Map to PhpVersion enum
            $mappedPhpVersion = PhpVersion::PHP_8_4;
            foreach (PhpVersion::cases() as $phpVersionCase) {
                if ($phpVersionCase->value === $parsedComposerVersion) {
                    $mappedPhpVersion = $phpVersionCase;
                    break;
                }
            }

            // Generate and write default config
            $generatedDefaultConfig = $this->generateDefaultConfig($mappedPhpVersion);
            file_put_contents($codemapConfigFilePath, $generatedDefaultConfig);
            $output->writeln('<info>Created default codemap config at: '.$codemapConfigFilePath.'</info>');
            $configuredScanPaths = [__DIR__.'/../../src'];
            $configuredPhpVersion = $mappedPhpVersion;
        }

        // Determine scan paths: CLI arguments or configured defaults
        $paths = $input->getArgument('paths');
        if (empty($paths)) {
            $paths = $configuredScanPaths;
        }

        // Determine PHP version: CLI option or configured default
        $phpVersionString = $input->getOption('php-version');
        if ($phpVersionString) {
            $phpVersion = PhpVersion::tryFrom($phpVersionString);
            if (! $phpVersion instanceof PhpVersion) {
                $output->writeln('<error>Invalid PHP version: '.$phpVersionString.'</error>');

                return Command::FAILURE;
            }
        } else {
            $phpVersion = $configuredPhpVersion;
        }

        $outputFile = $input->getOption('output');

        $codemapGenerator = new CodemapGenerator;
        if ($phpVersion instanceof PhpVersion) {
            $codemapGenerator->setPhpParserVersion(\PhpParser\PhpVersion::fromString($phpVersion->value));
        }

        $aggregatedCodemapResults = [];
        foreach ($paths as $scanPath) {
            if (! file_exists($scanPath)) {
                $output->writeln('<error>Warning: Path \''.$scanPath.'\' does not exist.</error>');

                continue;
            }
            $fileResults = $codemapGenerator->generate($scanPath);
            foreach ($fileResults as $fileName => $codemapDto) {
                $aggregatedCodemapResults[$fileName] = $codemapDto;
            }
        }

        $formatter = new TextCodemapFormatter;
        $formattedCodemapOutput = $formatter->format($aggregatedCodemapResults);

        if ($outputFile === '-') {
            $output->write($formattedCodemapOutput);
        } else {
            file_put_contents($outputFile, $formattedCodemapOutput);
            $output->writeln('<info>Codemap generated at: '.$outputFile.'</info>');
        }

        return Command::SUCCESS;
    }

    /**
     * Generates the content for a default codemap configuration file.
     *
     * @param  PhpVersion  $mappedPhpVersion  The PHP version to include in the config
     * @return string The configuration file content
     */
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
