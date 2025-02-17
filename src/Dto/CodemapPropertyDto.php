<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Dto;

final readonly class CodemapPropertyDto
{
    public function __construct(
        public string $propertyVisibility,
        public string $propertyName,
        public string $propertyType
    ) {}
}
