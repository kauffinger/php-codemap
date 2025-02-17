<?php

declare(strict_types=1);

use Kauffinger\Codemap\Config\CodemapConfig;
use Kauffinger\Codemap\Enum\PhpVersion;

return CodemapConfig::configure()
    ->withScanPaths([
        __DIR__.'/src',
    ])
    ->withPhpVersion(PhpVersion::PHP_8_3);
