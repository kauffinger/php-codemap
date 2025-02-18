<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Generator;

use Kauffinger\Codemap\Dto\CodemapParameterDto;
use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeVisitorAbstract;

class ClassCollectionVisitor extends NodeVisitorAbstract
{
    public array $collectedClasses = [];

    private ?string $currentClassName = null;

    public function enterNode(ComplexType|Node $node): array|int|Node|null
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
            } elseif ($returnTypeNode instanceof ComplexType) {
                // e.g. Union or Intersection types
                $determinedReturnType = $this->renderComplexType($returnTypeNode);
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
                } elseif ($param->type instanceof ComplexType) {
                    $paramType = $this->renderComplexType($param->type);
                }

                $paramNameNode = $param->var->name;
                if (is_string($paramNameNode)) {
                    $paramName = $paramNameNode;
                } elseif ($paramNameNode instanceof Node\Identifier) {
                    $paramName = $paramNameNode->name;
                } else {
                    $paramName = 'unknown';
                }

                $methodParameters[] = new CodemapParameterDto($paramName, $paramType);
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
            } elseif ($propertyTypeNode instanceof ComplexType) {
                $determinedPropertyType = $this->renderComplexType($propertyTypeNode);
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

        return null;
    }

    /**
     * Renders a complex type (union/intersection) as a string.
     */
    private function renderComplexType(ComplexType $node): string
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
        if ($n instanceof ComplexType) {
            return $this->renderComplexType($n);
        }

        return 'mixed';
    }
}
