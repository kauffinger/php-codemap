<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Formatter;

use Kauffinger\Codemap\Dto\CodemapFileDto;

final class TextCodemapFormatter
{
    /**
     * @param  array<string, CodemapFileDto>  $codemapData
     */
    public function format(array $codemapData): string
    {
        $formattedOutput = '';
        foreach ($codemapData as $fileName => $fileData) {
            $formattedOutput .= "File: {$fileName}\n";

            foreach ($fileData->classesInFile as $className => $classInformation) {
                $formattedOutput .= "  Class: {$className}\n";

                foreach ($classInformation->classMethods as $methodInformation) {
                    $paramList = '';
                    foreach ($methodInformation->methodParameters as $param) {
                        if ($paramList !== '') {
                            $paramList .= ', ';
                        }
                        $paramList .= $param['parameterType'].' $'.$param['parameterName'];
                    }

                    $formattedOutput .= sprintf(
                        "    %s function %s(%s): %s\n",
                        $methodInformation->methodVisibility,
                        $methodInformation->methodName,
                        $paramList,
                        $methodInformation->methodReturnType
                    );
                }

                // Only show public properties
                foreach ($classInformation->classProperties as $propertyInformation) {
                    if ($propertyInformation->propertyVisibility === 'public') {
                        $formattedOutput .= sprintf(
                            "    %s property %s \$%s\n",
                            $propertyInformation->propertyVisibility,
                            $propertyInformation->propertyType,
                            $propertyInformation->propertyName
                        );
                    }
                }
            }

            $formattedOutput .= "\n";
        }

        return $formattedOutput;
    }
}
