<?php

declare(strict_types=1);

use Kauffinger\Codemap\CodemapGenerator;

test('CodemapGenerator returns empty array for invalid path', function (): void {
    $generator = new CodemapGenerator;
    expect($generator->generate('nonexistent/path'))->toBeEmpty();
});

test('CodemapGenerator can parse a simple file with one class', function (): void {
    // Prepare a temporary file with minimal PHP code
    $tempFile = sys_get_temp_dir().'/codemap_'.uniqid().'.php';
    file_put_contents($tempFile, <<<'PHP'
<?php

class SimpleClass {
    public function greet(): string {
        return "hello";
    }
}
PHP);

    $generator = new CodemapGenerator;
    $result = $generator->generate($tempFile);

    // Clean up
    unlink($tempFile);

    expect($result)->toHaveCount(1)
        ->and($result[array_key_first($result)]->classes)
        ->toHaveKey('SimpleClass');
});
