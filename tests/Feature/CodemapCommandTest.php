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
