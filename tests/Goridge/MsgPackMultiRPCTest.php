<?php

declare(strict_types=1);

namespace Goridge;

use Exception;
use Spiral\Goridge\RPC\Codec\MsgpackCodec;
use Spiral\Goridge\RPC\Exception\ServiceException;

class MsgPackMultiRPCTest extends \Spiral\Goridge\Tests\MultiRPC
{
    /**
     * @throws Exception
     */
    public function testJsonException(): void
    {
        $this->expectException(ServiceException::class);
        $this->rpc->call('Service.Process', random_bytes(256));
    }

    public function testJsonExceptionAsync(): void
    {
        $id = $this->rpc->callAsync('Service.Process', random_bytes(256));
        $this->expectException(ServiceException::class);
        $this->rpc->getResponse($id);
    }

    public function testJsonExceptionNotThrownWithIgnoreResponse(): void
    {
        $this->rpc->callIgnoreResponse('Service.Process', random_bytes(256));
        $this->forceFlushRpc();
    }

    protected function makeRPC(int $count = 10): void
    {
        parent::makeRPC($count);
        $this->rpc = $this->rpc->withCodec(new MsgpackCodec());
    }
}
