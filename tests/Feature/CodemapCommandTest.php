<?php

declare(strict_types=1);

use Kauffinger\Codemap\Console\CodemapCommand;

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

    // Mock CLI arguments (script name + path to scan)
    $args = [
        'codemap', // script name (ignored in the command)
        $testFile,
    ];

    // Call the command
    $exitCode = (new CodemapCommand)->__invoke($args);

    // Assert command success
    expect($exitCode)->toBe(0);

    // Check if codemap.txt got created (in the project root by default)
    $codemapFilePath = __DIR__.'/../../codemap.txt';
    expect(file_exists($codemapFilePath))->toBeTrue();

    // Clean up
    unlink($testFile);
    rmdir($tempDir);
    unlink($codemapFilePath);
});
