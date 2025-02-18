<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Dto;

final class CodemapEnumDto
{
    /**
     * @param  array<string, string|null>  $cases  Array of caseName => caseValue|null
     */
    public function __construct(
        public string $enumName,
        public ?string $backingType = null,
        public array $cases = []
    ) {}
}
