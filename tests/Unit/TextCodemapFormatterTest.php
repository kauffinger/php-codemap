<?php

declare(strict_types=1);

use Kauffinger\Codemap\Formatter\TextCodemapFormatter;

test('TextCodemapFormatter correctly formats class, methods, and public properties', function (): void {
    $formatter = new TextCodemapFormatter;

    $testData = [
        'Example.php' => new Kauffinger\Codemap\Dto\CodemapFileDto([
            'Acme\\Example' => new Kauffinger\Codemap\Dto\CodemapClassDto(
                [
                    new Kauffinger\Codemap\Dto\CodemapMethodDto('public', 'run', 'void'),
                    new Kauffinger\Codemap\Dto\CodemapMethodDto('protected', 'test', 'mixed'),
                    new Kauffinger\Codemap\Dto\CodemapMethodDto(
                        'public',
                        'multipleParams',
                        'int',
                        [
                            [
                                'parameterName' => 'input',
                                'parameterType' => 'string|int',
                            ],
                            [
                                'parameterName' => 'flag',
                                'parameterType' => 'bool',
                            ],
                        ]
                    ),
                ],
                [
                    new Kauffinger\Codemap\Dto\CodemapPropertyDto('public', 'foo', 'string'),
                    new Kauffinger\Codemap\Dto\CodemapPropertyDto('private', 'bar', 'int'),
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
