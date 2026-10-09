<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests;

use Spiral\Goridge\ConnectedRelayInterface;
use Spiral\Goridge\Exception\TransportException;
use Spiral\Goridge\RelayInterface;
use Spiral\Goridge\RPC\Codec\JsonCodec;
use Spiral\Goridge\RPC\Codec\MsgpackCodec;
use Spiral\Goridge\RPC\Codec\RawCodec;
use Spiral\Goridge\RPC\Exception\CodecException;
use Spiral\Goridge\RPC\Exception\RPCException;
use Spiral\Goridge\RPC\Exception\ServiceException;
use Spiral\Goridge\RPC\MultiRPC as GoridgeMultiRPC;
use Spiral\Goridge\SocketRelay;
use Spiral\Goridge\SocketType;
use Spiral\Goridge\StreamRelay;
use Spiral\Goridge\Tests\Support\AfterEachTest;
use Testo\Expect;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[AfterEachTest('assertFreeRelaysCorrectNumber')]
abstract class MultiRPC
{
    public const GO_APP = 'server';
    public const SOCK_ADDR = '127.0.0.1';
    public const SOCK_PORT = 7079;
    public const SOCK_TYPE = SocketType::TCP;

    protected GoridgeMultiRPC $rpc;
    private int $expectedNumberOfRelays;

    #[Test]
    public function testManualConnect(): void
    {
        $property = new \ReflectionProperty(GoridgeMultiRPC::class, 'freeRelays');
        $property->setValue([]);

        $relays = [];
        for ($i = 0; $i < 10; $i++) {
            $relays[] = $this->makeRelay();
        }
        /** @var SocketRelay $relay */
        $relay = $relays[0];
        $this->rpc = new GoridgeMultiRPC($relays);
        $this->expectedNumberOfRelays = 10;

        \Testo\Assert::false($relay->isConnected());

        $relay->connect();
        \Testo\Assert::true($relay->isConnected());

        \Testo\Assert::same($this->rpc->call('Service.Ping', 'ping'), 'pong');
        \Testo\Assert::true($relay->isConnected());

        $this->rpc->preConnectRelays();
        foreach ($relays as $relay) {
            \Testo\Assert::true($relay->isConnected());
        }
    }

    #[Test]
    public function testReconnect(): void
    {
        $property = new \ReflectionProperty(GoridgeMultiRPC::class, 'freeRelays');
        $property->setValue([]);

        /** @var SocketRelay $relay */
        $relay = $this->makeRelay();
        $this->rpc = new GoridgeMultiRPC([$relay]);
        $this->expectedNumberOfRelays = 1;

        \Testo\Assert::false($relay->isConnected());

        \Testo\Assert::same($this->rpc->call('Service.Ping', 'ping'), 'pong');
        \Testo\Assert::true($relay->isConnected());

        $relay->close();
        \Testo\Assert::false($relay->isConnected());

        \Testo\Assert::same($this->rpc->call('Service.Ping', 'ping'), 'pong');
        \Testo\Assert::true($relay->isConnected());
    }

    #[Test]
    public function testPingPong(): void
    {
        \Testo\Assert::same($this->rpc->call('Service.Ping', 'ping'), 'pong');
    }

    #[Test]
    public function testPingPongAsync(): void
    {
        $id = $this->rpc->callAsync('Service.Ping', 'ping');
        \Testo\Assert::same($this->rpc->getResponse($id), 'pong');
    }

    #[Test]
    public function testPrefixPingPong(): void
    {
        $this->rpc = $this->rpc->withServicePrefix('Service');
        \Testo\Assert::same($this->rpc->call('Ping', 'ping'), 'pong');
    }

    #[Test]
    public function testPrefixPingPongAsync(): void
    {
        $this->rpc = $this->rpc->withServicePrefix('Service');
        $id = $this->rpc->callAsync('Ping', 'ping');
        \Testo\Assert::same($this->rpc->getResponse($id), 'pong');
    }

    #[Test]
    public function testPingNull(): void
    {
        \Testo\Assert::same($this->rpc->call('Service.Ping', 'not-ping'), '');
    }

    #[Test]
    public function testPingNullAsync(): void
    {
        $id = $this->rpc->callAsync('Service.Ping', 'not-ping');
        \Testo\Assert::same($this->rpc->getResponse($id), '');
    }

