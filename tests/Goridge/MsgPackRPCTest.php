<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests;

use Spiral\Goridge\RPC\Codec\MsgpackCodec;
use Spiral\Goridge\RPC\Exception\ServiceException;
use Spiral\Goridge\RPC\RPC;
use Testo\Expect;
use Testo\Test;

#[Test]
final class MsgPackRPCTest extends \Spiral\Goridge\Tests\RPC
{
    /**
     * @throws \Exception
     */
    public function testJsonException(): void
    {
        Expect::exception(ServiceException::class);

        $conn = $this->makeRPC();

        $conn->call('Service.Process', random_bytes(256));
    }

    protected function makeRPC(): RPC
    {
        return (new RPC($this->makeRelay()))->withCodec(new MsgpackCodec());
    }
}
