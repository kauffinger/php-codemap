<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Dto;

final readonly class CodemapClassDto
{
    /**
     * @param  CodemapMethodDto[]  $classMethods
     * @param  CodemapPropertyDto[]  $classProperties
     */
    public function __construct(
        public array $classMethods = [],
        public array $classProperties = []
    ) {}
}
