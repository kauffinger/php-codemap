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

test('CodemapGenerator scans a directory with multiple files', function (): void {
    $tempDir = sys_get_temp_dir().'/codemap_test_'.uniqid();
    mkdir($tempDir);
    mkdir($tempDir.'/subdir');

    // Create multiple PHP files
    $file1 = $tempDir.'/File1.php';
    file_put_contents($file1, <<<'PHP'
<?php
class ClassA {}
PHP);

    $file2 = $tempDir.'/subdir/File2.php';
    file_put_contents($file2, <<<'PHP'
<?php
class ClassB {}
PHP);

    $generator = new CodemapGenerator;
    $result = $generator->generate($tempDir);

    // Clean up
    unlink($file1);
    unlink($file2);
    rmdir($tempDir.'/subdir');
    rmdir($tempDir);

    expect($result)->toHaveKeys(['File1.php', 'subdir/File2.php']);
    expect($result['File1.php']->classesInFile)->toHaveKey('ClassA');
    expect($result['subdir/File2.php']->classesInFile)->toHaveKey('ClassB');
});

test('CodemapGenerator ignores non-PHP files', function (): void {
    $tempDir = sys_get_temp_dir().'/codemap_test_'.uniqid();
    mkdir($tempDir);

    // Create a PHP file and a non-PHP file
    $phpFile = $tempDir.'/Test.php';
    file_put_contents($phpFile, <<<'PHP'
<?php
class TestClass {}
PHP);

    $txtFile = $tempDir.'/notes.txt';
    file_put_contents($txtFile, 'Some text');

    $generator = new CodemapGenerator;
    $result = $generator->generate($tempDir);

    // Clean up
    unlink($phpFile);
    unlink($txtFile);
    rmdir($tempDir);

    expect($result)->toHaveKey('Test.php');
    expect($result)->not->toHaveKey('notes.txt');
});

test('CodemapGenerator throws on syntax error', function (): void {
    $tempFile = sys_get_temp_dir().'/invalid.php';
    file_put_contents($tempFile, <<<'PHP'
<?php
class InvalidClass {
    public function method() // missing brace
}
PHP);

    $generator = new CodemapGenerator;
    expect(fn () => $generator->generate($tempFile))->toThrow(RuntimeException::class, 'Parse error');

    unlink($tempFile);
});
