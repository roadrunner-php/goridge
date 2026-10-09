<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests\Support;

use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;

/**
 * Calls the given test case method after each test and fails the test when it throws.
 *
 * Exists because a throwing {@see \Testo\Lifecycle\AfterTest} hook does not change the test result:
 * its failure only goes to stderr.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
#[FallbackInterceptor(AfterEachTestInterceptor::class)]
final readonly class AfterEachTest implements Interceptable
{
    public function __construct(
        public string $method,
    ) {}
}
