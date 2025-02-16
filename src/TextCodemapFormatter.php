<?php

declare(strict_types=1);

namespace Kauffinger\Codemap;

final class TextCodemapFormatter
{
    /**
     * Formats the codemap results into a string with classes, methods, and public properties.
     */
    public function format(array $results): string
    {
        $output = '';
        foreach ($results as $fileName => $data) {
            $output .= "File: {$fileName}\n";

            foreach ($data['classes'] as $className => $classData) {
                $output .= "  Class: {$className}\n";

                foreach ($classData['methods'] as $method) {
                    $output .= sprintf(
                        "    %s function %s(): %s\n",
                        $method['visibility'],
                        $method['name'],
                        $method['returnType']
                    );
                }

                // Only show public properties
                foreach ($classData['properties'] as $property) {
                    if ($property['visibility'] === 'public') {
                        $output .= sprintf(
                            "    %s property \$%s\n",
                            $property['visibility'],
                            $property['name']
                        );
                    }
                }
            }

            $output .= "\n";
        }

        return $output;
    }
}
