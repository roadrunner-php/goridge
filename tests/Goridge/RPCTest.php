<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use Spiral\Goridge\Frame;
use Spiral\Goridge\Relay;
use Spiral\Goridge\RelayInterface;
use Spiral\Goridge\RPC\Codec\RawCodec;
use Spiral\Goridge\RPC\Exception\CodecException;
use Spiral\Goridge\RPC\Exception\ServiceException;
use Spiral\Goridge\RPC\RPC;
use Spiral\Goridge\SocketRelay;

abstract class RPCTest extends TestCase
{
    public const GO_APP    = 'server';
    public const SOCK_ADDR = '127.0.0.1';
    public const SOCK_PORT = 7079;
    public const SOCK_TYPE = SocketRelay::SOCK_TCP;

    public function testManualConnect(): void
    {
        /** @var SocketRelay $relay */
        $relay = $this->makeRelay();
        $conn = new RPC($relay);

        $this->assertFalse($relay->isConnected());

        $relay->connect();
        $this->assertTrue($relay->isConnected());

        $this->assertSame('pong', $conn->call('Service.Ping', 'ping'));
        $this->assertTrue($relay->isConnected());
    }

    public function testReconnect(): void
    {
        /** @var SocketRelay $relay */
        $relay = $this->makeRelay();
        $conn = new RPC($relay);

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

    public function testPrefixPingPong(): void
    {
        $conn = $this->makeRPC()->withServicePrefix('Service');
        $this->assertSame('pong', $conn->call('Ping', 'ping'));
    }

    public function testPingNull(): void
    {
        $conn = $this->makeRPC();
        $this->assertSame('', $conn->call('Service.Ping', 'not-ping'));
    }

    public function testNegate(): void
    {
        $conn = $this->makeRPC();
        $this->assertSame(-10, $conn->call('Service.Negate', 10));
    }

    public function testNegateNegative(): void
    {
        $conn = $this->makeRPC();
        $this->assertSame(10, $conn->call('Service.Negate', -10));
    }

    public function testInvalidService(): void
    {
        $this->expectException(ServiceException::class);
        $conn = $this->makeRPC()->withServicePrefix('Service2');
        $this->assertSame('pong', $conn->call('Ping', 'ping'));
    }

    public function testInvalidMethod(): void
    {
        $this->expectException(ServiceException::class);
        $conn = $this->makeRPC()->withServicePrefix('Service');
        $this->assertSame('pong', $conn->call('Ping2', 'ping'));
    }

    /**
     * @throws Exception
     */
    public function testLongEcho(): void
    {
        $conn = $this->makeRPC();
        $payload = base64_encode(random_bytes(65000 * 5));

        $resp = $conn->call('Service.Echo', $payload);

        $this->assertSame(strlen($payload), strlen($resp));
        $this->assertSame(md5($payload), md5($resp));
    }

    /**
     * @throws Exception
     */
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

    /**
     * @throws Exception
     */
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

    /**
     * @throws Exception
     */
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

    public function testPayload(): void
    {
        $conn = $this->makeRPC();

        $resp = $conn->call(
            'Service.Process',
            [
                'Name'  => 'wolfy-j',
                'Value' => 18
            ]
        );

        $this->assertSame(
            [
                'Name'  => 'WOLFY-J',
                'Value' => -18,
                'Keys'  => null
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

    public function testPayloadWithMap(): void
    {
        $conn = $this->makeRPC();

        $resp = $conn->call(
            'Service.Process',
            [
                'Name'  => 'wolfy-j',
                'Value' => 18,
                'Keys'  => [
                    'Key'   => 'value',
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

    public function testBrokenPayloadMap(): void
    {
        $this->expectException(ServiceException::class);

        $conn = $this->makeRPC();

        $conn->call(
            'Service.Process',
            [
                'Name'  => 'wolfy-j',
                'Value' => 18,
                'Keys'  => 1111
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function testJsonException(): void
    {
        $this->expectException(CodecException::class);

        $conn = $this->makeRPC();

        $conn->call('Service.Process', random_bytes(256));
    }

    public function testCallSequence(): void
    {
        $relay1 = $this->getMockBuilder(Relay::class)->onlyMethods(['waitFrame', 'send'])->getMock();
        $relay1
            ->method('waitFrame')
            ->willReturnCallback(function () {
                static $series = [
                    [new Frame('Service.Process{}', [1, 15])],
                    [new Frame('Service.Process{}', [2, 15])],
                    [new Frame('Service.Process{}', [3, 15])],
                ];

                [$return] = \array_shift($series);

                return $return;
        });
        $relay1
            ->method('send')
            ->willReturnCallback(function (Frame $frame) {
                static $series = [
                    [new Frame('Service.Process{"Name":"foo","Value":18}', [1, 15], 8)],
                    [new Frame('Service.Process{"Name":"foo","Value":18}', [2, 15], 8)],
                    [new Frame('Service.Process{"Name":"foo","Value":18}', [3, 15], 8)],
                ];

                [$expectedArgs] = \array_shift($series);
                self::assertEquals($expectedArgs, $frame);
            });

        $relay2 = $this->getMockBuilder(Relay::class)->onlyMethods(['waitFrame', 'send'])->getMock();
        $relay2
            ->method('waitFrame')
            ->willReturnCallback(function () {
                static $series = [
                    [new Frame('Service.Process{}', [1, 15])],
                    [new Frame('Service.Process{}', [2, 15])],
                    [new Frame('Service.Process{}', [3, 15])],
                ];

                [$return] = \array_shift($series);

                return $return;
            });
        $relay2
            ->method('send')
            ->willReturnCallback(function (Frame $frame) {
                static $series = [
                    [new Frame('Service.Process{"Name":"bar","Value":18}', [1, 15], 8)],
                    [new Frame('Service.Process{"Name":"bar","Value":18}', [2, 15], 8)],
                    [new Frame('Service.Process{"Name":"bar","Value":18}', [3, 15], 8)],
                ];

                [$expectedArgs] = \array_shift($series);
                self::assertEquals($expectedArgs, $frame);
            });

        $conn1 = new RPC($relay1);
        $conn2 = new RPC($relay2);

        $conn1->call('Service.Process', ['Name'  => 'foo', 'Value' => 18]);
        $conn2->call('Service.Process', ['Name'  => 'bar', 'Value' => 18]);
        $conn1->call('Service.Process', ['Name'  => 'foo', 'Value' => 18]);
        $conn2->call('Service.Process', ['Name'  => 'bar', 'Value' => 18]);
        $conn1->call('Service.Process', ['Name'  => 'foo', 'Value' => 18]);
        $conn2->call('Service.Process', ['Name'  => 'bar', 'Value' => 18]);
    }

    /**
     * @return RPC
     */
    protected function makeRPC(): RPC
    {
        return new RPC($this->makeRelay());
    }

    /**
     * @return RelayInterface
     */
    protected function makeRelay(): RelayInterface
    {
        return new SocketRelay(static::SOCK_ADDR, static::SOCK_PORT, static::SOCK_TYPE);
    }
}
