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

test('TextCodemapFormatter correctly formats traits with public members', function (): void {
    $formatter = new TextCodemapFormatter;

    $testData = [
        'Traits/ExampleTrait.php' => new CodemapFileDto(
            [], // No classes
            [], // No enums
            [ // Traits
                'App\\Traits\\ExampleTrait' => new Kauffinger\Codemap\Dto\CodemapTraitDto(
                    'App\\Traits\\ExampleTrait',
                    [ // Methods
                        new CodemapMethodDto('public', 'doSomething', 'void'),
                        new CodemapMethodDto('protected', 'helper', 'string'),
                    ],
                    [ // Properties
                        new CodemapPropertyDto('public', 'configValue', 'int'),
                        new CodemapPropertyDto('private', 'internalState', 'bool'),
                    ]
                ),
            ]
        ),
    ];

    $output = $formatter->format($testData);

    expect($output)->toContain('File: Traits/ExampleTrait.php')
        ->toContain('Trait: App\\Traits\\ExampleTrait')
        ->toContain('    public property int $configValue')
        ->toContain('    public function doSomething(): void')
        ->toContain('protected function helper')
        ->not->toContain('private property');
});

test('TextCodemapFormatter correctly formats class relationships', function (): void {
    $formatter = new TextCodemapFormatter;

    $testData = [
        'Structure/Complex.php' => new CodemapFileDto(
            [ // Classes
                'App\\Structure\\Complex' => new CodemapClassDto(
                    [new CodemapMethodDto('public', '__invoke', 'mixed')],
                    [new CodemapPropertyDto('public', 'name', 'string')],
                    ['App\\Traits\\Loggable', 'App\\Traits\\Runnable'], // Uses
                    'App\\Structure\\Base', // Extends
                    ['App\\Interfaces\\Serializable', 'App\\Interfaces\\Countable'] // Implements
                ),
            ]
        ),
    ];

    $output = $formatter->format($testData);

    expect($output)->toContain('File: Structure/Complex.php')
        ->toContain('  Class: App\\Structure\\Complex')
        ->toContain('    Extends: App\\Structure\\Base')
        ->toContain('    Implements: App\\Interfaces\\Serializable, App\\Interfaces\\Countable')
        ->toContain('    Uses: App\\Traits\\Loggable, App\\Traits\\Runnable')
        ->toContain('    public property string $name')
        ->toContain('    public function __invoke(): mixed');
});
