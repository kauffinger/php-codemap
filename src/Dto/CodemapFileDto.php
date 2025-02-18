<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Dto;

final readonly class CodemapFileDto
{
    /**
     * @param  array<string, CodemapClassDto>  $classesInFile  Map of FQCN => CodemapClassDto
     * @param  array<string, CodemapEnumDto>  $enumsInFile  Map of FQCN => CodemapEnumDto
     */
    public function __construct(
        public array $classesInFile = [],
        public array $enumsInFile = [],
    ) {}
}
