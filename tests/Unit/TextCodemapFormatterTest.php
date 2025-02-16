<?php

declare(strict_types=1);

use Kauffinger\Codemap\TextCodemapFormatter;

test('TextCodemapFormatter correctly formats class, methods, and public properties', function (): void {
    $formatter = new TextCodemapFormatter;

    $testData = [
        'Example.php' => new Kauffinger\Codemap\Dto\CodemapFileDto([
            'Acme\\Example' => new Kauffinger\Codemap\Dto\CodemapClassDto(
                [
                    new Kauffinger\Codemap\Dto\CodemapMethodDto('public', 'run', 'void'),
                    new Kauffinger\Codemap\Dto\CodemapMethodDto('protected', 'test', 'mixed'),
                ],
                [
                    new Kauffinger\Codemap\Dto\CodemapPropertyDto('public', 'foo'),
                    new Kauffinger\Codemap\Dto\CodemapPropertyDto('private', 'bar'),
                ]
            ),
        ]),
    ];

    $output = $formatter->format($testData);

    expect($output)->toContain('File: Example.php')
        ->toContain('Class: Acme\\Example')
        ->toContain('public function run(): void')
        ->toContain('protected function test(): mixed')
        ->toContain('public property $foo')
        ->not->toContain('private property $bar'); // Should not list private props
});