    #[Test]
    public function testNegate(): void
    {
        \Testo\Assert::same($this->rpc->call('Service.Negate', 10), -10);
    }

    #[Test]
    public function testNegateAsync(): void
    {
        $id = $this->rpc->callAsync('Service.Negate', 10);
        \Testo\Assert::same($this->rpc->getResponse($id), -10);
    }

    #[Test]
    public function testNegateNegative(): void
    {
        \Testo\Assert::same($this->rpc->call('Service.Negate', -10), 10);
    }

    #[Test]
    public function testNegateNegativeAsync(): void
    {
        $id = $this->rpc->callAsync('Service.Negate', -10);
        \Testo\Assert::same($this->rpc->getResponse($id), 10);
    }

    #[Test]
    public function testInvalidService(): void
    {
        Expect::exception(ServiceException::class);
        $this->rpc = $this->rpc->withServicePrefix('Service2');
        \Testo\Assert::same($this->rpc->call('Ping', 'ping'), 'pong');
    }

    #[Test]
    public function testInvalidServiceAsync(): void
    {
        $this->rpc = $this->rpc->withServicePrefix('Service2');
        $id = $this->rpc->callAsync('Ping', 'ping');
        Expect::exception(ServiceException::class);
        \Testo\Assert::same($this->rpc->getResponse($id), 'pong');
    }

    #[Test]
    public function testInvalidMethod(): void
    {
        Expect::exception(ServiceException::class);
        $this->rpc = $this->rpc->withServicePrefix('Service');
        \Testo\Assert::same($this->rpc->call('Ping2', 'ping'), 'pong');
    }

    #[Test]
    public function testInvalidMethodAsync(): void
    {
        $this->rpc = $this->rpc->withServicePrefix('Service');
        $id = $this->rpc->callAsync('Ping2', 'ping');
        Expect::exception(ServiceException::class);
        \Testo\Assert::same($this->rpc->getResponse($id), 'pong');
    }

    #[Test]
    public function testLongEcho(): void
    {
        $payload = base64_encode(random_bytes(65000 * 5));

        $resp = $this->rpc->call('Service.Echo', $payload);

        \Testo\Assert::same(strlen($resp), strlen($payload));
        \Testo\Assert::same(md5($resp), md5($payload));
    }

    #[Test]
    public function testLongEchoAsync(): void
    {
        $payload = base64_encode(random_bytes(65000 * 5));

        $id = $this->rpc->callAsync('Service.Echo', $payload);
        $resp = $this->rpc->getResponse($id);

        \Testo\Assert::same(strlen($resp), strlen($payload));
        \Testo\Assert::same(md5($resp), md5($payload));
    }

    #[Test]
    public function testConvertException(): void
    {
        Expect::exception(ServiceException::class)->withMessageContaining('unknown Raw payload type');

        $payload = base64_encode(random_bytes(65000 * 5));

        $resp = $this->rpc->withCodec(new RawCodec())->call(
            'Service.Echo',
            $payload,
        );

        \Testo\Assert::same(strlen($resp), strlen($payload));
        \Testo\Assert::same(md5($resp), md5($payload));
    }

    #[Test]
    public function testConvertExceptionAsync(): void
    {
        $payload = base64_encode(random_bytes(65000 * 5));

        $this->rpc = $this->rpc->withCodec(new RawCodec());
        $id = $this->rpc->callAsync(
            'Service.Echo',
            $payload,
        );

        Expect::exception(ServiceException::class)->withMessageContaining('unknown Raw payload type');

        $resp = $this->rpc->getResponse($id);

        \Testo\Assert::same(strlen($resp), strlen($payload));
        \Testo\Assert::same(md5($resp), md5($payload));
    }

    #[Test]
    public function testRawBody(): void
    {
        $payload = random_bytes(100);

        $resp = $this->rpc->withCodec(new RawCodec())->call(
            'Service.EchoBinary',
            $payload,
        );

        \Testo\Assert::same(strlen($resp), strlen($payload));
        \Testo\Assert::same(md5($resp), md5($payload));
    }

