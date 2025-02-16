<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Dto;

final class CodemapFileDto
{
    /**
     * @param  array<string, CodemapClassDto>  $classes
     */
    public function __construct(
        /**
         * Map of fully qualified class names => CodemapClassDto
         */
        public array $classes = []
    ) {}
}
