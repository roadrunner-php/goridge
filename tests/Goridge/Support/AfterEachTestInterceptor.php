<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests\Support;

use Testo\Assert\State\Record;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;

/**
 * @see AfterEachTest
 */
# Inside the assertion collector, so the check is recorded in the test's assertion history,
# and outside the expectations handler, so an expected exception has already been accounted for.
#[InterceptorOptions(order: InterceptorOptions::ORDER_ASSERTIONS)]
final readonly class AfterEachTestInterceptor implements TestRunInterceptor
{
    public function __construct(
        private AfterEachTest $options,
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        /** @var TestResult $result */
        $result = $next($info);

        $instance = $info->caseInfo->instance;
        if ($instance === null || !$instance->hasInstance()) {
            return $result;
        }

        $method = $this->options->method;
        try {
            (fn() => $this->{$method}())->call($instance->getInstance());
        } catch (\Throwable $e) {
            return $result->status === Status::Passed || $result->status === Status::Risky
                ? $result->with(status: $e instanceof Record ? Status::Failed : Status::Error)->withFailure($e)
                : $result;
        }

        return $result;
    }
}
