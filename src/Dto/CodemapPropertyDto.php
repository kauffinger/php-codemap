<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Dto;

final class CodemapPropertyDto
{
    public function __construct(
        public string $visibility,
        public string $name
    ) {}
}
