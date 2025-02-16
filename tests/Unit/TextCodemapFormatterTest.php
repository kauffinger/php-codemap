<?php

declare(strict_types=1);

use Kauffinger\Codemap\TextCodemapFormatter;

test('TextCodemapFormatter correctly formats class, methods, and public properties', function (): void {
    $formatter = new TextCodemapFormatter;

    $testData = [
        'Example.php' => [
            'classes' => [
                'Acme\\Example' => [
                    'methods' => [
                        ['visibility' => 'public', 'name' => 'run', 'returnType' => 'void'],
                        ['visibility' => 'protected', 'name' => 'test', 'returnType' => 'mixed'],
                    ],
                    'properties' => [
                        ['visibility' => 'public', 'name' => 'foo'],
                        ['visibility' => 'private', 'name' => 'bar'],
                    ],
                ],
            ],
        ],
    ];

    $output = $formatter->format($testData);

    expect($output)->toContain('File: Example.php')
        ->toContain('Class: Acme\\Example')
        ->toContain('public function run(): void')
        ->toContain('protected function test(): mixed')
        ->toContain('public property $foo')
        ->not->toContain('private property $bar'); // Should not list private props
});
