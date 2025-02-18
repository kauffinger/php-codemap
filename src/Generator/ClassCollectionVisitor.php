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
        if ($this->currentClassName === null) {
            return null;
        }

        if ($node instanceof ClassMethod) {
            $this->handleClassMethod($node);
        } elseif ($node instanceof Property) {
            $this->handleProperty($node);
        }

        if ($node instanceof Class_) {
            $this->currentClassName = null;
        }

        return null;
    }

    /**
     * Renders a (possibly complex) type node into a string (e.g., union, intersection, nullable).
     */
    private function renderTypeNode(?Node $typeNode): string
    {
        if (! $typeNode instanceof Node) {
            return 'mixed';
        }

        return match (true) {
            $typeNode instanceof Node\Identifier => $typeNode->name,
            $typeNode instanceof Node\Name => $typeNode->toString(),
            $typeNode instanceof ComplexType => $this->renderComplexType($typeNode),
            default => 'mixed',
        };
    }

    /**
     * Handles union, intersection, and nullable type nodes, rendering them as strings.
     */
    private function renderComplexType(ComplexType $node): string
    {
        return match (true) {
            $node instanceof Node\UnionType => implode('|', array_map(fn (Node $n) => $this->renderTypeNode($n), $node->types)),
            $node instanceof Node\IntersectionType => implode('&', array_map(fn (Node $n) => $this->renderTypeNode($n), $node->types)),
            $node instanceof Node\NullableType => '?'.$this->renderTypeNode($node->type),
            default => 'mixed',
        };
    }

    /**
     * Processes a ClassMethod node, building and adding its DTO to the current class.
     */
    private function handleClassMethod(ClassMethod $node): void
    {
        $methodVisibility = $node->isPublic()
            ? 'public'
            : ($node->isProtected() ? 'protected' : 'private');

        $determinedReturnType = $this->renderTypeNode($node->getReturnType());

        // Build parameter DTOs for the method
        $methodParameters = [];
        foreach ($node->getParams() as $param) {
            $paramType = $this->renderTypeNode($param->type);
            $paramName = is_string($param->var->name) ? $param->var->name : 'unknown';
            $methodParameters[] = new CodemapParameterDto($paramName, $paramType);
        }

        $newMethod = new CodemapMethodDto(
            $methodVisibility,
            $node->name->toString(),
            $determinedReturnType,
            $methodParameters
        );

        $this->addMethodToCurrentClass($newMethod);
    }

    /**
     * Processes a Property node, building and adding its DTO(s) to the current class.
     */
    private function handleProperty(Property $node): void
    {
        $propertyVisibility = $node->isPublic()
            ? 'public'
            : ($node->isProtected() ? 'protected' : 'private');

        $determinedPropertyType = $this->renderTypeNode($node->type);

        foreach ($node->props as $propertyDefinition) {
            $newProperty = new CodemapPropertyDto(
                $propertyVisibility,
                $propertyDefinition->name->toString(),
                $determinedPropertyType
            );

            $this->addPropertyToCurrentClass($newProperty);
        }
    }

    /**
     * Updates the current class DTO by adding a new method.
     */
    private function addMethodToCurrentClass(CodemapMethodDto $method): void
    {
        $oldClassDto = $this->collectedClasses[$this->currentClassName];
        $updatedMethods = [...$oldClassDto->classMethods, $method];
        $this->collectedClasses[$this->currentClassName] = new CodemapClassDto(
            $updatedMethods,
            $oldClassDto->classProperties
        );
    }

    /**
     * Updates the current class DTO by adding a new property.
     */
    private function addPropertyToCurrentClass(CodemapPropertyDto $property): void
    {
        $oldClassDto = $this->collectedClasses[$this->currentClassName];
        $updatedProperties = [...$oldClassDto->classProperties, $property];
        $this->collectedClasses[$this->currentClassName] = new CodemapClassDto(
            $oldClassDto->classMethods,
            $updatedProperties
        );
    }
}
