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
}
