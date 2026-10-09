<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests;

use Spiral\Goridge\Exception\HeaderException;
use Spiral\Goridge\Exception\InvalidArgumentException;
use Spiral\Goridge\Exception\TransportException;
use Spiral\Goridge\Frame;
use Spiral\Goridge\StreamRelay;
use Spiral\Goridge\Tests\Stub\FailingStream;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(StreamRelay::class)]
final class StreamTest
{
    public static function invalidStreamsProvider(): iterable
    {
        $closed = fopen('php://memory', 'rb+');
        fclose($closed);

        yield 'input is not a resource' => ['php://stdin', STDOUT, 'Expected a valid input resource stream'];
        yield 'input is write-only' => [fopen('php://output', 'wb'), STDOUT, 'Input resource stream must be readable'];
        yield 'output is closed' => [STDIN, $closed, 'Expected a valid output resource stream'];
        yield 'output is read-only' => [STDIN, fopen(__FILE__, 'rb'), 'Output resource stream must be writable'];
    }

    public function testMessagePassing(): void
    {
        $resource = fopen('php://memory', 'rb+');

        $relay = new StreamRelay($resource, $resource);

        $in = new Frame('hello world', [100, 9001], Frame::CODEC_RAW);
        $relay->send($in);

        fseek($resource, 0);

        Assert::equals($relay->waitFrame(), $in);
    }

    public function testMessagePassingBetweenPeers(): void
    {
        [$left, $right] = self::socketPair();
        $client = new StreamRelay($left, $left);
        $server = new StreamRelay($right, $right);
        $request = new Frame('ping', [1, 4], Frame::CODEC_JSON);
        $response = new Frame('pong', [1, 4], Frame::CODEC_JSON);

        $client->send($request);
        $received = $server->waitFrame();
        $server->send($response);

        Assert::equals($received, $request);
        Assert::equals($client->waitFrame(), $response);
    }

    public function testHasFrameReportsPendingData(): void
    {
        [$left, $right] = self::socketPair();
        $relay = new StreamRelay($left, $left);

        Assert::false($relay->hasFrame());

        fwrite($right, Frame::packFrame(new Frame('hello')));
        self::waitReadable($left);

        Assert::true($relay->hasFrame());
    }

    #[DataProvider('invalidStreamsProvider')]
    public function testRejectsInvalidStreams(mixed $in, mixed $out, string $message): never
    {
        Expect::exception(InvalidArgumentException::class)->withMessage($message);

        new StreamRelay($in, $out);
    }

    public function testFailsOnTruncatedHeader(): never
    {
        $resource = fopen('php://memory', 'rb+');
        fwrite($resource, 'short');
        fseek($resource, 0);
        $relay = new StreamRelay($resource, $resource);

        Expect::exception(HeaderException::class)
            ->withMessage('Unable to read frame header: Incorrect header size');

        $relay->waitFrame();
    }

    public function testFailsWhenHeaderCannotBeRead(): never
    {
        $stream = FailingStream::open([false], 'disk is gone');
        $relay = new StreamRelay($stream, $stream);

        Expect::exception(HeaderException::class)->withMessage('Unable to read frame header: disk is gone');

        $relay->waitFrame();
    }

    public function testFailsWhenPayloadCannotBeRead(): never
    {
        $header = substr(Frame::packFrame(new Frame('payload')), 0, 12);
        $stream = FailingStream::open([$header, false]);
        $relay = new StreamRelay($stream, $stream);

        Expect::exception(TransportException::class)
            ->withMessage('An error occurred while reading payload from the stream: Unknown Error');

        $relay->waitFrame();
    }

    public function testFailsWhenFrameCannotBeWritten(): never
    {
        $stream = FailingStream::open(warning: 'pipe is broken');
        $relay = new StreamRelay($stream, $stream);

        Expect::exception(TransportException::class)
            ->withMessage('An error occurred while write payload to the stream: pipe is broken');

        $relay->send(new Frame('hello'));
    }

    /**
     * @return array{resource, resource}
     */
    private static function socketPair(): array
    {
        # Windows has no AF_UNIX socket pairs, Linux has no AF_INET ones.
        $domain = PHP_OS_FAMILY === 'Windows' ? STREAM_PF_INET : STREAM_PF_UNIX;

        return stream_socket_pair($domain, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    }

    /**
     * @param resource $stream
     */
    private static function waitReadable($stream): void
    {
        # On Windows the pair is a loopback TCP connection, so written data may arrive a moment later.
        $read = [$stream];
        $write = $except = null;
        stream_select($read, $write, $except, 1);
    }
}
