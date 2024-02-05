<?php

declare(strict_types=1);

namespace Goridge;

use PHPUnit\Framework\TestCase;
use Spiral\Goridge\RelayInterface;
use Spiral\Goridge\RPC\Codec\RawCodec;
use Spiral\Goridge\RPC\Exception\CodecException;
use Spiral\Goridge\RPC\Exception\RPCException;
use Spiral\Goridge\RPC\Exception\ServiceException;
use Spiral\Goridge\RPC\MultiRPC as GoridgeMultiRPC;
use Spiral\Goridge\SocketRelay;
use Spiral\Goridge\SocketType;

abstract class MultiRPC extends TestCase
{
    public const GO_APP = 'server';
    public const SOCK_ADDR = '127.0.0.1';
    public const SOCK_PORT = 7079;
    public const SOCK_TYPE = SocketType::TCP;

    public function testManualConnect(): void
    {
        $relays = [];
        for ($i = 0; $i < 10; $i++) {
            $relays[] = $this->makeRelay();
        }
        /** @var SocketRelay $relay */
        $relay = $relays[0];
        $conn = new GoridgeMultiRPC($relays);

        $this->assertFalse($relay->isConnected());

        $relay->connect();
        $this->assertTrue($relay->isConnected());

        $this->assertSame('pong', $conn->call('Service.Ping', 'ping'));
        $this->assertTrue($relay->isConnected());

        $conn->preConnectRelays();
        foreach ($relays as $relay) {
            $this->assertTrue($relay->isConnected());
        }
    }

    public function testReconnect(): void
    {
        /** @var SocketRelay $relay */
        $relay = $this->makeRelay();
        $conn = new GoridgeMultiRPC([$relay]);

        $this->assertFalse($relay->isConnected());

        $this->assertSame('pong', $conn->call('Service.Ping', 'ping'));
        $this->assertTrue($relay->isConnected());

        $relay->close();
        $this->assertFalse($relay->isConnected());

        $this->assertSame('pong', $conn->call('Service.Ping', 'ping'));
        $this->assertTrue($relay->isConnected());
    }

    public function testPingPong(): void
    {
        $conn = $this->makeRPC();
        $this->assertSame('pong', $conn->call('Service.Ping', 'ping'));
    }

    public function testPingPongAsync(): void
    {
        $conn = $this->makeRPC();
        $id = $conn->callAsync('Service.Ping', 'ping');
        $this->assertSame('pong', $conn->getResponse($id));
    }

    public function testPrefixPingPong(): void
    {
        $conn = $this->makeRPC()->withServicePrefix('Service');
        $this->assertSame('pong', $conn->call('Ping', 'ping'));
    }

    public function testPrefixPingPongAsync(): void
    {
        $conn = $this->makeRPC()->withServicePrefix('Service');
        $id = $conn->callAsync('Ping', 'ping');
        $this->assertSame('pong', $conn->getResponse($id));
    }

    public function testPingNull(): void
    {
        $conn = $this->makeRPC();
        $this->assertSame('', $conn->call('Service.Ping', 'not-ping'));
    }

    public function testPingNullAsync(): void
    {
        $conn = $this->makeRPC();
        $id = $conn->callAsync('Service.Ping', 'not-ping');
        $this->assertSame('', $conn->getResponse($id));
    }

    public function testNegate(): void
    {
        $conn = $this->makeRPC();
        $this->assertSame(-10, $conn->call('Service.Negate', 10));
    }

    public function testNegateAsync(): void
    {
        $conn = $this->makeRPC();
        $id = $conn->callAsync('Service.Negate', 10);
        $this->assertSame(-10, $conn->getResponse($id));
    }

    public function testNegateNegative(): void
    {
        $conn = $this->makeRPC();
        $this->assertSame(10, $conn->call('Service.Negate', -10));
    }

    public function testNegateNegativeAsync(): void
    {
        $conn = $this->makeRPC();
        $id = $conn->callAsync('Service.Negate', -10);
        $this->assertSame(10, $conn->getResponse($id));
    }

    public function testInvalidService(): void
    {
        $this->expectException(ServiceException::class);
        $conn = $this->makeRPC()->withServicePrefix('Service2');
        $this->assertSame('pong', $conn->call('Ping', 'ping'));
    }

    public function testInvalidServiceAsync(): void
    {
        $conn = $this->makeRPC()->withServicePrefix('Service2');
        $id = $conn->callAsync('Ping', 'ping');
        $this->expectException(ServiceException::class);
        $this->assertSame('pong', $conn->getResponse($id));
    }

    public function testInvalidMethod(): void
    {
        $this->expectException(ServiceException::class);
        $conn = $this->makeRPC()->withServicePrefix('Service');
        $this->assertSame('pong', $conn->call('Ping2', 'ping'));
    }

