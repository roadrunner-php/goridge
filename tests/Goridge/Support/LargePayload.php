<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests\Support;

use Spiral\Goridge\RPC\Exception\ServiceException;

/**
 * Keeps a large payload out of a test failure.
 *
 * A {@see ServiceException} quotes the whole response body, so a call that echoes tens of megabytes
 * and gets its response treated as an error fails with a message of that size. Infection keeps every
 * mutant's test output in memory for the Stryker report, and a handful of such failures take gigabytes.
 */
final class LargePayload
{
    private const MESSAGE_LIMIT = 512;

    /**
     * Runs the call and rethrows its {@see ServiceException} with the message cut to a readable prefix.
     *
     * @template T
     * @param callable(): T $call
     * @return T
     * @throws ServiceException
     */
    public static function guard(callable $call): mixed
    {
        try {
            return $call();
        } catch (ServiceException $e) {
            $message = $e->getMessage();
            if (\strlen($message) <= self::MESSAGE_LIMIT) {
                throw $e;
            }

            # No `previous`: the failure report prints the message of every exception in the chain.
            throw new ServiceException(\sprintf(
                '%s... [%d bytes, thrown at %s:%d]',
                \substr($message, 0, self::MESSAGE_LIMIT),
                \strlen($message),
                $e->getFile(),
                $e->getLine(),
            ), $e->getCode());
        }
    }
}
