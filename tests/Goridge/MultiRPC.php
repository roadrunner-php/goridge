<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
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
    private GoridgeMultiRPC $rpc;

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

        $this->assertFreeRelaysCorrectNumber($conn);
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
        $this->assertSame('pong', $this->rpc->call('Service.Ping', 'ping'));
    }

    public function testPingPongAsync(): void
    {
        $id = $this->rpc->callAsync('Service.Ping', 'ping');
        $this->assertSame('pong', $this->rpc->getResponse($id));
    }

    public function testPrefixPingPong(): void
    {
        $this->rpc = $this->makeRPC()->withServicePrefix('Service');
        $this->assertSame('pong', $this->rpc->call('Ping', 'ping'));
    }

    public function testPrefixPingPongAsync(): void
    {
        $this->rpc = $this->makeRPC()->withServicePrefix('Service');
        $id = $this->rpc->callAsync('Ping', 'ping');
        $this->assertSame('pong', $this->rpc->getResponse($id));
    }

    public function testPingNull(): void
    {
        $this->assertSame('', $this->rpc->call('Service.Ping', 'not-ping'));
    }

    public function testPingNullAsync(): void
    {
        $id = $this->rpc->callAsync('Service.Ping', 'not-ping');
        $this->assertSame('', $this->rpc->getResponse($id));
    }

    public function testNegate(): void
    {
        $this->assertSame(-10, $this->rpc->call('Service.Negate', 10));
    }

    public function testNegateAsync(): void
    {
        $id = $this->rpc->callAsync('Service.Negate', 10);
        $this->assertSame(-10, $this->rpc->getResponse($id));
    }

    public function testNegateNegative(): void
    {
        $this->assertSame(10, $this->rpc->call('Service.Negate', -10));
    }

    public function testNegateNegativeAsync(): void
    {
        $id = $this->rpc->callAsync('Service.Negate', -10);
        $this->assertSame(10, $this->rpc->getResponse($id));
    }

    public function testInvalidService(): void
    {
        $this->expectException(ServiceException::class);
        $this->rpc = $this->makeRPC()->withServicePrefix('Service2');
        $this->assertSame('pong', $this->rpc->call('Ping', 'ping'));
    }

    public function testInvalidServiceAsync(): void
    {
        $this->rpc = $this->makeRPC()->withServicePrefix('Service2');
        $id = $this->rpc->callAsync('Ping', 'ping');
        $this->expectException(ServiceException::class);
        $this->assertSame('pong', $this->rpc->getResponse($id));
    }

    public function testInvalidMethod(): void
    {
        $this->expectException(ServiceException::class);
        $this->rpc = $this->makeRPC()->withServicePrefix('Service');
        $this->assertSame('pong', $this->rpc->call('Ping2', 'ping'));
    }

    public function testInvalidMethodAsync(): void
    {
        $this->rpc = $this->makeRPC()->withServicePrefix('Service');
        $id = $this->rpc->callAsync('Ping2', 'ping');
        $this->expectException(ServiceException::class);
        $this->assertSame('pong', $this->rpc->getResponse($id));
    }

    public function testLongEcho(): void
    {
        $payload = base64_encode(random_bytes(65000 * 5));

        $resp = $this->rpc->call('Service.Echo', $payload);

        $this->assertSame(strlen($payload), strlen($resp));
        $this->assertSame(md5($payload), md5($resp));
    }

    public function testLongEchoAsync(): void
    {
        $payload = base64_encode(random_bytes(65000 * 5));

        $id = $this->rpc->callAsync('Service.Echo', $payload);
        $resp = $this->rpc->getResponse($id);

        $this->assertSame(strlen($payload), strlen($resp));
        $this->assertSame(md5($payload), md5($resp));
    }

    public function testConvertException(): void
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('unknown Raw payload type');

        $payload = base64_encode(random_bytes(65000 * 5));

        $resp = $this->rpc->withCodec(new RawCodec())->call(
            'Service.Echo',
            $payload
        );

        $this->assertSame(strlen($payload), strlen($resp));
        $this->assertSame(md5($payload), md5($resp));
    }

    public function testConvertExceptionAsync(): void
    {
        $payload = base64_encode(random_bytes(65000 * 5));

        $this->rpc = $this->rpc->withCodec(new RawCodec());
        $id = $this->rpc->callAsync(
            'Service.Echo',
            $payload
        );

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('unknown Raw payload type');

        $resp = $this->rpc->getResponse($id);

        $this->assertSame(strlen($payload), strlen($resp));
        $this->assertSame(md5($payload), md5($resp));
    }

    public function testRawBody(): void
    {
        $payload = random_bytes(100);

        $resp = $this->rpc->withCodec(new RawCodec())->call(
            'Service.EchoBinary',
            $payload
        );

        $this->assertSame(strlen($payload), strlen($resp));
        $this->assertSame(md5($payload), md5($resp));
    }

    public function testRawBodyAsync(): void
    {
        $payload = random_bytes(100);

        $this->rpc = $this->rpc->withCodec(new RawCodec());
        $id = $this->rpc->callAsync(
            'Service.EchoBinary',
            $payload
        );
        $resp = $this->rpc->getResponse($id);

        $this->assertSame(strlen($payload), strlen($resp));
        $this->assertSame(md5($payload), md5($resp));
    }

    public function testLongRawBody(): void
    {
        $payload = random_bytes(65000 * 1000);

        $resp = $this->rpc->withCodec(new RawCodec())->call(
            'Service.EchoBinary',
            $payload
        );

        $this->assertSame(strlen($payload), strlen($resp));
        $this->assertSame(md5($payload), md5($resp));
    }

    public function testLongRawBodyAsync(): void
    {
        $payload = random_bytes(65000 * 1000);

        $this->rpc = $this->rpc->withCodec(new RawCodec());
        $id = $this->rpc->callAsync(
            'Service.EchoBinary',
            $payload
        );
        $resp = $this->rpc->getResponse($id);

        $this->assertSame(strlen($payload), strlen($resp));
        $this->assertSame(md5($payload), md5($resp));
    }

    public function testPayload(): void
    {
        $resp = $this->rpc->call(
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
        $id = $this->rpc->callAsync(
            'Service.Process',
            [
                'Name' => 'wolfy-j',
                'Value' => 18
            ]
        );
        $resp = $this->rpc->getResponse($id);

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

        $this->rpc->withCodec(new RawCodec())->call('Service.Process', 'raw');
    }

    public function testBadPayloadAsync(): void
    {
        $this->rpc = $this->rpc->withCodec(new RawCodec());
        $id = $this->rpc->callAsync('Service.Process', 'raw');

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('unknown Raw payload type');
        $resp = $this->rpc->getResponse($id);
    }

    public function testPayloadWithMap(): void
    {
        $resp = $this->rpc->call(
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
        $id = $this->rpc->callAsync(
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
        $resp = $this->rpc->getResponse($id);

        $this->assertIsArray($resp['Keys']);
        $this->assertArrayHasKey('value', $resp['Keys']);
        $this->assertArrayHasKey('domain', $resp['Keys']);

        $this->assertSame('Key', $resp['Keys']['value']);
        $this->assertSame('Email', $resp['Keys']['domain']);
    }

    public function testBrokenPayloadMap(): void
    {
        $id = $this->rpc->callAsync(
            'Service.Process',
            [
                'Name' => 'wolfy-j',
                'Value' => 18,
                'Keys' => 1111
            ]
        );

        $this->expectException(ServiceException::class);
        $resp = $this->rpc->getResponse($id);
    }

    public function testJsonException(): void
    {
        $this->expectException(CodecException::class);

        $this->rpc->call('Service.Process', random_bytes(256));
    }

    public function testJsonExceptionAsync(): void
    {
        $this->expectException(CodecException::class);
        $id = $this->rpc->callAsync('Service.Process', random_bytes(256));
    }

    public function testJsonExceptionNotThrownWithIgnoreResponse(): void
    {
        $this->expectException(CodecException::class);
        $this->rpc->callIgnoreResponse('Service.Process', random_bytes(256));
    }

    public function testSleepEcho(): void
    {
        $time = hrtime(true) / 1e9;
        $this->assertSame('Hello', $this->rpc->call('Service.SleepEcho', 'Hello'));
        // sleep is 100ms, so we check if we are further along than 100ms
        $this->assertGreaterThanOrEqual($time + 0.1, hrtime(true) / 1e9);
    }

    public function testSleepEchoAsync(): void
    {
        $time = hrtime(true) / 1e9;
        $id = $this->rpc->callAsync('Service.SleepEcho', 'Hello');
        // hrtime is in nanoseconds, and at most expect 1ms delay (sleep is 100ms)
        $this->assertLessThanOrEqual($time + 0.001, hrtime(true) / 1e9);
        $this->assertFalse($this->rpc->hasResponse($id));
        $this->assertSame('Hello', $this->rpc->getResponse($id));
        // sleep is 100ms, so we check if we are further along than 100ms
        $this->assertGreaterThanOrEqual($time + 0.1, hrtime(true) / 1e9);
    }

    public function testSleepEchoIgnoreResponse(): void
    {
        $time = hrtime(true) / 1e9;
        $this->rpc->callIgnoreResponse('Service.SleepEcho', 'Hello');
        // hrtime is in nanoseconds, and at most expect 1ms delay (sleep is 100ms)
        $this->assertLessThanOrEqual($time + 0.001, hrtime(true) / 1e9);
        // Wait for response
        usleep(100_000);

        $this->forceFlushRpc($this->rpc);
    }

    public function testCannotGetSameResponseTwice(): void
    {
        $id = $this->rpc->callAsync('Service.Ping', 'ping');
        $this->assertSame('pong', $this->rpc->getResponse($id));
        $this->assertFreeRelaysCorrectNumber($this->rpc);
        $this->expectException(RPCException::class);
        $this->expectExceptionMessage('Invalid Seq, unknown');
        $this->assertSame('pong', $this->rpc->getResponse($id));
    }

    public function testCanCallMoreTimesThanRelays(): void
    {
        $ids = [];

        for ($i = 0; $i < 50; $i++) {
            $ids[] = $this->rpc->callAsync('Service.Ping', 'ping');
        }

        foreach ($this->rpc->getResponses($ids) as $response) {
            $this->assertSame('pong', $response);
        }
    }

//    public function testCanCallMoreTimesThanBufferAndNotGetResponses(): void
//    {
//        $ids = [];
//
//        for ($i = 0; $i < 1_000_050; $i++) {
//            $ids[] = $this->rpc->callAsync('Service.Ping', 'ping');
//        }
//
//        foreach ($ids as $id) {
//            try {
//                $this->assertSame('pong', $this->rpc->getResponse($id));
//                $this->assertGreaterThanOrEqual(1_000_000, $id);
//            } catch (RPCException $exception) {
//                $this->assertLessThan(1_000_000, $id);
//            }
//        }
//
//        $this->assertFreeRelaysCorrectNumber($this->rpc);
//    }

//    public function testCanCallMoreTimesThanRelaysWithIntermittentResponseHandling(): void
//    {
//        $ids = [];
//
//        for ($i = 0; $i < 1_000_050; $i++) {
//            if ($i === 500_000) {
//                foreach ($this->rpc->getResponses($ids) as $response) {
//                    $this->assertSame('pong', $response);
//                }
//                $ids = [];
//            }
//            $ids[] = $this->rpc->callAsync('Service.Ping', 'ping');
//        }
//
//        foreach ($this->rpc->getResponses($ids) as $response) {
//            $this->assertSame('pong', $response);
//        }
//
//        $this->assertFreeRelaysCorrectNumber($this->rpc);
//    }

    protected function setUp(): void
    {
        $this->rpc = $this->makeRPC();
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

    protected function tearDown(): void
    {
        $this->assertFreeRelaysCorrectNumber($this->rpc);
    }

    protected function assertFreeRelaysCorrectNumber(GoridgeMultiRPC $rpc): void
    {
        $property = new ReflectionProperty(GoridgeMultiRPC::class, 'freeRelays');
        $count = count($property->getValue($rpc));
        $numberOfWaitingResponses = 0;
        $numberOfOccupiedRelays = 0;

        // Aid in debugging
        if ($count !== 10) {
            $property = new ReflectionProperty(GoridgeMultiRPC::class, 'seqToRelayMap');
            $numberOfWaitingResponses = count($property->getValue($rpc));
            $property = new ReflectionProperty(GoridgeMultiRPC::class, 'occupiedRelays');
            $numberOfOccupiedRelays = count($property->getValue($rpc));
        }

        $this->assertSame(10, $count, "RPC has lost at least one relay! Waiting Responses: $numberOfWaitingResponses, Occupied Relays: $numberOfOccupiedRelays");
    }

    protected function forceFlushRpc(GoridgeMultiRPC $rpc): void
    {
        // Force consuming relay by flooding requests
        $ids = [];
        for ($i = 0; $i < 50; $i++) {
            $ids[] = $rpc->callAsync('Service.Ping', 'ping');
        }
        foreach ($rpc->getResponses($ids) as $id => $response) {
            $this->assertSame('pong', $response);
            array_splice($ids, array_search($id, $ids, true), 1);
        }
        $this->assertEmpty($ids);
    }
}
