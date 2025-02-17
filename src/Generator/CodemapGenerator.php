<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Generator;

use Kauffinger\Codemap\Dto\CodemapClassDto;
use Kauffinger\Codemap\Dto\CodemapFileDto;
use Kauffinger\Codemap\Dto\CodemapMethodDto;
use Kauffinger\Codemap\Dto\CodemapPropertyDto;
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
use SplFileInfo;

final class CodemapGenerator
{
    private ?PhpVersion $phpParserVersion = null;

    /**
     * Renders a complex type (union/intersection) as a string.
     */
    public function renderComplexType(Node\ComplexType $node): string
    {
        if ($node instanceof Node\UnionType) {
            return implode('|', array_map(fn (Node $n) => $this->typeNodeToString($n), $node->types));
        }
        if ($node instanceof Node\IntersectionType) {
            return implode('&', array_map(fn (Node $n) => $this->typeNodeToString($n), $node->types));
        }
        if ($node instanceof Node\NullableType) {
            return '?'.$this->typeNodeToString($node->type);
        }

        return 'mixed';
    }

    private function typeNodeToString(Node $n): string
    {
        if ($n instanceof Node\Identifier) {
            return $n->name;
        }
        if ($n instanceof Node\Name) {
            return $n->toString();
        }
        if ($n instanceof Node\ComplexType) {
            return $this->renderComplexType($n);
        }

        return 'mixed';
    }

    /**
     * Optionally set the PHP version used by PhpParser.
     */
    public function setPhpParserVersion(?PhpVersion $version): void
    {
        $this->phpParserVersion = $version;
    }