    #[Test]
    public function testRawBodyAsync(): void
    {
        $payload = random_bytes(100);

        $this->rpc = $this->rpc->withCodec(new RawCodec());
        $id = $this->rpc->callAsync(
            'Service.EchoBinary',
            $payload,
        );
        $resp = $this->rpc->getResponse($id);

        \Testo\Assert::same(strlen($resp), strlen($payload));
        \Testo\Assert::same(md5($resp), md5($payload));
    }

    #[Test]
    public function testLongRawBody(): void
    {
        $payload = random_bytes(65000 * 1000);

        $resp = $this->rpc->withCodec(new RawCodec())->call(
            'Service.EchoBinary',
            $payload,
        );

        \Testo\Assert::same(strlen($resp), strlen($payload));
        \Testo\Assert::same(md5($resp), md5($payload));
    }

    #[Test]
    public function testLongRawBodyAsync(): void
    {
        $payload = random_bytes(65000 * 1000);

        $this->rpc = $this->rpc->withCodec(new RawCodec());
        $id = $this->rpc->callAsync(
            'Service.EchoBinary',
            $payload,
        );
        $resp = $this->rpc->getResponse($id);

        \Testo\Assert::same(strlen($resp), strlen($payload));
        \Testo\Assert::same(md5($resp), md5($payload));
    }

    #[Test]
    public function testPayload(): void
    {
        $resp = $this->rpc->call(
            'Service.Process',
            [
                'Name' => 'wolfy-j',
                'Value' => 18,
            ],
        );

        \Testo\Assert::same($resp, [
            'Name' => 'WOLFY-J',
            'Value' => -18,
            'Keys' => null,
        ]);
    }

    #[Test]
    public function testPayloadAsync(): void
    {
        $id = $this->rpc->callAsync(
            'Service.Process',
            [
                'Name' => 'wolfy-j',
                'Value' => 18,
            ],
        );
        $resp = $this->rpc->getResponse($id);

        \Testo\Assert::same($resp, [
            'Name' => 'WOLFY-J',
            'Value' => -18,
            'Keys' => null,
        ]);
    }

    #[Test]
    public function testBadPayload(): void
    {
        Expect::exception(ServiceException::class)->withMessageContaining('unknown Raw payload type');

        $this->rpc->withCodec(new RawCodec())->call('Service.Process', 'raw');
    }

    #[Test]
    public function testBadPayloadAsync(): void
    {
        $this->rpc = $this->rpc->withCodec(new RawCodec());
        $id = $this->rpc->callAsync('Service.Process', 'raw');

        Expect::exception(ServiceException::class)->withMessageContaining('unknown Raw payload type');
        $resp = $this->rpc->getResponse($id);
    }

    #[Test]
    public function testPayloadWithMap(): void
    {
        $resp = $this->rpc->call(
            'Service.Process',
            [
                'Name' => 'wolfy-j',
                'Value' => 18,
                'Keys' => [
                    'Key' => 'value',
                    'Email' => 'domain',
                ],
            ],
        );

        \Testo\Assert::array($resp['Keys']);
        \Testo\Assert::array($resp['Keys'])->hasKeys('value');
        \Testo\Assert::array($resp['Keys'])->hasKeys('domain');

        \Testo\Assert::same($resp['Keys']['value'], 'Key');
        \Testo\Assert::same($resp['Keys']['domain'], 'Email');
    }

    #[Test]
    public function testPayloadWithMapAsync(): void
    {
        $id = $this->rpc->callAsync(
            'Service.Process',
            [
                'Name' => 'wolfy-j',
                'Value' => 18,
                'Keys' => [
                    'Key' => 'value',
                    'Email' => 'domain',
                ],
            ],
        );
        $resp = $this->rpc->getResponse($id);

        \Testo\Assert::array($resp['Keys']);
        \Testo\Assert::array($resp['Keys'])->hasKeys('value');
        \Testo\Assert::array($resp['Keys'])->hasKeys('domain');

        \Testo\Assert::same($resp['Keys']['value'], 'Key');
        \Testo\Assert::same($resp['Keys']['domain'], 'Email');
    }

    #[Test]
    public function testBrokenPayloadMap(): void
    {
        $id = $this->rpc->callAsync(
            'Service.Process',
            [
                'Name' => 'wolfy-j',
                'Value' => 18,
                'Keys' => 1111,
            ],
        );

        Expect::exception(ServiceException::class);
        $resp = $this->rpc->getResponse($id);
    }

