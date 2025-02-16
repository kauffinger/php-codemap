<?php

declare(strict_types=1);

namespace Kauffinger\Codemap;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class CodemapGenerator
{
    /**
     * Scans the provided path (directory or single file).
     * If it's a directory, it scans recursively for PHP files.
     * If it's a single file, it parses just that file.
     *
     * @param  string  $path  Directory or file to parse.
     * @return array Parsed structure of classes, methods, and properties.
     */
    public function generate(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        // If path is a file, just parse that single file.
        if (is_file($path)) {
            return $this->processFile($path);
        }

        // Otherwise, path is a directory; scan recursively.
        if (! is_dir($path)) {
            return [];
        }

        $results = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $parsed = $this->processFile($file->getRealPath());
            $results = array_merge($results, $parsed);
        }

        return $results;
    }

    /**
     * Processes a single PHP file, returning codemap data.
     *
     * @param  string  $filePath  The file to parse.
     * @return array The codemap results for this file.
     */
    private function processFile(string $filePath): array
    {
        if (! file_exists($filePath) || pathinfo($filePath, PATHINFO_EXTENSION) !== 'php') {
            return [];
        }

        $parser = (new ParserFactory)->createForVersion(PhpVersion::getHostVersion());
        $code = file_get_contents($filePath);

        try {
            $ast = $parser->parse($code);
        } catch (Error $e) {
            echo 'Parse Error: '.$e->getMessage().PHP_EOL;

            return [];
        }

        $fileName = basename($filePath);
        $results = [
            $fileName => ['classes' => []],
        ];

        // Create a NodeVisitor to track classes, methods, and properties
        $visitor = new class extends NodeVisitorAbstract
        {
            public array $classes = [];

            private ?string $currentClassName = null;

            public function enterNode(Node $node)
            {
                // Capture class names
                if ($node instanceof Class_) {
                    $this->currentClassName = $node->namespacedName
                        ? $node->namespacedName->toString()
                        : (string) $node->name;

                    $this->classes[$this->currentClassName] = [
                        'methods' => [],
                        'properties' => [],
                    ];
                }
                // Capture methods
                elseif ($node instanceof ClassMethod && $this->currentClassName !== null) {
                    $visibility = $node->isPublic()
                        ? 'public'
                        : ($node->isProtected() ? 'protected' : 'private');

                    $returnType = $node->getReturnType();
                    if ($returnType instanceof Node\Identifier) {
                        $returnType = $returnType->name;
                    } elseif ($returnType instanceof Node\Name) {
                        $returnType = $returnType->toString();
                    } else {
                        $returnType = 'mixed';
                    }

                    $this->classes[$this->currentClassName]['methods'][] = [
                        'visibility' => $visibility,
                        'name' => $node->name->toString(),
                        'returnType' => $returnType,
                    ];
                }
                // Capture properties
                elseif ($node instanceof Property && $this->currentClassName !== null) {
                    $visibility = $node->isPublic()
                        ? 'public'
                        : ($node->isProtected() ? 'protected' : 'private');

                    foreach ($node->props as $prop) {
                        $this->classes[$this->currentClassName]['properties'][] = [
                            'visibility' => $visibility,
                            'name' => $prop->name->toString(),
                        ];
                    }
                }
            }
        };

        // Traverse the AST
        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver);
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        // Store the gathered class info for this file
        $results[$fileName]['classes'] = $visitor->classes;

        return $results;
    }
}
