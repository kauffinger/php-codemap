<?php

declare(strict_types=1);

use Kauffinger\Codemap\CodemapConfig;
use Kauffinger\Codemap\Enum\PhpVersion;

return CodemapConfig::configure()
    ->withPaths([
        __DIR__.'/src',
    ])
    ->withPhpVersion(PhpVersion::PHP_8_3);
