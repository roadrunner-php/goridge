<?php

/**
 * Dead simple, high performance, drop-in bridge to Golang RPC with zero dependencies
 *
 * @author Wolfy-J
 */

declare(strict_types=1);

namespace Spiral\Goridge\Tests;

use Spiral\Goridge\RPC\Codec\MsgpackCodec;
use Spiral\Goridge\RPC\Exception\CodecException;
use Spiral\Goridge\RPC\Exception\ServiceException;
use Spiral\Goridge\RPC\RPC;

class MsgPackRPCTest extends RPCTest
{
    /**
     * @throws \Exception
     */
    public function testJsonException(): void
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches('#.*msgpack: invalid.*#');

        $conn = $this->makeRPC();

        $conn->call('Service.Process', random_bytes(256));
    }

    /**
     * @return RPC
     */
    protected function makeRPC(): RPC
    {
        return (new RPC($this->makeRelay()))->withCodec(new MsgpackCodec());
    }
}
