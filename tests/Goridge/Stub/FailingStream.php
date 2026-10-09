<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests\Stub;

/**
 * Stream wrapper whose reads and writes fail on demand, to reach the I/O error paths of stream relays.
 *
 * Reads return the queued chunks one per call; a `false` chunk is a read error. Every write fails.
 * A non-null warning is raised on each failure, the way a real stream reports one.
 */
final class FailingStream
{
    public const PROTOCOL = 'goridge-failing';

    /** @var resource|null */
    public $context;

    /** @var list<string|false> */
    private static array $reads = [];

    private static ?string $warning = null;

    /**
     * @param list<string|false> $reads
     * @return resource
     */
    public static function open(array $reads = [], ?string $warning = null)
    {
        if (!\in_array(self::PROTOCOL, \stream_get_wrappers(), true)) {
            \stream_wrapper_register(self::PROTOCOL, self::class);
        }

        self::$reads = $reads;
        self::$warning = $warning;

        return \fopen(self::PROTOCOL . '://stream', 'r+');
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_read(int $count): string|false
    {
        $chunk = \array_shift(self::$reads) ?? '';
        if ($chunk === false) {
            $this->warn();
        }

        return $chunk;
    }

    public function stream_write(string $data): false
    {
        $this->warn();

        return false;
    }

    public function stream_eof(): bool
    {
        return self::$reads === [];
    }

    private function warn(): void
    {
        if (self::$warning !== null) {
            \trigger_error(self::$warning, \E_USER_WARNING);
        }
    }
}
