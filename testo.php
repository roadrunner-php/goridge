<?php

declare(strict_types=1);

use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Codecov\CodecovPlugin;
use Testo\Codecov\Config\CoverageMode;
use Testo\Codecov\Report\CloverReport;

return new ApplicationConfig(
    src: ['src'],
    suites: [
        new SuiteConfig(
            name: 'Unit',
            location: ['tests/Goridge'],
        ),
    ],
    plugins: [
        new CodecovPlugin(
            collect: CoverageMode::Never,
            reports: [
                new CloverReport(__DIR__ . '/runtime/coverage/clover.xml', 'roadrunner/goridge'),
            ],
        ),
    ],
);
