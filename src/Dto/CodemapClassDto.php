<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Dto;

final readonly class CodemapClassDto
{
    /**
     * @param  CodemapMethodDto[]  $classMethods
     * @param  CodemapPropertyDto[]  $classProperties
     * @param  string[]  $usesTraits  Array of FQCNs of used traits
     * @param  string|null  $extendsClass  FQCN of the extended class
     * @param  string[]  $implementsInterfaces  Array of FQCNs of implemented interfaces
     */
    public function __construct(
        public array $classMethods = [],
        public array $classProperties = [],
        public array $usesTraits = [],
        public ?string $extendsClass = null,
        public array $implementsInterfaces = []
    ) {}
}
