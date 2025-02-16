<?php

declare(strict_types=1);

namespace Kauffinger\Codemap;

use Kauffinger\Codemap\Dto\CodemapClassDto;
use Kauffinger\Codemap\Dto\CodemapFileDto;
use Kauffinger\Codemap\Dto\CodemapMethodDto;
use Kauffinger\Codemap\Dto\CodemapPropertyDto;

final class TextCodemapFormatter
{
    /**
     * @param  array<string, CodemapFileDto>  $results
     */
    public function format(array $results): string
    {
        $output = '';
        foreach ($results as $fileName => $fileDto) {
            $output .= "File: {$fileName}\n";

            // $fileDto->classes is an array<string, CodemapClassDto>
            foreach ($fileDto->classes as $className => $classDto) {
                $output .= "  Class: {$className}\n";

                // $classDto->methods is CodemapMethodDto[]
                foreach ($classDto->methods as $method) {
                    $output .= sprintf(
                        "    %s function %s(): %s\n",
                        $method->visibility,
                        $method->name,
                        $method->returnType
                    );
                }

                // $classDto->properties is CodemapPropertyDto[]
                // Only show public properties
                foreach ($classDto->properties as $property) {
                    if ($property->visibility === 'public') {
                        $output .= sprintf(
                            "    %s property %s \$%s\n",
                            $property->visibility,
                            $property->type,
                            $property->name
                        );
                    }
                }
            }

            $output .= "\n";
        }

        return $output;
    }
}
