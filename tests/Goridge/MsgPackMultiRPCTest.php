<?php

declare(strict_types=1);

namespace Goridge;

use Spiral\Goridge\RPC\Codec\MsgpackCodec;
use Spiral\Goridge\RPC\Exception\ServiceException;
use Testo\Assert\ExpectException;
use Testo\Expect;
use Testo\Test;

#[Test]
final class MsgPackMultiRPCTest extends \Spiral\Goridge\Tests\MultiRPC
{
    /**
     * @throws \Exception
     */
    #[ExpectException(ServiceException::class)]
    public function testJsonException(): void
    {
        $this->rpc->call('Service.Process', random_bytes(256));
    }

    public function testJsonExceptionAsync(): void
    {
        $id = $this->rpc->callAsync('Service.Process', random_bytes(256));
        Expect::exception(ServiceException::class);
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
