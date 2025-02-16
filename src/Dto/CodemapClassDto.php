<?php

declare(strict_types=1);

namespace Kauffinger\Codemap\Dto;

final class CodemapClassDto
{
    /**
     * @param  CodemapMethodDto[]  $methods
     * @param  CodemapPropertyDto[]  $properties
     */
    public function __construct(public array $methods = [], public array $properties = []) {}
}
