<?php

declare(strict_types=1);

use Kauffinger\Codemap\Generator\CodemapGenerator;

test('CodemapGenerator throws for invalid path', function (): void {
    $generator = new CodemapGenerator;
    expect($generator->generate('nonexistent/path'))->toBeEmpty();
})->throws(RuntimeException::class);

test('CodemapGenerator can parse a simple file with one class', function (): void {
    // Prepare a temporary file with minimal PHP code
    $tempFile = sys_get_temp_dir().'/codemap_'.uniqid().'.php';
    file_put_contents($tempFile, <<<'PHP'
<?php

class SimpleClass {
    public function greet(\Kauffinger\Codemap\Dto\CodemapClassDto $dto): string {
        return "hello";
    }
}
PHP);

    $generator = new CodemapGenerator;
    $result = $generator->generate($tempFile);

    // Clean up
    unlink($tempFile);

    expect($result)->toHaveCount(1)
        ->and($result[array_key_first($result)]->classesInFile)
        ->toHaveKey('SimpleClass');
});

test('CodemapGenerator can parse a method with multiple parameters including union type', function (): void {
    // Prepare a temporary file with more complex PHP code
    $tempFile = sys_get_temp_dir().'/codemap_'.uniqid().'.php';
    file_put_contents($tempFile, <<<'PHP'
<?php

class AdvancedClass {
    public function doSomething(array|int $param1, string $param2, \Foo\Bar $param3): ?\Baz {
        return null;
    }
}
PHP);

    $generator = new CodemapGenerator;
    $result = $generator->generate($tempFile);

    // Clean up
    unlink($tempFile);

    // Expect a single file result
    expect($result)->toHaveCount(1);

    // Extract first file
    $fileKey = array_key_first($result);
    $codemapFileDto = $result[$fileKey];
    expect($codemapFileDto->classesInFile)->toHaveKey('AdvancedClass');

    $advancedClass = $codemapFileDto->classesInFile['AdvancedClass'];
    expect($advancedClass->classMethods)->toHaveLength(1);

    $doSomethingMethod = $advancedClass->classMethods[0];
    expect($doSomethingMethod->methodName)->toBe('doSomething')
        ->and($doSomethingMethod->methodVisibility)->toBe('public')
        ->and($doSomethingMethod->methodReturnType)->toBe('?Baz')
        ->and($doSomethingMethod->methodParameters)->toHaveCount(3);

    // Confirm each parameter's type
    expect($doSomethingMethod->methodParameters[0]->parameterType)->toBe('array|int');
    expect($doSomethingMethod->methodParameters[1]->parameterType)->toBe('string');
    expect($doSomethingMethod->methodParameters[2]->parameterType)->toBe('Foo\\Bar');
});