    #[Test]
    public function testJsonException(): void
    {
        Expect::exception(CodecException::class);
        $this->rpc->call('Service.Process', random_bytes(256));
    }

    #[Test]
    public function testJsonExceptionAsync(): void
    {
        Expect::exception(CodecException::class);
        $id = $this->rpc->callAsync('Service.Process', random_bytes(256));
    }

    #[Test]
    public function testJsonExceptionNotThrownWithIgnoreResponse(): void
    {
        Expect::exception(CodecException::class);
        $this->rpc->callIgnoreResponse('Service.Process', random_bytes(256));
    }

    #[Test]
    public function testSleepEcho(): void
    {
        $time = hrtime(true) / 1e9;
        \Testo\Assert::same($this->rpc->call('Service.SleepEcho', 'Hello'), 'Hello');
        // sleep is 100ms, so we check if we are further along than 100ms
        \Testo\Assert::numeric(hrtime(true) / 1e9)->greaterThanOrEqual($time + 0.1);
    }

    #[Test]
    public function testSleepEchoAsync(): void
    {
        $time = hrtime(true) / 1e9;
        $id = $this->rpc->callAsync('Service.SleepEcho', 'Hello');
        // hrtime is in nanoseconds, and at most expect 1ms delay (sleep is 100ms)
        \Testo\Assert::numeric(hrtime(true) / 1e9)->lessThanOrEqual($time + 0.001);
        \Testo\Assert::false($this->rpc->hasResponse($id));
        \Testo\Assert::same($this->rpc->getResponse($id), 'Hello');
        // sleep is 100ms, so we check if we are further along than 100ms
        \Testo\Assert::numeric(hrtime(true) / 1e9)->greaterThanOrEqual($time + 0.1);
    }

    #[Test]
    public function testSleepEchoIgnoreResponse(): void
    {
        $time = hrtime(true) / 1e9;
        $this->rpc->callIgnoreResponse('Service.SleepEcho', 'Hello');
        // hrtime is in nanoseconds, and at most expect 1ms delay (sleep is 100ms)
        \Testo\Assert::numeric(hrtime(true) / 1e9)->lessThanOrEqual($time + 0.001);
        // Wait for response
        usleep(100_000);

        $this->forceFlushRpc();
    }

    #[Test]
    public function testCannotGetSameResponseTwice(): void
    {
        $id = $this->rpc->callAsync('Service.Ping', 'ping');
        \Testo\Assert::same($this->rpc->getResponse($id), 'pong');
        $this->assertFreeRelaysCorrectNumber($this->rpc);
        Expect::exception(RPCException::class)->withMessageContaining(GoridgeMultiRPC::ERR_INVALID_SEQ_NUMBER);
        \Testo\Assert::same($this->rpc->getResponse($id), 'pong');
    }

    #[Test]
    public function testCanCallMoreTimesThanRelays(): void
    {
        $ids = [];

        for ($i = 0; $i < 50; $i++) {
            $ids[] = $this->rpc->callAsync('Service.Ping', 'ping');
        }

        foreach ($this->rpc->getResponses($ids) as $response) {
            \Testo\Assert::same($response, 'pong');
        }
    }

    #[Test]
    public function testCanCallMoreTimesThanBufferAndNotGetResponses(): void
    {
        $ids = [];

        // Flood to force the issue
        for ($i = 0; $i < 20_000; $i++) {
            $ids[] = $this->rpc->callAsync('Service.Ping', 'ping');
        }

        Expect::exception(RPCException::class);

        // We cheat here since the order in which responses are discarded depends on when they are received
        $property = new \ReflectionProperty(GoridgeMultiRPC::class, 'asyncResponseBuffer');
        $buffer = $property->getValue();

        foreach ($ids as $id) {
            if (!isset($buffer[$id])) {
                $this->rpc->getResponse($id);
                \Testo\Assert::fail("Invalid seq did not throw exception");
            }
        }
    }

