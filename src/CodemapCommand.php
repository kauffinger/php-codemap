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

        // If no paths are provided, default to scanning the src folder
        if ($args === []) {
            $args = [__DIR__.'/../src'];
        }

        $pathsToScan = $args;
        $outputFile = __DIR__.'/../codemap.txt';

        $generator = new CodemapGenerator;
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
