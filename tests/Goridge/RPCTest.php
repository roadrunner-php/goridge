<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests;

use Spiral\Goridge\Exception\RelayException;
use Spiral\Goridge\Frame;
use Spiral\Goridge\RPC\AbstractRPC;
use Spiral\Goridge\RPC\Codec\RawCodec;
use Spiral\Goridge\RPC\Exception\RPCException;
use Spiral\Goridge\RPC\Exception\ServiceException;
use Spiral\Goridge\RPC\RPC as GoridgeRPC;
use Spiral\Goridge\Tests\Stub\FakeRelay;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

/**
 * RPC protocol handling against an in-process relay; {@see RPC} covers the same client against the Go server.
 */
#[Test]
#[Covers(GoridgeRPC::class)]
#[Covers(AbstractRPC::class)]
final class RPCTest
{
    public function testSendsMethodAndPayloadInOneFrame(): void
    {
        $relay = FakeRelay::echo();
        $rpc = new GoridgeRPC($relay);

        $result = $rpc->call('Service.Echo', ['key' => 'value']);

        Assert::same($result, ['key' => 'value']);
        Assert::count($relay->sent, 1);
        Assert::same($relay->sent[0]->payload, 'Service.Echo{"key":"value"}');
        Assert::same($relay->sent[0]->options[1], \strlen('Service.Echo'));
        Assert::same($relay->sent[0]->flags, Frame::CODEC_JSON);
    }

    public function testServicePrefixCapitalizesMethod(): void
    {
        $relay = FakeRelay::echo();
        $rpc = (new GoridgeRPC($relay))->withServicePrefix('Service');

        $rpc->call('echo', 'hi');

        Assert::string($relay->sent[0]->payload)->startsWith('Service.Echo');
    }

    public function testSequenceAdvancesWithEveryCall(): void
    {
        $relay = FakeRelay::echo();
        $rpc = new GoridgeRPC($relay);

        $rpc->call('Service.Echo', 1);
        $rpc->call('Service.Echo', 2);

        Assert::same($relay->sent[1]->options[0], $relay->sent[0]->options[0] + 1);
    }

    public function testWithCodecKeepsOriginalInstance(): void
    {
        $relay = FakeRelay::echo();
        $rpc = new GoridgeRPC($relay);

        $raw = $rpc->withCodec(new RawCodec());
        $raw->call('Service.Echo', 'raw');
        $rpc->call('Service.Echo', 'json');

        Assert::same($relay->sent[0]->flags, Frame::CODEC_RAW);
        Assert::same($relay->sent[1]->flags, Frame::CODEC_JSON);
    }

    public function testRejectsResponseWithoutOptions(): never
    {
        $rpc = new GoridgeRPC(new FakeRelay(static fn(Frame $request): Frame => new Frame($request->payload)));

        Expect::exception(RPCException::class)->withMessage('Invalid RPC frame, options missing');

        $rpc->call('Service.Echo', 'hi');
    }

    public function testRejectsResponseWithForeignSequence(): never
    {
        $rpc = new GoridgeRPC(new FakeRelay(static fn(Frame $request): Frame => new Frame(
            $request->payload,
            [$request->options[0] + 1, $request->options[1]],
        )));

        Expect::exception(RPCException::class)->withMessage('Invalid RPC frame, sequence mismatch');

        $rpc->call('Service.Echo', 'hi');
    }

    public function testErrorFrameNamesRelayClass(): never
    {
        $rpc = new GoridgeRPC(new FakeRelay(static fn(Frame $request): Frame => new Frame(
            'Service.Echo' . 'boom',
            $request->options,
            Frame::ERROR,
        )));

        Expect::exception(ServiceException::class)
            ->withMessage(\sprintf("Error 'boom' on %s", FakeRelay::class));

        $rpc->call('Service.Echo', 'hi');
    }

    public function testCreateBuildsRelayFromConnectionString(): never
    {
        # Relative: the DSN parser takes a colon as the port separator, so `C:\...` cannot be used.
        $path = 'goridge-missing-' . \bin2hex(\random_bytes(4)) . '.sock';
        $rpc = GoridgeRPC::create("unix://{$path}");

        Expect::exception(RelayException::class)->withMessage("Unable to establish connection unix://{$path}");

        $rpc->call('Service.Ping', 'ping');
    }
}