    #[Test]
    public function testCanCallMoreTimesThanRelaysWithIntermittentResponseHandling(): void
    {
        $ids = [];

        for ($i = 0; $i < 150; $i++) {
            if ($i === 50) {
                foreach ($this->rpc->getResponses($ids) as $response) {
                    \Testo\Assert::same($response, 'pong');
                }
                $ids = [];
            }
            $ids[] = $this->rpc->callAsync('Service.Ping', 'ping');
        }

        foreach ($this->rpc->getResponses($ids) as $response) {
            \Testo\Assert::same($response, 'pong');
        }
    }

    #[Test]
    public function testHandleRelayDisconnect(): void
    {
        $id = $this->rpc->callAsync('Service.Ping', 'ping');
        $property = new \ReflectionProperty(GoridgeMultiRPC::class, 'occupiedRelays');
        $occupiedRelays = $property->getValue();
        \Testo\Assert::instanceOf($occupiedRelays[$id], SocketRelay::class);
        $occupiedRelays[$id]->close();
        Expect::exception(TransportException::class);
        $this->rpc->getResponse($id);
    }

    #[Test]
    public function testHandleRelayDisconnectWithPressure(): void
    {
        $id = $this->rpc->callAsync('Service.Ping', 'ping');
        $property = new \ReflectionProperty(GoridgeMultiRPC::class, 'occupiedRelays');
        $occupiedRelays = $property->getValue();
        \Testo\Assert::instanceOf($occupiedRelays[$id], SocketRelay::class);
        $occupiedRelays[$id]->close();

        $ids = [];
        for ($i = 0; $i < 50; $i++) {
            $ids[] = $this->rpc->callAsync('Service.Ping', 'ping');
        }

        foreach ($this->rpc->getResponses($ids) as $response) {
            \Testo\Assert::same($response, 'pong');
        }

        // In this case there may be two different scenarios, which is why there are three tests basically doing the same
        // In the first one, the disconnected relay was already discovered. In that case, an RPCException is thrown (unknown seq).
        // In the second one, the disconnected relay is only now discovered, which throws a TransportException instead.
        // We need to kind of force the issue in the second two tests. This one does whatever the MultiRPC has done.
        $property = new \ReflectionProperty(GoridgeMultiRPC::class, 'seqToRelayMap');
        $discovered = !isset($property->getValue()[$id]);

        if ($discovered) {
            Expect::exception(RPCException::class)->withMessageContaining(GoridgeMultiRPC::ERR_INVALID_SEQ_NUMBER);
        } else {
            Expect::exception(TransportException::class)->withMessageContaining('Unable to read payload from the stream');
        }
        $this->rpc->getResponse($id);
    }

    #[Test]
    public function testHandleRelayDisconnectWithPressureForceDiscovered(): void
    {
        $id = $this->rpc->callAsync('Service.Ping', 'ping');
        $property = new \ReflectionProperty(GoridgeMultiRPC::class, 'occupiedRelays');
        $occupiedRelays = $property->getValue();
        \Testo\Assert::instanceOf($occupiedRelays[$id], SocketRelay::class);
        $occupiedRelays[$id]->close();

        $ids = [];
        for ($i = 0; $i < 50; $i++) {
            $ids[] = $this->rpc->callAsync('Service.Ping', 'ping');
        }

        foreach ($this->rpc->getResponses($ids) as $response) {
            \Testo\Assert::same($response, 'pong');
        }

        // In this case there may be two different scenarios, which is why there are three tests basically doing the same
        // In the first one, the disconnected relay was already discovered. In that case, an RPCException is thrown (unknown seq).
        // In the second one, the disconnected relay is only now discovered, which throws a TransportException instead.
        // We need to kind of force the issue in the second two tests. This one does whatever the MultiRPC has done.
        $property = new \ReflectionProperty(GoridgeMultiRPC::class, 'seqToRelayMap');
        $discovered = !isset($property->getValue()[$id]);

        if (!$discovered) {
            $method = new \ReflectionMethod(GoridgeMultiRPC::class, 'checkAllOccupiedRelaysStillConnected');
            $method->invoke($this->rpc);
        }

        Expect::exception(RPCException::class)->withMessageContaining(GoridgeMultiRPC::ERR_INVALID_SEQ_NUMBER);
        $this->rpc->getResponse($id);
    }

