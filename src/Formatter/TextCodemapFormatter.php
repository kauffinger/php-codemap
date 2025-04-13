<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Formatter;

use Kauffinger\Codemap\Dto\CodemapFileDto;
use Kauffinger\Codemap\Dto\CodemapMethodDto;
use Kauffinger\Codemap\Dto\CodemapParameterDto;
use Kauffinger\Codemap\Dto\CodemapPropertyDto;

// Added
// Added

final readonly class TextCodemapFormatter
{
    /**
     * @param  string[]  $allowedPropertyVisibilities
     * @param  string[]  $allowedMethodVisibilities
     */
    public function __construct(private array $allowedPropertyVisibilities = ['public'], private array $allowedMethodVisibilities = ['public', 'protected', 'private']) {}

    /**
     * Formats the codemap data into a human-readable text representation.
     *
     * @param  array<string, CodemapFileDto>  $codemapData
     */
    public function format(array $codemapData): string
    {
        $lines = [];
        foreach ($codemapData as $fileName => $fileData) {
            $lines[] = "File: {$fileName}";

            // Format Classes
            foreach ($fileData->classesInFile as $className => $classInformation) {
                $lines[] = "  Class: {$className}";
                if ($classInformation->extendsClass) {
                    $lines[] = "    Extends: {$classInformation->extendsClass}";
                }
                if ($classInformation->implementsInterfaces !== []) {
                    $lines[] = '    Implements: '.implode(', ', $classInformation->implementsInterfaces);
                }
                if ($classInformation->usesTraits !== []) {
                    $lines[] = '    Uses: '.implode(', ', $classInformation->usesTraits);
                }

                // Format Properties based on allowed visibility
                foreach ($classInformation->classProperties as $propertyInformation) {
                    if (in_array($propertyInformation->propertyVisibility, $this->allowedPropertyVisibilities, true)) {
                        $lines[] = $this->formatProperty($propertyInformation);
                    }
                }
                // Format Methods based on allowed visibility
                foreach ($classInformation->classMethods as $methodInformation) {
                    if (in_array($methodInformation->methodVisibility, $this->allowedMethodVisibilities, true)) {
                        $lines[] = $this->formatMethod($methodInformation);
                    }
                }
            }

            // Format Enums
            foreach ($fileData->enumsInFile as $enumName => $enumDto) {
                $backingInfo = $enumDto->backingType ? ": {$enumDto->backingType}" : '';
                $lines[] = "  Enum: {$enumName}{$backingInfo}";
                foreach ($enumDto->cases as $caseName => $caseValue) {
                    $lines[] = $this->formatEnumCase($caseValue, $caseName);
                }
            }

            // Format Traits
            foreach ($fileData->traitsInFile as $traitName => $traitDto) {
                $lines[] = "  Trait: {$traitName}";
                // Format Trait Properties based on allowed visibility
                foreach ($traitDto->traitProperties as $propertyInformation) {
                    if (in_array($propertyInformation->propertyVisibility, $this->allowedPropertyVisibilities, true)) {
                        $lines[] = $this->formatProperty($propertyInformation);
                    }
                }
                // Format Trait Methods based on allowed visibility
                foreach ($traitDto->traitMethods as $methodInformation) {
                    if (in_array($methodInformation->methodVisibility, $this->allowedMethodVisibilities, true)) {
                        $lines[] = $this->formatMethod($methodInformation);
                    }
                }
            }

            $lines[] = ''; // Add a blank line after each file's details
        }

        // Remove trailing blank line if present
        if (end($lines) === '') {
            array_pop($lines);
        }

        return implode("\n", $lines);
    }

    /**
     * Formats a method's details into a string.
     */
    private function formatMethod(CodemapMethodDto $methodInformation): string
    {
        $paramList = $this->formatParameters($methodInformation->methodParameters);

        return sprintf(
            '    %s function %s(%s): %s',
            $methodInformation->methodVisibility,
            $methodInformation->methodName,
            $paramList,
            $methodInformation->methodReturnType
        );
    }

    /**
     * Formats an array of method parameters into a comma-separated string.
     *
     * @param  CodemapParameterDto[]  $parameters
     */
    private function formatParameters(array $parameters): string
    {
        return implode(', ', array_map(fn (CodemapParameterDto $param) => $param->parameterType.' $'.$param->parameterName, $parameters));
    }

    /**
     * Formats a property's details into a string.
     */
    private function formatProperty(CodemapPropertyDto $propertyInformation): string
    {
        return sprintf(
            '    %s property %s $%s',
            $propertyInformation->propertyVisibility,
            $propertyInformation->propertyType,
            $propertyInformation->propertyName
        );
    }

    /**
     * Formats an enum case's details into a string.
     */
    private function formatEnumCase(?string $caseValue, int|string $caseName): string
    {
        return $caseValue === null ? "    case {$caseName}" : "    case {$caseName} = {$caseValue}";
    }
}
