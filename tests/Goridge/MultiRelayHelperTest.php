<?php

namespace Goridge;

use PHPUnit\Framework\TestCase;
use Spiral\Goridge\MultiRelayHelper;
use Spiral\Goridge\StreamRelay;
use Spiral\Goridge\Tests\MultiRPC;

class MultiRelayHelperTest extends TestCase
{
    // Unfortunately a locally created stream is always "available" and will just return an empty string if no data is available.
    // Thus the test below could only work with a remote stream
    public function testSupportsStreamRelay(): void
    {
        $type = MultiRPC::SOCK_TYPE->value;
        $address = MultiRPC::SOCK_ADDR;
        $port = MultiRPC::SOCK_PORT;

        $in = stream_socket_client("$type://$address:$port");
        $this->assertTrue(stream_set_blocking($in, true));
        $this->assertFalse(feof($in));
        $relays = [new StreamRelay($in, STDOUT), new StreamRelay($in, STDERR)];
        // No message available on STDIN, aka a read would block, so this returns false
        $this->assertFalse(MultiRelayHelper::findRelayWithMessage($relays));
        fclose($in);
    }

    public function testSupportsReadingFromStreamRelay(): void
    {
        $stream = fopen('php://temp', 'rw+');
        fwrite($stream, 'Hello');
        fseek($stream, 0);
        $this->assertTrue(stream_set_blocking($stream, true));
        $this->assertFalse(feof($stream));
        $relays = [new StreamRelay($stream, STDOUT)];
        $this->assertCount(1, MultiRelayHelper::findRelayWithMessage($relays));
        fclose($stream);
    }
}