    #[Test]
    public function testHandleRelayDisconnectWithPressureForceUndiscovered(): void
    {
        $id = $this->rpc->callAsync('Service.Ping', 'ping');
        $occupiedProperty = new \ReflectionProperty(GoridgeMultiRPC::class, 'occupiedRelays');
        $occupiedRelays = $occupiedProperty->getValue();
        \Testo\Assert::instanceOf($occupiedRelays[$id], SocketRelay::class);
        $occupiedRelays[$id]->close();

        $ids = [];
        for ($i = 0; $i < 50; $i++) {
            $ids[] = $this->rpc->callAsync('Service.Ping', 'ping');
        }

        foreach ($this->rpc->getResponses($ids) as $response) {
            \Testo\Assert::same($response, 'pong');
        }

        // In this case there may be two different scenarios, which is why there are three tests basically doing the same
        // In the first one, the disconnected relay was already discovered. In that case, an RPCException is thrown (unknown seq).
        // In the second one, the disconnected relay is only now discovered, which throws a TransportException instead.
        // We need to kind of force the issue in the second two tests. This one does whatever the MultiRPC has done.
        $mapProperty = new \ReflectionProperty(GoridgeMultiRPC::class, 'seqToRelayMap');
        $seqToRelayMap = $mapProperty->getValue();
        $discovered = !isset($seqToRelayMap[$id]);

        if ($discovered) {
            $property = new \ReflectionProperty(GoridgeMultiRPC::class, 'freeRelays');
            $freeRelays = $property->getValue();
            $relay = array_pop($freeRelays);
            $property->setValue($freeRelays);
            assert($relay instanceof SocketRelay);
            $relay->close();
            $seqToRelayMap[$id] = $relay;
            $occupiedRelays[$id] = $relay;
            $mapProperty->setValue($seqToRelayMap);
            $occupiedProperty->setValue($occupiedRelays);
        }

        Expect::exception(TransportException::class)->withMessageContaining('Unable to read payload from the stream');
        $this->rpc->getResponse($id);
    }

    #[Test]
    public function testHandleRelayDisconnectWithPressureGetResponses(): void
    {
        $ids = [];
        $ids[] = $id = $this->rpc->callAsync('Service.Ping', 'ping');
        $property = new \ReflectionProperty(GoridgeMultiRPC::class, 'occupiedRelays');
        $occupiedRelays = $property->getValue();
        \Testo\Assert::instanceOf($occupiedRelays[$id], SocketRelay::class);
        $occupiedRelays[$id]->close();

        for ($i = 0; $i < 50; $i++) {
            $ids[] = $this->rpc->callAsync('Service.Ping', 'ping');
        }

        Expect::exception(RPCException::class)->withMessageContaining(GoridgeMultiRPC::ERR_INVALID_SEQ_NUMBER);
        foreach ($this->rpc->getResponses($ids) as $response) {
            \Testo\Assert::same($response, 'pong');
        }
    }

