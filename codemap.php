<?php

declare(strict_types=1);

use Kauffinger\Codemap\Config\CodemapConfig;
use Kauffinger\Codemap\Enum\PhpVersion;

return CodemapConfig::configure()
    ->withScanPaths([
        __DIR__.'/src',
    ])
    ->withExcludePaths([
        'vendor', // Example: exclude vendor directory
        // 'tests/Fixtures', // Example: exclude test fixtures
    ])
    ->withPhpVersion(PhpVersion::PHP_8_3);
