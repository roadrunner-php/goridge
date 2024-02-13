<?php

namespace Goridge;

use PHPUnit\Framework\TestCase;
use Spiral\Goridge\MultiRelayHelper;
use Spiral\Goridge\StreamRelay;

class MultiRelayHelperTest extends TestCase
{
    public function testSupportsStreamRelay(): void
    {
        $relays = [new StreamRelay(STDIN, STDOUT), new StreamRelay(STDIN, STDERR)];
        // No message available on STDIN, aka a read would block, so this returns false
        $this->assertFalse(MultiRelayHelper::findRelayWithMessage($relays));
    }

    public function testSupportsReadingFromStreamRelay(): void
    {
        $stream = fopen('php://temp', 'rw+');
        fwrite($stream, 'Hello');
        fseek($stream, 0);
        $relays = [new StreamRelay($stream, STDOUT)];
        $this->assertCount(1, MultiRelayHelper::findRelayWithMessage($relays));
        fclose($stream);
    }
}
