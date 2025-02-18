<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Generator;

use Kauffinger\Codemap\Dto\CodemapClassDto;
use Kauffinger\Codemap\Dto\CodemapMethodDto;
use Kauffinger\Codemap\Dto\CodemapParameterDto;
use Kauffinger\Codemap\Dto\CodemapPropertyDto;
use Override;
use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeVisitorAbstract;

/**
 * A node visitor that collects class definitions (plus methods, properties) into CodemapClassDto objects.
 */
class ClassCollectionVisitor extends NodeVisitorAbstract
{
    /**
     * @var array<string, CodemapClassDto>
     */
    public array $collectedClasses = [];

    private ?string $currentClassName = null;

    #[Override]
    public function enterNode(Node $node): null|int|Node|array
    {
        if ($node instanceof Class_) {
            // Resolve class name (with namespace if available)
            $this->currentClassName = $node->namespacedName
                ? $node->namespacedName->toString()
                : (string) $node->name;

            // Initialize an empty CodemapClassDto for this class
            $this->collectedClasses[$this->currentClassName] = new CodemapClassDto;
        }

        return null;
    }

    #[Override]
    public function leaveNode(Node $node): null|int|Node|array
    {
        // If we have no current class, skip
        if ($this->currentClassName === null) {
            return null;
        }

        if ($node instanceof ClassMethod) {
            // Build the method DTO
            $methodVisibility = $node->isPublic()
                ? 'public'
                : ($node->isProtected() ? 'protected' : 'private');

            $determinedReturnType = $this->renderTypeNode($node->getReturnType());

            // Collect parameters
            $methodParameters = [];
            foreach ($node->getParams() as $param) {
                $paramType = $this->renderTypeNode($param->type);
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

            $newMethod = new CodemapMethodDto(
                $methodVisibility,
                $node->name->toString(),
                $determinedReturnType,
                $methodParameters
            );

            // Rebuild the class DTO with the new method
            $oldClassDto = $this->collectedClasses[$this->currentClassName];
            $updatedMethods = [...$oldClassDto->classMethods, $newMethod];
            $this->collectedClasses[$this->currentClassName] = new CodemapClassDto(
                $updatedMethods,
                $oldClassDto->classProperties
            );
        } elseif ($node instanceof Property) {
            $propertyVisibility = $node->isPublic()
                ? 'public'
                : ($node->isProtected() ? 'protected' : 'private');

            $determinedPropertyType = $this->renderTypeNode($node->type);

            // Each property statement can define multiple properties
            // e.g. "public int $a, $b;" => $a and $b
            foreach ($node->props as $propertyDefinition) {
                $newProperty = new CodemapPropertyDto(
                    $propertyVisibility,
                    $propertyDefinition->name->toString(),
                    $determinedPropertyType
                );

                // Rebuild the class DTO with the new property
                $oldClassDto = $this->collectedClasses[$this->currentClassName];
                $updatedProperties = [...$oldClassDto->classProperties, $newProperty];
                $this->collectedClasses[$this->currentClassName] = new CodemapClassDto(
                    $oldClassDto->classMethods,
                    $updatedProperties
                );
            }
        }

        if ($node instanceof Class_) {
            // Once we leave a Class_ node, there's no current class
            $this->currentClassName = null;
        }

        return null;
    }

    /**
     * Renders a (possibly complex) type node into a string (e.g. union, intersection, nullable).
     */
    private function renderTypeNode(?Node $typeNode): string
    {
        if (! $typeNode instanceof Node) {
            return 'mixed';
        }

        if ($typeNode instanceof Node\Identifier) {
            return $typeNode->name;
        }

        if ($typeNode instanceof Node\Name) {
            return $typeNode->toString();
        }

        if ($typeNode instanceof ComplexType) {
            return $this->renderComplexType($typeNode);
        }

        return 'mixed';
    }

    /**
     * Handles union, intersection, nullable type nodes.
     */
    private function renderComplexType(ComplexType $node): string
    {
        if ($node instanceof Node\UnionType) {
            return implode('|', array_map(fn (Node $n) => $this->renderTypeNode($n), $node->types));
        }

        if ($node instanceof Node\IntersectionType) {
            return implode('&', array_map(fn (Node $n) => $this->renderTypeNode($n), $node->types));
        }

        if ($node instanceof Node\NullableType) {
            return '?'.$this->renderTypeNode($node->type);
        }

        return 'mixed';
    }
}
