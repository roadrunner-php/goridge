<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests;

use Exception;
use Spiral\Goridge\RelayInterface;
use Spiral\Goridge\RPC\Codec\RawCodec;
use Spiral\Goridge\RPC\Exception\CodecException;
use Spiral\Goridge\RPC\Exception\ServiceException;
use Spiral\Goridge\RPC\RPC as GoridgeRPC;
use Spiral\Goridge\SocketRelay;
use Spiral\Goridge\SocketType;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

abstract class RPC
{
    public const GO_APP    = 'server';
    public const SOCK_ADDR = '127.0.0.1';
    public const SOCK_PORT = 7079;
    public const SOCK_TYPE = SocketType::TCP;

    #[Test]
    public function testManualConnect(): void
    {
        /** @var SocketRelay $relay */
        $relay = $this->makeRelay();
        $conn = new GoridgeRPC($relay);

        Assert::false($relay->isConnected());

        $relay->connect();
        Assert::true($relay->isConnected());

        Assert::same($conn->call('Service.Ping', 'ping'), 'pong');
        Assert::true($relay->isConnected());
    }

    #[Test]
    public function testReconnect(): void
    {
        /** @var SocketRelay $relay */
        $relay = $this->makeRelay();
        $conn = new GoridgeRPC($relay);

        Assert::false($relay->isConnected());

        Assert::same($conn->call('Service.Ping', 'ping'), 'pong');
        Assert::true($relay->isConnected());

        $relay->close();
        Assert::false($relay->isConnected());

        Assert::same($conn->call('Service.Ping', 'ping'), 'pong');
        Assert::true($relay->isConnected());
    }

    #[Test]
    public function testPingPong(): void
    {
        $conn = $this->makeRPC();
        Assert::same($conn->call('Service.Ping', 'ping'), 'pong');
    }

    #[Test]
    public function testPrefixPingPong(): void
    {
        $conn = $this->makeRPC()->withServicePrefix('Service');
        Assert::same($conn->call('Ping', 'ping'), 'pong');
    }

    #[Test]
    public function testPingNull(): void
    {
        $conn = $this->makeRPC();
        Assert::same($conn->call('Service.Ping', 'not-ping'), '');
    }

    #[Test]
    public function testNegate(): void
    {
        $conn = $this->makeRPC();
        Assert::same($conn->call('Service.Negate', 10), -10);
    }

    #[Test]
    public function testNegateNegative(): void
    {
        $conn = $this->makeRPC();
        Assert::same($conn->call('Service.Negate', -10), 10);
    }

    #[Test]
    public function testInvalidService(): void
    {
        Expect::exception(ServiceException::class);
        $conn = $this->makeRPC()->withServicePrefix('Service2');
        Assert::same($conn->call('Ping', 'ping'), 'pong');
    }

    #[Test]
    public function testInvalidMethod(): void
    {
        Expect::exception(ServiceException::class);
        $conn = $this->makeRPC()->withServicePrefix('Service');
        Assert::same($conn->call('Ping2', 'ping'), 'pong');
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testLongEcho(): void
    {
        $conn = $this->makeRPC();
        $payload = base64_encode(random_bytes(65000 * 5));

        $resp = $conn->call('Service.Echo', $payload);

        Assert::same(strlen($resp), strlen($payload));
        Assert::same(md5($resp), md5($payload));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testConvertException(): void
    {
        Expect::exception(ServiceException::class)->withMessageContaining('unknown Raw payload type');

        $conn = $this->makeRPC();
        $payload = base64_encode(random_bytes(65000 * 5));

        $resp = $conn->withCodec(new RawCodec())->call(
            'Service.Echo',
            $payload
        );

        Assert::same(strlen($resp), strlen($payload));
        Assert::same(md5($resp), md5($payload));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testRawBody(): void
    {
        $conn = $this->makeRPC();
        $payload = random_bytes(100);

        $resp = $conn->withCodec(new RawCodec())->call(
            'Service.EchoBinary',
            $payload
        );

        Assert::same(strlen($resp), strlen($payload));
        Assert::same(md5($resp), md5($payload));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testLongRawBody(): void
    {
        $conn = $this->makeRPC();
        $payload = random_bytes(65000 * 1000);

        $resp = $conn->withCodec(new RawCodec())->call(
            'Service.EchoBinary',
            $payload
        );

        Assert::same(strlen($resp), strlen($payload));
        Assert::same(md5($resp), md5($payload));
    }

    #[Test]
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

        Assert::same($resp, [
            'Name'  => 'WOLFY-J',
            'Value' => -18,
            'Keys'  => null
        ]);
    }

    #[Test]
    public function testBadPayload(): void
    {
        Expect::exception(ServiceException::class)->withMessageContaining('unknown Raw payload type');

        $conn = $this->makeRPC();
        $conn->withCodec(new RawCodec())->call('Service.Process', 'raw');
    }

    #[Test]
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

        Assert::array($resp['Keys']);
        Assert::array($resp['Keys'])->hasKeys('value');
        Assert::array($resp['Keys'])->hasKeys('domain');

        Assert::same($resp['Keys']['value'], 'Key');
        Assert::same($resp['Keys']['domain'], 'Email');
    }

    #[Test]
    public function testBrokenPayloadMap(): void
    {
        Expect::exception(ServiceException::class);

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
    #[Test]
    public function testJsonException(): void
    {
        Expect::exception(CodecException::class);

        $conn = $this->makeRPC();

        $conn->call('Service.Process', random_bytes(256));
    }

    /**
     * @return GoridgeRPC
     */
    protected function makeRPC(): GoridgeRPC
    {
        return new GoridgeRPC($this->makeRelay());
    }

    /**
     * @return RelayInterface
     */
    protected function makeRelay(): RelayInterface
    {
        return new SocketRelay(static::SOCK_ADDR, static::SOCK_PORT, static::SOCK_TYPE);
    }
}
