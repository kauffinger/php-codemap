<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Dto;

/**
 * Represents a single method in the codemap.
 */
final readonly class CodemapMethodDto
{
    /**
     * @param  CodemapParameterDto[]  $methodParameters
     */
    public function __construct(
        public string $methodVisibility,
        public string $methodName,
        public string $methodReturnType,
        public array $methodParameters = []
    ) {}
}