    /**
     * Scans the provided path (directory or single file).
     * If it's a directory, scans recursively for PHP files.
     * If it's a single file, parses just that file.
     *
     * @return array<string, CodemapFileDto> A map of fileName => codemap DTO
     */
    public function generate(string $pathToScan): array
    {
        if (! file_exists($pathToScan)) {
            return [];
        }

        // If path is a file, parse that single file
        if (is_file($pathToScan)) {
            return $this->processSingleFile($pathToScan);
        }

        // Otherwise, if path is a directory, scan recursively
        if (! is_dir($pathToScan)) {
            return [];
        }

        $scanResults = [];
        $directoryIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pathToScan));

        /** @var SplFileInfo $file */
        foreach ($directoryIterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $parsedFileResults = $this->processSingleFile($file->getRealPath());
            foreach ($parsedFileResults as $fileName => $codemapFileDto) {
                $scanResults[$fileName] = $codemapFileDto;
            }
        }

        return $scanResults;
    }

    /**
     * Processes a single PHP file, returning codemap data in a DTO.
     *
     * @return array<string, CodemapFileDto> The codemap results for this file
     */
    private function processSingleFile(string $filePath): array
    {
        if (! file_exists($filePath) || pathinfo($filePath, PATHINFO_EXTENSION) !== 'php') {
            return [];
        }

        $parser = (new ParserFactory)->createForVersion(
            $this->phpParserVersion ?? PhpVersion::getHostVersion()
        );

        $fileContents = file_get_contents($filePath);
        if ($fileContents === false) {
            return [];
        }

        try {
            $abstractSyntaxTree = $parser->parse($fileContents);
        } catch (Error $parseError) {
            echo 'Parse Error: '.$parseError->getMessage().PHP_EOL;

            return [];
        }

        $fileName = basename($filePath);

        $classCollectionVisitor = new class($this) extends NodeVisitorAbstract
        {
            public array $collectedClasses = [];

            private ?string $currentClassName = null;

            public function __construct(
                private readonly CodemapGenerator $generator
            ) {}

            public function enterNode(Node\ComplexType|Node $node): void
            {
                if ($node instanceof Class_) {
                    $this->currentClassName = $node->namespacedName
                        ? $node->namespacedName->toString()
                        : (string) $node->name;

                    $this->collectedClasses[$this->currentClassName] = [
                        'classMethods' => [],
                        'classProperties' => [],
                    ];
                } elseif ($node instanceof ClassMethod && $this->currentClassName !== null) {
                    $methodVisibility = $node->isPublic()
                        ? 'public'
                        : ($node->isProtected() ? 'protected' : 'private');

                    $returnTypeNode = $node->getReturnType();
                    if ($returnTypeNode instanceof Node\Identifier) {
                        $determinedReturnType = $returnTypeNode->name;
                    } elseif ($returnTypeNode instanceof Node\Name) {
                        $determinedReturnType = $returnTypeNode->toString();
                    } elseif ($returnTypeNode instanceof Node\ComplexType) {
                        // e.g. Union or Intersection types
                        $determinedReturnType = $this->generator->renderComplexType($returnTypeNode);
                    } else {
                        $determinedReturnType = 'mixed';
                    }

                    // Collect parameters
                    $methodParameters = [];
                    foreach ($node->getParams() as $param) {
                        $paramType = 'mixed';
                        if ($param->type instanceof Node\Identifier) {
                            $paramType = $param->type->name;
                        } elseif ($param->type instanceof Node\Name) {
                            $paramType = $param->type->toString();
                        } elseif ($param->type instanceof Node\ComplexType) {
                            $paramType = $this->generator->renderComplexType($param->type);
                        }

                        $paramNameNode = $param->var->name;
                        if (is_string($paramNameNode)) {
                            $paramName = $paramNameNode;
                        } elseif ($paramNameNode instanceof Node\Identifier) {
                            $paramName = $paramNameNode->name;
                        } else {
                            $paramName = 'unknown';
                        }

                        $methodParameters[] = [
                            'parameterName' => $paramName,
                            'parameterType' => $paramType,
                        ];
                    }

                    $this->collectedClasses[$this->currentClassName]['classMethods'][] = [
                        'methodVisibility' => $methodVisibility,
                        'methodName' => $node->name->toString(),
                        'methodReturnType' => $determinedReturnType,
                        'methodParameters' => $methodParameters,
                    ];
                } elseif ($node instanceof Property && $this->currentClassName !== null) {
                    $propertyVisibility = $node->isPublic()
                        ? 'public'
                        : ($node->isProtected() ? 'protected' : 'private');

                    $propertyTypeNode = $node->type;
                    if ($propertyTypeNode instanceof Node\Identifier) {
                        $determinedPropertyType = $propertyTypeNode->name;
                    } elseif ($propertyTypeNode instanceof Node\Name) {
                        $determinedPropertyType = $propertyTypeNode->toString();
                    } elseif ($propertyTypeNode instanceof Node\ComplexType) {
                        $determinedPropertyType = $this->generator->renderComplexType($propertyTypeNode);
                    } else {
                        $determinedPropertyType = 'mixed';
                    }

                    foreach ($node->props as $propertyDefinition) {
                        $this->collectedClasses[$this->currentClassName]['classProperties'][] = [
                            'propertyVisibility' => $propertyVisibility,
                            'propertyName' => $propertyDefinition->name->toString(),
                            'propertyType' => $determinedPropertyType,
                        ];
                    }
                }
            }
        };

        $nodeTraverser = new NodeTraverser;
        $nodeTraverser->addVisitor(new NameResolver);
        $nodeTraverser->addVisitor($classCollectionVisitor);
        $nodeTraverser->traverse((array) $abstractSyntaxTree);

        // Convert arrays to typed DTOs
        $discoveredClasses = [];
        foreach ($classCollectionVisitor->collectedClasses as $className => $classData) {
            $classMethods = [];
            foreach ($classData['classMethods'] as $methodData) {
                $classMethods[] = new CodemapMethodDto(
                    $methodData['methodVisibility'],
                    $methodData['methodName'],
                    $methodData['methodReturnType'],
                    $methodData['methodParameters'] ?? []
                );
            }

            $classProperties = [];
            foreach ($classData['classProperties'] as $propertyData) {
                $classProperties[] = new CodemapPropertyDto(
                    $propertyData['propertyVisibility'],
                    $propertyData['propertyName'],
                    $propertyData['propertyType']
                );
            }

            $discoveredClasses[$className] = new CodemapClassDto(
                $classMethods,
                $classProperties
            );
        }

        $codemapFileDto = new CodemapFileDto($discoveredClasses);

        return [$fileName => $codemapFileDto];
    }
}