    /**
     * This test checks whether relays are cloned correctly, or if they get shared between the cloned instances.
     * Without cloning them explicitly they get shared and thus, when one RPC gets called, the freeRelays array
     * in the other RPC stays the same, making it reuse the just-used and still occupied relay.
     */
    #[Test]
    public function testHandlesCloneCorrectly(): void
    {
        $this->rpc->preConnectRelays();

        // This is to support the MsgPackMultiRPC Tests
        $property = new \ReflectionProperty(GoridgeMultiRPC::class, 'codec');
        $codec = $property->getValue($this->rpc);
        $clonedRpc = $this->rpc->withCodec($codec instanceof MsgpackCodec ? new JsonCodec() : new MsgpackCodec());

        $property = new \ReflectionProperty(GoridgeMultiRPC::class, 'freeRelays');
        foreach ($property->getValue() as $relay) {
            /** @var ConnectedRelayInterface $relay */
            \Testo\Assert::true($relay->isConnected());
        }

        $ids = [];
        $clonedIds = [];

        for ($i = 0; $i < 50; $i++) {
            $ids[] = $this->rpc->callAsync('Service.Ping', 'ping');
        }

        for ($i = 0; $i < 50; $i++) {
            $clonedIds[] = $clonedRpc->callAsync('Service.Echo', 'Hello');
        }
        // Wait 100ms for the response(s)
        usleep(100 * 1000);

        // Can use wrong RPC for response (unfortunately, but there's no easy solution)
        try {
            $response = $this->rpc->getResponse($clonedIds[0]);
            $property = new \ReflectionProperty(GoridgeMultiRPC::class, 'codec');

            if ($property->getValue($this->rpc) instanceof MsgpackCodec) {
                // Msgpack internally does not throw an error, only returns the encoded response because of course why
                // would normal error handling be something that is important in a library.
                // Locally this returned the number 34, but I'm not sure if there's some variation in that
                // so we test on the expected response.
                // This also notifies PHPUnit since msgpack logs a warning.
                if ($response !== 'Hello') {
                    throw new CodecException("msgpack is a big meany");
                }
            }

            \Testo\Assert::fail("Should've thrown an Exception due to wrong codec");
        } catch (CodecException $exception) {
            \Testo\Assert::false(empty($exception->getMessage()));
        }

        // The $seq should not be available anymore
        try {
            $response = $clonedRpc->getResponse($clonedIds[0]);
            \Testo\Assert::fail("Should've thrown an exception due to wrong seq");
        } catch (RPCException $exception) {
            \Testo\Assert::false(empty($exception->getMessage()));
        }

        array_shift($clonedIds);

        foreach ($this->rpc->getResponses($ids) as $response) {
            \Testo\Assert::same($response, 'pong');
        }

        foreach ($clonedRpc->getResponses($clonedIds) as $response) {
            \Testo\Assert::same($response, 'Hello');
        }
    }

    #[Test]
    public function testNeedsAtLeastOne(): void
    {
        $property = new \ReflectionProperty(GoridgeMultiRPC::class, 'freeRelays');
        $property->setValue([]);
        $this->expectedNumberOfRelays = 0;
        Expect::exception(RPCException::class)->withMessageContaining("MultiRPC needs at least one relay. Zero provided.");
        new GoridgeMultiRPC([]);
    }

    #[Test]
    public function testChecksIfResponseIsInRelay(): void
    {
        $id = $this->rpc->callAsync('Service.Ping', 'ping');
        // Wait a bit
        usleep(100 * 1000);

        \Testo\Assert::true($this->rpc->hasResponse($id));
    }

    #[Test]
    public function testChecksIfResponseIsInBuffer(): void
    {
        $id = $this->rpc->callAsync('Service.Ping', 'ping');
        // Wait a bit
        usleep(100 * 1000);
        $this->forceFlushRpc();

        \Testo\Assert::true($this->rpc->hasResponse($id));
    }

    #[Test]
    public function testChecksIfResponseIsNotReceivedYet(): void
    {
        $id = $this->rpc->callAsync('Service.Ping', 'ping');
        \Testo\Assert::false($this->rpc->hasResponse($id));
    }

    #[Test]
    public function testChecksMultipleResponses(): void
    {
        $ids = [];
        $ids[] = $this->rpc->callAsync('Service.Ping', 'ping');
        $this->forceFlushRpc();
        $ids[] = $this->rpc->callAsync('Service.Ping', 'ping');
        usleep(100 * 1000);
        $ids[] = $this->rpc->callAsync('Service.Ping', 'ping');
        $responses = $this->rpc->hasResponses($ids);
        \Testo\Assert::contains($responses, $ids[0]);
        \Testo\Assert::contains($responses, $ids[1]);
        \Testo\Assert::iterable($responses)->notContains($ids[2]);
    }

    #[Test]
    public function testHasResponsesReturnsEmptyArrayWhenNoResponses(): void
    {
        $id = $this->rpc->callAsync('Service.Ping', 'ping');
        \Testo\Assert::blank($this->rpc->hasResponses([$id]));
    }

    #[Test]
    public function testGetResponsesReturnsWhenNoRelaysAvailableToAvoidInfiniteLoop(): void
    {
        $property = new \ReflectionProperty(GoridgeMultiRPC::class, 'freeRelays');
        $property->setValue([]);
        $property = new \ReflectionProperty(GoridgeMultiRPC::class, 'occupiedRelays');
        $property->setValue([]);
        $this->expectedNumberOfRelays = 0;
        Expect::exception(RPCException::class)->withMessageContaining("No relays available at all");
        $this->rpc->call('Service.Ping', 'ping');
    }

