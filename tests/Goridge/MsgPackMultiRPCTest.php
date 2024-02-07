<?php

declare(strict_types=1);

namespace Goridge;

use Exception;
use Spiral\Goridge\RPC\Codec\MsgpackCodec;
use Spiral\Goridge\RPC\Exception\ServiceException;
use Spiral\Goridge\RPC\MultiRPC;

class MsgPackMultiRPCTest extends \Spiral\Goridge\Tests\MultiRPC
{
    /**
     * @throws Exception
     */
    public function testJsonException(): void
    {
        $this->expectException(ServiceException::class);

        $conn = $this->makeRPC();

        $conn->call('Service.Process', random_bytes(256));
    }

    public function testJsonExceptionAsync(): void
    {
        $conn = $this->makeRPC();
        $id = $conn->callAsync('Service.Process', random_bytes(256));
        $this->expectException(ServiceException::class);
        $conn->getResponse($id);
    }

    public function testJsonExceptionNotThrownWithIgnoreResponse(): void
    {
        $conn = $this->makeRPC();
        $conn->callIgnoreResponse('Service.Process', random_bytes(256));

        $this->forceFlushRpc($conn);
    }


    /**
     * @return MultiRPC
     */
    protected function makeRPC(): MultiRPC
    {
        return parent::makeRPC()->withCodec(new MsgpackCodec());
    }
}
