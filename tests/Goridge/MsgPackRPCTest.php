<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests;

use Spiral\Goridge\Exception\TransportException;
use Spiral\Goridge\RPC\Codec\MsgpackCodec;
use Spiral\Goridge\RPC\RPC as GoridgeRPC;
use Spiral\Goridge\SocketRelay;
use Testo\Assert;
use Testo\Test;

/**
 * Goridge v4 refuses MessagePack frames, which is why {@see MsgpackCodec} is deprecated.
 */
#[Test]
final class MsgPackRPCTest
{
    public function testServerDropsConnectionOnMsgpackFrame(): void
    {
        $rpc = (new GoridgeRPC($this->makeRelay()))->withCodec(new MsgpackCodec());

        try {
            $rpc->call('Service.Ping', 'ping');
            Assert::fail('The server should have refused the MessagePack frame');
        } catch (TransportException $e) {
            Assert::notBlank($e->getMessage());
        }

        Assert::same((new GoridgeRPC($this->makeRelay()))->call('Service.Ping', 'ping'), 'pong');
    }

    private function makeRelay(): SocketRelay
    {
        return new SocketRelay(RPC::SOCK_ADDR, RPC::SOCK_PORT, RPC::SOCK_TYPE);
    }
}
