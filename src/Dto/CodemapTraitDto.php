<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Dto;

/**
 * Represents a single trait in the codemap.
 */
final readonly class CodemapTraitDto
{
    /**
     * @param  CodemapMethodDto[]  $traitMethods
     * @param  CodemapPropertyDto[]  $traitProperties
     */
    public function __construct(
        public string $traitName,
        public array $traitMethods = [],
        public array $traitProperties = []
    ) {}
}
