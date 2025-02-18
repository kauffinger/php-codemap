<?php

declare(strict_types=1);

use Kauffinger\Codemap\Console\CodemapCommand;
use Symfony\Component\Console\Tester\CommandTester;

test('CodemapCommand runs without error and generates codemap.txt', function (): void {
    // Use a temp directory for output
    $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'php_codemap_test_'.uniqid();
    mkdir($tempDir);

    // Create a test file to parse
    $testFile = $tempDir.DIRECTORY_SEPARATOR.'TestClass.php';
    file_put_contents($testFile, <<<'PHP'
<?php

class TestClass {
    public function hello(): string {
        return "Hello World";
    }
}
PHP);

    // Set the output file path within the temporary directory
    $codemapFilePath = $tempDir.DIRECTORY_SEPARATOR.'codemap.txt';

    // Create and set up the command tester
    $command = new CodemapCommand;
    $commandTester = new CommandTester($command);

    // Execute the command with arguments and options
    $commandTester->execute([
        'paths' => [$testFile],
        '--output' => $codemapFilePath,
    ]);

    // Assert command executed successfully
    expect($commandTester->getStatusCode())->toBe(0);

    // Check if codemap.txt was created
    expect(file_exists($codemapFilePath))->toBeTrue();

    // Verify the content of the generated codemap
    $codemapContent = file_get_contents($codemapFilePath);
    expect($codemapContent)->toContain('File: TestClass.php')
        ->toContain('Class: TestClass')
        ->toContain('public function hello(): string');

    // Clean up
    unlink($testFile);
    unlink($codemapFilePath);
    rmdir($tempDir);
});

test('CodemapCommand uses configured scan paths', function (): void {
    $originalCwd = getcwd();
    $tempDir = sys_get_temp_dir().'/codemap_test_'.uniqid();
    mkdir($tempDir);
    chdir($tempDir);

    // Create codemap.php with configured scan paths
    $codemapPhp = <<<'PHP'
<?php
use Kauffinger\Codemap\Config\CodemapConfig;
return CodemapConfig::configure()->withScanPaths([__DIR__ . '/src']);
PHP;
    file_put_contents($tempDir.'/codemap.php', $codemapPhp);

    // Create src directory with a PHP file
    mkdir($tempDir.'/src');
    $testFile = $tempDir.'/src/TestClass.php';
    file_put_contents($testFile, <<<'PHP'
<?php
class TestClass {}
PHP);

    // Set output file path
    $codemapFilePath = $tempDir.'/codemap.txt';

    // Run the command without arguments to use config
    $command = new CodemapCommand;
    $commandTester = new CommandTester($command);
    $commandTester->execute(['--output' => $codemapFilePath]);

    // Assert command executed successfully
    expect($commandTester->getStatusCode())->toBe(0);

    // Check if codemap.txt was created
    expect(file_exists($codemapFilePath))->toBeTrue();

    // Verify the content
    $codemapContent = file_get_contents($codemapFilePath);
    expect($codemapContent)->toContain('File: TestClass.php')
        ->toContain('Class: TestClass');

    // Clean up
    unlink($testFile);
    rmdir($tempDir.'/src');
    unlink($tempDir.'/codemap.php');
    unlink($codemapFilePath);
    rmdir($tempDir);
    chdir($originalCwd);
});

test('CodemapCommand handles multiple paths', function (): void {
    $tempDir1 = sys_get_temp_dir().'/codemap_test1_'.uniqid();
    $tempDir2 = sys_get_temp_dir().'/codemap_test2_'.uniqid();
    mkdir($tempDir1);
    mkdir($tempDir2);

    // Create test files in different directories
    $testFile1 = $tempDir1.'/ClassA.php';
    file_put_contents($testFile1, <<<'PHP'
<?php
class ClassA {}
PHP);

    $testFile2 = $tempDir2.'/ClassB.php';
    file_put_contents($testFile2, <<<'PHP'
<?php
class ClassB {}
PHP);

    // Set output file path
    $codemapFilePath = sys_get_temp_dir().'/codemap.txt';

    // Run the command with multiple paths
    $command = new CodemapCommand;
    $commandTester = new CommandTester($command);
    $commandTester->execute([
        'paths' => [$tempDir1, $tempDir2],
        '--output' => $codemapFilePath,
    ]);

    // Assert command executed successfully
    expect($commandTester->getStatusCode())->toBe(0);

    // Check if codemap.txt was created
    expect(file_exists($codemapFilePath))->toBeTrue();

    // Verify the content includes classes from both paths
    $codemapContent = file_get_contents($codemapFilePath);
    expect($codemapContent)->toContain('File: ClassA.php')
        ->toContain('Class: ClassA')
        ->toContain('File: ClassB.php')
        ->toContain('Class: ClassB');

    // Clean up
    unlink($testFile1);
    unlink($testFile2);
    rmdir($tempDir1);
    rmdir($tempDir2);
    unlink($codemapFilePath);
});

test('CodemapCommand handles syntax errors in files', function (): void {
    $tempDir = sys_get_temp_dir().'/codemap_test_'.uniqid();
    mkdir($tempDir);

    // Create a valid PHP file
    $validFile = $tempDir.'/Valid.php';
    file_put_contents($validFile, <<<'PHP'
<?php
class ValidClass {}
PHP);

    // Create an invalid PHP file with a syntax error
    $invalidFile = $tempDir.'/Invalid.php';
    file_put_contents($invalidFile, <<<'PHP'
<?php
class InvalidClass {
    public function method() // missing brace
}
PHP);

    // Set output file path
    $codemapFilePath = $tempDir.'/codemap.txt';

    // Run the command with the directory containing both files
    $command = new CodemapCommand;
    $commandTester = new CommandTester($command);
    $commandTester->execute([
        'paths' => [$tempDir],
        '--output' => $codemapFilePath,
    ]);

    // Assert command executed successfully despite the error
    expect($commandTester->getStatusCode())->toBe(0);

    // Check if codemap.txt was created
    expect(file_exists($codemapFilePath))->toBeTrue();

    // Verify the content includes only the valid file
    $codemapContent = file_get_contents($codemapFilePath);
    expect($codemapContent)->toContain('File: Valid.php')
        ->toContain('Class: ValidClass')
        ->not->toContain('File: Invalid.php');

    // Check if an error message was output for the invalid file
    $output = $commandTester->getDisplay();
    expect($output)->toContain('Error processing');

    // Clean up
    unlink($validFile);
    unlink($invalidFile);
    unlink($codemapFilePath);
    rmdir($tempDir);
});