    #[Test]
    public function testMultiRPCIsUsableWithOneRelay(): void
    {
        $this->makeRPC(1);
        $this->rpc->callIgnoreResponse('Service.Ping', 'ping');
        $this->rpc->callIgnoreResponse('Service.SleepEcho', 'Hello');
        $id = $this->rpc->callAsync('Service.Ping', 'ping');
        $this->rpc->callIgnoreResponse('Service.Echo', 'Hello');
        \Testo\Assert::same($this->rpc->call('Service.Ping', 'ping'), 'pong');
        \Testo\Assert::same($this->rpc->getResponse($id), 'pong');
    }

    #[Test]
    public function testThrowsWhenMixedRelaysProvided(): void
    {
        $property = new \ReflectionProperty(GoridgeMultiRPC::class, 'freeRelays');
        $property->setValue([]);
        $this->expectedNumberOfRelays = 0;
        $relays = [new StreamRelay(STDIN, STDOUT), $this->makeRelay()];
        Expect::exception(RPCException::class)->withMessageContaining("MultiRPC can only be used with all relays of the same type, such as a " . SocketRelay::class);
        new GoridgeMultiRPC($relays);
    }

    #[Test]
    public function testThrowsWhenRelaysDontMatchExistingOnes(): void
    {
        $relays = [new StreamRelay(STDIN, STDOUT)];
        Expect::exception(RPCException::class)->withMessageContaining("MultiRPC can only be used with all relays of the same type, such as a " . SocketRelay::class);
        new GoridgeMultiRPC($relays);
    }

    #[BeforeTest]
    protected function setUp(): void
    {
        $this->makeRPC();
    }

    protected function makeRPC(int $count = 10): void
    {
        // We need to manually clean the static properties between test runs.
        // In an actual application this would never happen.
        $property = new \ReflectionProperty(GoridgeMultiRPC::class, 'freeRelays');
        $property->setValue([]);
        $property = new \ReflectionProperty(GoridgeMultiRPC::class, 'occupiedRelays');
        $property->setValue([]);
        $property = new \ReflectionProperty(GoridgeMultiRPC::class, 'seqToRelayMap');
        $property->setValue([]);
        $property = new \ReflectionProperty(GoridgeMultiRPC::class, 'asyncResponseBuffer');
        $property->setValue([]);
        $type = self::SOCK_TYPE->value;
        $address = self::SOCK_ADDR;
        $port = self::SOCK_PORT;
        $this->rpc = GoridgeMultiRPC::create("$type://$address:$port", $count);
        $this->expectedNumberOfRelays = $count;
    }

    protected function makeRelay(): RelayInterface
    {
        return new SocketRelay(static::SOCK_ADDR, static::SOCK_PORT, static::SOCK_TYPE);
    }

    protected function assertFreeRelaysCorrectNumber(): void
    {
        $property = new \ReflectionProperty(GoridgeMultiRPC::class, 'freeRelays');
        $numberOfFreeRelays = count($property->getValue());
        $property = new \ReflectionProperty(GoridgeMultiRPC::class, 'occupiedRelays');
        $numberOfOccupiedRelays = count($property->getValue());
        $property = new \ReflectionProperty(GoridgeMultiRPC::class, 'seqToRelayMap');
        $numberOfWaitingResponses = count($property->getValue());

        \Testo\Assert::same($numberOfFreeRelays + $numberOfOccupiedRelays, $this->expectedNumberOfRelays, "RPC has lost at least one relay! Waiting Responses: $numberOfWaitingResponses, Free Relays: $numberOfFreeRelays, Occupied Relays: $numberOfOccupiedRelays");
    }

    protected function forceFlushRpc(): void
    {
        // Force consuming relay by flooding requests
        $ids = [];
        for ($i = 0; $i < 50; $i++) {
            $ids[] = $this->rpc->callAsync('Service.Ping', 'ping');
        }
        foreach ($this->rpc->getResponses($ids) as $id => $response) {
            \Testo\Assert::same($response, 'pong');
            array_splice($ids, array_search($id, $ids, true), 1);
        }
        \Testo\Assert::blank($ids);
    }
}