    public function testInvalidMethodAsync(): void
    {
        $conn = $this->makeRPC()->withServicePrefix('Service');
        $id = $conn->callAsync('Ping2', 'ping');
        $this->expectException(ServiceException::class);
        $this->assertSame('pong', $conn->getResponse($id));
    }

    public function testLongEcho(): void
    {
        $conn = $this->makeRPC();
        $payload = base64_encode(random_bytes(65000 * 5));

        $resp = $conn->call('Service.Echo', $payload);

        $this->assertSame(strlen($payload), strlen($resp));
        $this->assertSame(md5($payload), md5($resp));
    }

    public function testLongEchoAsync(): void
    {
        $conn = $this->makeRPC();
        $payload = base64_encode(random_bytes(65000 * 5));

        $id = $conn->callAsync('Service.Echo', $payload);
        $resp = $conn->getResponse($id);

        $this->assertSame(strlen($payload), strlen($resp));
        $this->assertSame(md5($payload), md5($resp));
    }

    public function testConvertException(): void
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('unknown Raw payload type');

        $conn = $this->makeRPC();
        $payload = base64_encode(random_bytes(65000 * 5));

        $resp = $conn->withCodec(new RawCodec())->call(
            'Service.Echo',
            $payload
        );

        $this->assertSame(strlen($payload), strlen($resp));
        $this->assertSame(md5($payload), md5($resp));
    }

    public function testConvertExceptionAsync(): void
    {
        $conn = $this->makeRPC();
        $payload = base64_encode(random_bytes(65000 * 5));

        $id = $conn->withCodec(new RawCodec())->callAsync(
            'Service.Echo',
            $payload
        );

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('unknown Raw payload type');

        $resp = $conn->getResponse($id);

        $this->assertSame(strlen($payload), strlen($resp));
        $this->assertSame(md5($payload), md5($resp));
    }


    public function testRawBody(): void
    {
        $conn = $this->makeRPC();
        $payload = random_bytes(100);

        $resp = $conn->withCodec(new RawCodec())->call(
            'Service.EchoBinary',
            $payload
        );

        $this->assertSame(strlen($payload), strlen($resp));
        $this->assertSame(md5($payload), md5($resp));
    }

    public function testRawBodyAsync(): void
    {
        $conn = $this->makeRPC();
        $payload = random_bytes(100);

        $id = $conn->withCodec(new RawCodec())->callAsync(
            'Service.EchoBinary',
            $payload
        );
        $resp = $conn->getResponse($id);

        $this->assertSame(strlen($payload), strlen($resp));
        $this->assertSame(md5($payload), md5($resp));
    }

    public function testLongRawBody(): void
    {
        $conn = $this->makeRPC();
        $payload = random_bytes(65000 * 1000);

        $resp = $conn->withCodec(new RawCodec())->call(
            'Service.EchoBinary',
            $payload
        );

        $this->assertSame(strlen($payload), strlen($resp));
        $this->assertSame(md5($payload), md5($resp));
    }

    public function testLongRawBodyAsync(): void
    {
        $conn = $this->makeRPC();
        $payload = random_bytes(65000 * 1000);

        $id = $conn->withCodec(new RawCodec())->callAsync(
            'Service.EchoBinary',
            $payload
        );
        $resp = $conn->getResponse($id);

        $this->assertSame(strlen($payload), strlen($resp));
        $this->assertSame(md5($payload), md5($resp));
    }

    public function testPayload(): void
    {
        $conn = $this->makeRPC();

        $resp = $conn->call(
            'Service.Process',
            [
                'Name' => 'wolfy-j',
                'Value' => 18
            ]
        );

        $this->assertSame(
            [
                'Name' => 'WOLFY-J',
                'Value' => -18,
                'Keys' => null
            ],
            $resp
        );
    }

    public function testPayloadAsync(): void
    {
        $conn = $this->makeRPC();

        $id = $conn->callAsync(
            'Service.Process',
            [
                'Name' => 'wolfy-j',
                'Value' => 18
            ]
        );
        $resp = $conn->getResponse($id);

        $this->assertSame(
            [
                'Name' => 'WOLFY-J',
                'Value' => -18,
                'Keys' => null
            ],
            $resp
        );
    }

    public function testBadPayload(): void
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('unknown Raw payload type');

        $conn = $this->makeRPC();
        $conn->withCodec(new RawCodec())->call('Service.Process', 'raw');
    }

    public function testBadPayloadAsync(): void
    {
        $conn = $this->makeRPC();
        $id = $conn->withCodec(new RawCodec())->callAsync('Service.Process', 'raw');

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('unknown Raw payload type');
        $resp = $conn->getResponse($id);
    }

    public function testPayloadWithMap(): void
    {
        $conn = $this->makeRPC();

        $resp = $conn->call(
            'Service.Process',
            [
                'Name' => 'wolfy-j',
                'Value' => 18,
                'Keys' => [
                    'Key' => 'value',
                    'Email' => 'domain'
                ]
            ]
        );

        $this->assertIsArray($resp['Keys']);
        $this->assertArrayHasKey('value', $resp['Keys']);
        $this->assertArrayHasKey('domain', $resp['Keys']);

        $this->assertSame('Key', $resp['Keys']['value']);
        $this->assertSame('Email', $resp['Keys']['domain']);
    }

    public function testPayloadWithMapAsync(): void
    {
        $conn = $this->makeRPC();

        $id = $conn->callAsync(
            'Service.Process',
            [
                'Name' => 'wolfy-j',
                'Value' => 18,
                'Keys' => [
                    'Key' => 'value',
                    'Email' => 'domain'
                ]
            ]
        );
        $resp = $conn->getResponse($id);

        $this->assertIsArray($resp['Keys']);
        $this->assertArrayHasKey('value', $resp['Keys']);
        $this->assertArrayHasKey('domain', $resp['Keys']);

        $this->assertSame('Key', $resp['Keys']['value']);
        $this->assertSame('Email', $resp['Keys']['domain']);
    }

    public function testBrokenPayloadMap(): void
    {
        $conn = $this->makeRPC();

        $id = $conn->callAsync(
            'Service.Process',
            [
                'Name' => 'wolfy-j',
                'Value' => 18,
                'Keys' => 1111
            ]
        );

        $this->expectException(ServiceException::class);
        $resp = $conn->getResponse($id);
    }

    public function testJsonException(): void
    {
        $this->expectException(CodecException::class);

        $conn = $this->makeRPC();

        $conn->call('Service.Process', random_bytes(256));
    }

    public function testJsonExceptionAsync(): void
    {
        $this->expectException(CodecException::class);

        $conn = $this->makeRPC();

        $conn->callAsync('Service.Process', random_bytes(256));
    }

    public function testJsonExceptionIgnoreResponse(): void
    {
        $this->expectException(CodecException::class);

        $conn = $this->makeRPC();

        $conn->callIgnoreResponse('Service.Process', random_bytes(256));
    }

    public function testSleepEcho(): void
    {
        $conn = $this->makeRPC();
        $time = hrtime(true);
        $this->assertSame('Hello', $conn->call('Service.SleepEcho', 'Hello'));
        // sleep is 100ms, so we check if we are further along than 100ms
        $this->assertGreaterThanOrEqual($time + (100 * 1e6), hrtime(true));
    }

    public function testSleepEchoAsync(): void
    {
        $conn = $this->makeRPC();
        $time = hrtime(true);
        $id = $conn->callAsync('Service.SleepEcho', 'Hello');
        // hrtime is in nanoseconds, and at most expect 100 microseconds (sleep is 100ms)
        $this->assertLessThanOrEqual($time + (100 * 1e3), hrtime(true));
        $this->assertFalse($conn->hasResponse($id));
        $this->assertSame('Hello', $conn->getResponse($id));
        // sleep is 100ms, so we check if we are further along than 100ms
        $this->assertGreaterThanOrEqual($time + (100 * 1e6), hrtime(true));
    }

    public function testSleepEchoIgnoreResponse(): void
    {
        $conn = $this->makeRPC();
        $time = hrtime(true);
        $conn->callIgnoreResponse('Service.SleepEcho', 'Hello');
        // hrtime is in nanoseconds, and at most expect 100 microseconds (sleep is 100ms)
        $this->assertLessThanOrEqual($time + (100 * 1e3), hrtime(true));
    }

    public function testCannotGetSameResponseTwice(): void
    {
        $conn = $this->makeRPC();
        $id = $conn->callAsync('Service.Ping', 'ping');
        $this->assertSame('pong', $conn->getResponse($id));
        $this->expectException(RPCException::class);
        $this->expectExceptionMessage('Invalid Seq, unknown');
    }

    /**
     * @return GoridgeMultiRPC
     */
    protected function makeRPC(): GoridgeMultiRPC
    {
        $relays = [];
        for ($i = 0; $i < 10; $i++) {
            $relays[] = $this->makeRelay();
        }
        return new GoridgeMultiRPC($relays);
    }

    /**
     * @return RelayInterface
     */
    protected function makeRelay(): RelayInterface
    {
        return new SocketRelay(static::SOCK_ADDR, static::SOCK_PORT, static::SOCK_TYPE);
    }
}
