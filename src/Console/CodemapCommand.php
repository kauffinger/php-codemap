<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Console;

use Exception;
use InvalidArgumentException;
use Kauffinger\Codemap\Config\CodemapConfig;
use Kauffinger\Codemap\Dto\CodemapFileDto;
use Kauffinger\Codemap\Enum\PhpVersion;
use Kauffinger\Codemap\Formatter\TextCodemapFormatter;
use Kauffinger\Codemap\Generator\CodemapGenerator;
use Override;
use RuntimeException;
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
        try {
            $this->ensureConfigurationExists();
            $config = $this->loadConfiguration();

            $scanPaths = $this->argument('paths') ? (array) $this->argument('paths') : $config->getScanPaths();
            if ($scanPaths === []) {
                $this->error('No scan paths provided or configured.');

                return Command::FAILURE;
            }

            $phpVersion = $this->getPhpVersion($config);

            /* @phpstan-ignore-next-line */
            $codemapResults = $this->generateCodemap($scanPaths, $phpVersion);

            $formatter = new TextCodemapFormatter;
            $formattedOutput = $formatter->format($codemapResults);

            $outputFile = $this->option('output');
            if (! is_string($outputFile)) {
                throw new RuntimeException('Invalid output file path.');
            }

            $this->writeOutput($formattedOutput, $outputFile);

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    private function ensureConfigurationExists(): void
    {
        $codemapConfigFilePath = getcwd().'/codemap.php';
        if (file_exists($codemapConfigFilePath)) {
            return;
        }

        $composerJsonPath = getcwd().'/composer.json';
        if (! file_exists($composerJsonPath)) {
            throw new RuntimeException('composer.json not found.');
        }

        $composerContents = file_get_contents($composerJsonPath);
        if ($composerContents === false) {
            throw new RuntimeException('Failed to read composer.json.');
        }

        $composerData = json_decode($composerContents, true);
        if (! is_array($composerData)) {
            throw new RuntimeException('Invalid composer.json.');
        }
        $composerPhpVersionString = $composerData['require']['php'] ?? '^8.4.0';

        $parsedVersion = preg_match('/(\d+\.\d+)/', (string) $composerPhpVersionString, $matches) ? $matches[1] : '8.4';
        $mappedPhpVersion = PhpVersion::PHP_8_4;
        foreach (PhpVersion::cases() as $phpVersionCase) {
            if ($phpVersionCase->value === $parsedVersion) {
                $mappedPhpVersion = $phpVersionCase;
                break;
            }
        }

        $generatedConfig = $this->generateDefaultConfig($mappedPhpVersion);
        if (! file_put_contents($codemapConfigFilePath, $generatedConfig)) {
            throw new RuntimeException('Failed to write codemap.php.');
        }

        $this->info('Created default codemap config at: '.$codemapConfigFilePath);
    }

    private function loadConfiguration(): CodemapConfig
    {
        $codemapConfigFilePath = getcwd().'/codemap.php';
        if (! file_exists($codemapConfigFilePath)) {
            throw new RuntimeException('codemap.php not found.');
        }
        $codemapConfiguration = require $codemapConfigFilePath;
        if (! $codemapConfiguration instanceof CodemapConfig) {
            throw new RuntimeException('Invalid codemap configuration.');
        }

        return $codemapConfiguration;
    }

    private function getPhpVersion(CodemapConfig $config): ?PhpVersion
    {
        $phpVersionString = $this->option('php-version');
        if ($phpVersionString) {
            if (! is_string($phpVersionString) && ! is_int($phpVersionString)) {
                throw new InvalidArgumentException('Invalid PHP version: '.$phpVersionString);
            }

            $phpVersion = PhpVersion::tryFrom($phpVersionString);
            if (! $phpVersion instanceof PhpVersion) {
                throw new InvalidArgumentException('Invalid PHP version: '.$phpVersionString);
            }

            return $phpVersion;
        }

        return $config->getConfiguredPhpVersion();
    }

    /**
     * @param  string[]  $scanPaths
     * @return array<string, CodemapFileDto>
     */
    private function generateCodemap(array $scanPaths, ?PhpVersion $phpVersion): array
    {
        $codemapGenerator = new CodemapGenerator;
        if ($phpVersion instanceof PhpVersion) {
            $codemapGenerator->setPhpParserVersion($phpVersion->toParserPhpVersion());
        }
        $codemapGenerator->setErrorHandler(fn ($message) => $this->error($message));

        $aggregatedCodemapResults = [];
        foreach ($scanPaths as $scanPath) {
            if (! file_exists($scanPath)) {
                $this->error('Warning: Path "'.$scanPath.'" does not exist.');

                continue;
            }
            try {
                $fileResults = $codemapGenerator->generate($scanPath);
                $aggregatedCodemapResults += $fileResults;
            } catch (Exception $e) {
                $this->error('Error processing "'.$scanPath.'": '.$e->getMessage());
            }
        }

        return $aggregatedCodemapResults;
    }

    /**
     * @throws Exception
     */
    private function writeOutput(string $output, string $outputFile): void
    {
        if ($outputFile === '-') {
            $this->output->write($output);
        } else {
            try {
                file_put_contents($outputFile, $output);
                $this->info('Codemap generated at: '.$outputFile);
            } catch (Exception $e) {
                $this->error('Failed to write to "'.$outputFile.'": '.$e->getMessage());
                throw $e;
            }
        }
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
