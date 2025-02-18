<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Console;

use Exception;
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

final class CodemapCommand extends Command
{
    private InputInterface $input;

    private OutputInterface $output;

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

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        return $this->handle();
    }

    protected function handle(): int
    {
        $codemapConfigFilePath = getcwd().'/codemap.php';
        $configuredScanPaths = [];
        $configuredPhpVersion = null;

        if (file_exists($codemapConfigFilePath)) {
            $codemapConfiguration = require $codemapConfigFilePath;
            if ($codemapConfiguration instanceof CodemapConfig) {
                $configuredScanPaths = $codemapConfiguration->getScanPaths();
                $configuredPhpVersion = $codemapConfiguration->getConfiguredPhpVersion();
            }
        } else {
            $composerJsonPath = getcwd().'/composer.json';
            $composerContents = file_get_contents($composerJsonPath) ?: '';
            $composerData = json_decode($composerContents, true);
            /* @phpstan-ignore-next-line */
            $composerPhpVersionString = $composerData['require']['php'] ?? '^8.4.0';

            $parsedComposerVersion = '8.4';
            /* @phpstan-ignore-next-line */
            if (preg_match('/(\d+\.\d+)/', (string) $composerPhpVersionString, $matches)) {
                $parsedComposerVersion = $matches[1];
            }

            $mappedPhpVersion = PhpVersion::PHP_8_4;
            foreach (PhpVersion::cases() as $phpVersionCase) {
                if ($phpVersionCase->value === $parsedComposerVersion) {
                    $mappedPhpVersion = $phpVersionCase;
                    break;
                }
            }

            $generatedDefaultConfig = $this->generateDefaultConfig($mappedPhpVersion);
            try {
                file_put_contents($codemapConfigFilePath, $generatedDefaultConfig);
                $this->info('Created default codemap config at: '.$codemapConfigFilePath);
            } catch (Exception $e) {
                $this->error('Failed to create config file: '.$e->getMessage());

                return Command::FAILURE;
            }
            $configuredScanPaths = [getcwd().'/src'];
            $configuredPhpVersion = $mappedPhpVersion;
        }

        /* @var array<string> $paths */
        $paths = (array) $this->argument('paths');
        if ($paths === []) {
            $paths = $configuredScanPaths;
        }

        if ($paths === []) {
            $this->error('No scan paths provided or configured.');

            return Command::FAILURE;
        }

        /* @phpstan-ignore-next-line */
        $phpVersionString = (string) $this->option('php-version');
        if ($phpVersionString !== '') {
            $phpVersion = PhpVersion::tryFrom($phpVersionString);
            if (! $phpVersion instanceof PhpVersion) {
                $this->error('Invalid PHP version: '.$phpVersionString);

                return Command::FAILURE;
            }
        } else {
            $phpVersion = $configuredPhpVersion;
        }

        $outputFile = $this->option('output');

        $codemapGenerator = new CodemapGenerator;
        if ($phpVersion instanceof PhpVersion) {
            $codemapGenerator->setPhpParserVersion(\PhpParser\PhpVersion::fromString($phpVersion->value));
        }
        $codemapGenerator->setErrorHandler(fn ($message) => $this->error($message));

        $aggregatedCodemapResults = [];
        foreach ($paths as $scanPath) {
            /* @phpstan-ignore-next-line */
            if (! file_exists($scanPath)) {
                $this->error('Warning: Path "'.$scanPath.'" does not exist.');

                continue;
            }
            try {
                /* @phpstan-ignore-next-line */
                $fileResults = $codemapGenerator->generate($scanPath);
                foreach ($fileResults as $fileName => $codemapDto) {
                    $aggregatedCodemapResults[$fileName] = $codemapDto;
                }
            } catch (Exception $e) {
                $this->error('Error processing "'.$scanPath.'": '.$e->getMessage());
            }
        }

        $formatter = new TextCodemapFormatter;
        $formattedCodemapOutput = $formatter->format($aggregatedCodemapResults);

        if ($outputFile === '-') {
            $this->output->write($formattedCodemapOutput);
        } else {
            try {
                /* @phpstan-ignore-next-line */
                file_put_contents($outputFile, $formattedCodemapOutput);
                $this->info('Codemap generated at: '.$outputFile);
            } catch (Exception $e) {
                $this->error('Failed to write to "'.$outputFile.'": '.$e->getMessage());

                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
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

    protected function info(string $message): void
    {
        $this->output->writeln('<info>'.$message.'</info>');
    }

    protected function error(string $message): void
    {
        $this->output->writeln('<error>'.$message.'</error>');
    }

    protected function argument(string $name): mixed
    {
        return $this->input->getArgument($name);
    }

    protected function option(string $name): mixed
    {
        return $this->input->getOption($name);
    }
}
