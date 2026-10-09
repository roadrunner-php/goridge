<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests;

use Spiral\Goridge\Exception\HeaderException;
use Spiral\Goridge\Exception\RelayException;
use Spiral\Goridge\Frame;
use Spiral\Goridge\SocketRelay;
use Spiral\Goridge\SocketType;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Test;

/**
 * Runs the relay against a listening socket owned by the test, so no external server is needed.
 */
#[Test]
#[Covers(SocketRelay::class)]
final class SocketRelayTest
{
    /** @var resource|null */
    private $server = null;

    #[DataSet(['LocalHost', 6001, SocketType::TCP, 'tcp://localhost:6001'], 'tcp address is lowercased')]
    #[DataSet(['/tmp/RPC.sock', 6001, SocketType::UNIX, 'unix:///tmp/RPC.sock'], 'unix ignores port')]
    public function testStringRepresentation(string $address, int $port, SocketType $type, string $expected): void
    {
        $relay = new SocketRelay($address, $port, $type);

        Assert::same((string) $relay, $expected);
    }

    public function testUnixSocketHasNoPort(): void
    {
        $relay = new SocketRelay('/tmp/rpc.sock', 6001, SocketType::UNIX);

        Assert::null($relay->getPort());
    }

    public function testHasNoFrameWhenNotConnected(): void
    {
        $relay = new SocketRelay('127.0.0.1', 6001);

        Assert::false($relay->hasFrame());
        Assert::false($relay->isConnected());
    }

    public function testCannotCloseNotConnectedRelay(): never
    {
        $relay = new SocketRelay('127.0.0.1', 6001);

        Expect::exception(RelayException::class)
            ->withMessage("Unable to close socket 'tcp://127.0.0.1:6001', socket already closed");

        $relay->close();
    }

    public function testFailsToConnectToMissingSocket(): never
    {
        $path = sys_get_temp_dir() . '/goridge-missing-' . bin2hex(random_bytes(4)) . '.sock';
        $relay = new SocketRelay($path, type: SocketType::UNIX);

        Expect::exception(RelayException::class)
            ->withMessage("Unable to establish connection unix://{$path}")
            ->withPrevious(RelayException::class);

        $relay->connect(1, 1);
    }

    public function testConnectIsIdempotent(): void
    {
        $relay = $this->connectedRelay();

        Assert::true($relay->connect());
        Assert::true($relay->isConnected());
    }

    public function testCloseDisconnects(): void
    {
        $relay = $this->connectedRelay();

        $relay->close();

        Assert::false($relay->isConnected());
    }

    public function testCloneIsNotConnected(): void
    {
        $relay = $this->connectedRelay();

        $clone = clone $relay;

        Assert::false($clone->isConnected());
        Assert::true($relay->isConnected());
    }

    public function testExchangesFramesWithPeer(): void
    {
        $relay = $this->connectedRelay();
        $peer = $this->acceptPeer();
        $request = new Frame('ping', [1, 4], Frame::CODEC_JSON);
        $response = new Frame('pong', [1, 4], Frame::CODEC_JSON);
        $packedRequest = Frame::packFrame($request);

        $relay->send($request);
        $received = fread($peer, \strlen($packedRequest));
        fwrite($peer, Frame::packFrame($response));

        Assert::same($received, $packedRequest);
        Assert::equals($relay->waitFrame(), $response);
    }

    public function testFailsWhenPeerClosesBeforeHeader(): never
    {
        $relay = $this->connectedRelay();
        fclose($this->acceptPeer());

        Expect::exception(HeaderException::class)->withMessageContaining('Unable to read frame header');

        $relay->waitFrame();
    }

    public function testFailsWhenPeerClosesMidPayload(): never
    {
        $relay = $this->connectedRelay();
        $peer = $this->acceptPeer();
        fwrite($peer, substr(Frame::packFrame(new Frame('truncated payload')), 0, 15));
        fclose($peer);

        Expect::exception(HeaderException::class)->withMessageContaining('Unable to read payload from socket');

        $relay->waitFrame();
    }

    #[AfterTest]
    public function closeServer(): void
    {
        if ($this->server !== null) {
            fclose($this->server);
            $this->server = null;
        }
    }

    private function connectedRelay(): SocketRelay
    {
        $this->server = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($this->server, false), ':'), 1);

        $relay = new SocketRelay('127.0.0.1', $port);
        $relay->connect();

        return $relay;
    }

    /**
     * @return resource
     */
    private function acceptPeer()
    {
        return stream_socket_accept($this->server, 5);
    }
}
