<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Formatter;

use Kauffinger\Codemap\Dto\CodemapFileDto;

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

            foreach ($fileDto->classes as $className => $classDto) {
                $output .= "  Class: {$className}\n";

                foreach ($classDto->methods as $method) {
                    $output .= sprintf(
                        "    %s function %s(): %s\n",
                        $method->visibility,
                        $method->name,
                        $method->returnType
                    );
                }

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
