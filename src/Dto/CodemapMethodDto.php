<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Dto;

final readonly class CodemapMethodDto
{
    public function __construct(
        public string $visibility,
        public string $name,
        public string $returnType
    ) {}
}
