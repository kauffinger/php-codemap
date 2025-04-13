<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Generator;

use Kauffinger\Codemap\Dto\CodemapClassDto;
use Kauffinger\Codemap\Dto\CodemapEnumDto;
use Kauffinger\Codemap\Dto\CodemapMethodDto;
use Kauffinger\Codemap\Dto\CodemapParameterDto;
use Kauffinger\Codemap\Dto\CodemapPropertyDto;
use Kauffinger\Codemap\Dto\CodemapTraitDto;
use Override;
use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeVisitorAbstract;

/**
 * A node visitor that collects classes, enums, and traits into DTOs.
 */
final class SymbolCollectionVisitor extends NodeVisitorAbstract
{
    /**
     * @var array<string, CodemapClassDto>
     */
    public array $collectedClasses = [];

    /**
     * @var array<string, CodemapEnumDto>
     */
    public array $collectedEnums = [];

    /**
     * @var array<string, CodemapTraitDto>
     */
    public array $collectedTraits = [];

    private ?string $currentClassName = null;

    private ?string $currentEnumName = null;

    private ?string $currentTraitName = null;

    #[Override]
    public function enterNode(Node $node): null|int|Node|array
    {
        // Handle class
        if ($node instanceof Class_) {
            $this->currentClassName = $node->namespacedName
                ? $node->namespacedName->toString()
                : (string) $node->name;

            // Extract inheritance, implementation, and trait usage
            $extends = $node->extends ? $node->extends->toString() : null;
            $implements = array_map(fn (Node\Name $name) => $name->toString(), $node->implements);
            $uses = [];
            foreach ($node->getTraitUses() as $traitUse) {
                foreach ($traitUse->traits as $traitName) {
                    $uses[] = $traitName->toString();
                }
            }

            // Initialize DTO with structural info; methods/props added later
            $this->collectedClasses[$this->currentClassName] = new CodemapClassDto(
                [], // Methods added later
                [], // Properties added later
                $uses,
                $extends,
                $implements
            );
        }
        // Handle enum
        elseif ($node instanceof Enum_) {
            $this->currentEnumName = $node->namespacedName
                ? $node->namespacedName->toString()
                : (string) $node->name;

            // Check if this is a backed enum
            $backingType = null;
            if ($node->scalarType !== null) {
                $backingType = $this->renderTypeNode($node->scalarType);
            }

            $this->collectedEnums[$this->currentEnumName] = new CodemapEnumDto(
                $this->currentEnumName,
                $backingType
            );
        }
        // Handle trait
        elseif ($node instanceof Trait_) {
            $this->currentTraitName = $node->namespacedName
                ? $node->namespacedName->toString()
                : (string) $node->name; // Fallback, though namespacedName should exist after NameResolver

            $this->collectedTraits[$this->currentTraitName] = new CodemapTraitDto($this->currentTraitName);
        }

        return null;
    }

    #[Override]
    public function leaveNode(Node $node): null|int|Node|array
    {
        // End of a class
        if ($node instanceof Class_ && $this->currentClassName !== null) {
            $this->currentClassName = null;
        }
        // End of an enum
        elseif ($node instanceof Enum_ && $this->currentEnumName !== null) {
            $this->currentEnumName = null;
        }
        // End of a trait
        elseif ($node instanceof Trait_ && $this->currentTraitName !== null) {
            $this->currentTraitName = null;
        }
        // Inside a class or trait
        elseif ($this->currentClassName !== null || $this->currentTraitName !== null) {
            if ($node instanceof ClassMethod) {
                $this->handleClassMethod($node);
            } elseif ($node instanceof Property) {
                $this->handleProperty($node);
            }
        }
        // Inside an enum
        elseif ($this->currentEnumName !== null) {
            if ($node instanceof EnumCase) {
                $this->handleEnumCase($node);
            }
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
     * Processes a ClassMethod node, building and adding its DTO to the current class or trait.
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
            /* @phpstan-ignore-next-line */
            $paramName = is_string($param->var->name) ? $param->var->name : 'unknown';
            $methodParameters[] = new CodemapParameterDto($paramName, $paramType);
        }

        $newMethod = new CodemapMethodDto(
            $methodVisibility,
            $node->name->toString(),
            $determinedReturnType,
            $methodParameters
        );

        if ($this->currentClassName !== null) {
            $oldClassDto = $this->collectedClasses[$this->currentClassName];
            $updatedMethods = [...$oldClassDto->classMethods, $newMethod];
            $this->collectedClasses[$this->currentClassName] = new CodemapClassDto(
                $updatedMethods,
                $oldClassDto->classProperties,
                $oldClassDto->usesTraits,
                $oldClassDto->extendsClass,
                $oldClassDto->implementsInterfaces
            );
        } elseif ($this->currentTraitName !== null) {
            $oldTraitDto = $this->collectedTraits[$this->currentTraitName];
            $updatedMethods = [...$oldTraitDto->traitMethods, $newMethod];
            $this->collectedTraits[$this->currentTraitName] = new CodemapTraitDto(
                $oldTraitDto->traitName,
                $updatedMethods,
                $oldTraitDto->traitProperties
            );
        }
    }

    /**
     * Processes a Property node, building and adding its DTO(s) to the current class or trait.
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

            if ($this->currentClassName !== null) {
                $oldClassDto = $this->collectedClasses[$this->currentClassName];
                $updatedProperties = [...$oldClassDto->classProperties, $newProperty];
                $this->collectedClasses[$this->currentClassName] = new CodemapClassDto(
                    $oldClassDto->classMethods,
                    $updatedProperties,
                    $oldClassDto->usesTraits,
                    $oldClassDto->extendsClass,
                    $oldClassDto->implementsInterfaces
                );
            } elseif ($this->currentTraitName !== null) {
                $oldTraitDto = $this->collectedTraits[$this->currentTraitName];
                $updatedProperties = [...$oldTraitDto->traitProperties, $newProperty];
                $this->collectedTraits[$this->currentTraitName] = new CodemapTraitDto(
                    $oldTraitDto->traitName,
                    $oldTraitDto->traitMethods,
                    $updatedProperties
                );
            }
        }
    }

    /**
     * Processes an EnumCase node, adding each case to the current enum.
     */
    private function handleEnumCase(EnumCase $node): void
    {
        $enumDto = $this->collectedEnums[$this->currentEnumName];
        $caseName = $node->name->toString();

        // Attempt to determine the case value for backed enums
        $caseValue = null;
        if ($node->expr !== null) {
            $caseValue = $this->renderEnumCaseValue($node->expr);
        }

        $enumDto->cases[$caseName] = $caseValue;
        $this->collectedEnums[$this->currentEnumName] = new CodemapEnumDto(
            $enumDto->enumName,
            $enumDto->backingType,
            $enumDto->cases
        );
    }

    /**
     * Render an enum case's expression to string if possible (backed enums).
     */
    private function renderEnumCaseValue(Node $expr): ?string
    {
        if ($expr instanceof Node\Scalar\LNumber) {
            return (string) $expr->value;
        }
        if ($expr instanceof Node\Scalar\String_) {
            // Put quotes around string literals
            return "'".$expr->value."'";
        }
        if ($expr instanceof Node\Expr\ClassConstFetch) {
            /* @phpstan-ignore-next-line */
            $className = $expr->class->toString();
            /* @phpstan-ignore-next-line */
            $constName = $expr->name->toString();

            return $className.'::'.$constName;
        }

        // For other expressions, fallback to null
        return null;
    }
}
