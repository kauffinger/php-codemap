<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Dto;

final readonly class CodemapParameterDto
{
    public function __construct(
        public string $parameterName,
        public string $parameterType,
    ) {}
}
