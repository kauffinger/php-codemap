<?php

declare(strict_types=1);

use Kauffinger\Codemap\Dto\CodemapClassDto;
use Kauffinger\Codemap\Dto\CodemapFileDto;
use Kauffinger\Codemap\Dto\CodemapMethodDto;
use Kauffinger\Codemap\Dto\CodemapParameterDto;
use Kauffinger\Codemap\Dto\CodemapPropertyDto;
use Kauffinger\Codemap\Formatter\TextCodemapFormatter;

test('TextCodemapFormatter correctly formats class, methods, and public properties', function (): void {
    $formatter = new TextCodemapFormatter;

    $testData = [
        'Example.php' => new CodemapFileDto([
            'Acme\\Example' => new CodemapClassDto(
                [
                    new CodemapMethodDto('public', 'run', 'void'),
                    new CodemapMethodDto('protected', 'test', 'mixed'),
                    new CodemapMethodDto(
                        'public',
                        'multipleParams',
                        'int',
                        [
                            new CodemapParameterDto('input', 'string|int'),
                            new CodemapParameterDto('flag', 'bool'),
                        ]
                    ),
                ],
                [
                    new CodemapPropertyDto('public', 'foo', 'string'),
                    new CodemapPropertyDto('private', 'bar', 'int'),
                ]
            ),
        ]),
    ];

    $output = $formatter->format($testData);

    expect($output)->toContain('File: Example.php')
        ->toContain('Class: Acme\\Example')
        ->toContain('public function run(): void')
        ->toContain('protected function test(): mixed')
        ->toContain('public function multipleParams(string|int $input, bool $flag): int')
        ->toContain('public property string $foo')
        ->not->toContain('private property $bar'); // Should not list private props
});
