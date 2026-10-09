<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests;

use Spiral\Goridge\Frame;
use Spiral\Goridge\RPC\AbstractRPC;
use Spiral\Goridge\RPC\Exception\RPCException;
use Spiral\Goridge\RPC\Exception\ServiceException;
use Spiral\Goridge\RPC\MultiRPC as GoridgeMultiRPC;
use Spiral\Goridge\StreamRelay;
use Spiral\Goridge\Tests\Stub\FakeRelay;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

/**
 * MultiRPC protocol handling against in-process relays; {@see MultiRPC} covers it against the Go server.
 */
#[Test]
#[Covers(GoridgeMultiRPC::class)]
#[Covers(AbstractRPC::class)]
final class MultiRPCTest
{
    public function testCallDecodesResponse(): void
    {
        $rpc = new GoridgeMultiRPC([FakeRelay::echo()]);

        Assert::same($rpc->call('Service.Echo', ['key' => 'value']), ['key' => 'value']);
    }

    public function testRejectsResponseWithoutOptions(): never
    {
        $rpc = new GoridgeMultiRPC([new FakeRelay(static fn(Frame $request): Frame => new Frame($request->payload))]);

        Expect::exception(RPCException::class)->withMessage('Invalid RPC frame, options missing');

        $rpc->call('Service.Echo', 'hi');
    }

    public function testRejectsResponseWithForeignSequence(): never
    {
        $rpc = new GoridgeMultiRPC([new FakeRelay(static fn(Frame $request): Frame => new Frame(
            $request->payload,
            [$request->options[0] + 1, $request->options[1]],
        ))]);

        Expect::exception(RPCException::class)->withMessage('Invalid RPC frame, sequence mismatch');

        $rpc->call('Service.Echo', 'hi');
    }

    public function testRelayStaysUsableAfterInvalidResponse(): void
    {
        $broken = true;
        $rpc = new GoridgeMultiRPC([new FakeRelay(static function (Frame $request) use (&$broken): Frame {
            return $broken ? new Frame($request->payload) : new Frame($request->payload, $request->options);
        })]);

        try {
            $rpc->call('Service.Echo', 'first');
        } catch (RPCException) {
        }
        $broken = false;

        Assert::same($rpc->call('Service.Echo', 'second'), 'second');
    }

    public function testErrorFrameNamesRelayClass(): never
    {
        $rpc = new GoridgeMultiRPC([new FakeRelay(static fn(Frame $request): Frame => new Frame(
            'Service.Echo' . 'boom',
            $request->options,
            Frame::ERROR,
        ))]);

        Expect::exception(ServiceException::class)
            ->withMessage(\sprintf("Error 'boom' on %s", FakeRelay::class));

        $rpc->call('Service.Echo', 'hi');
    }

    public function testGetResponsesWithoutSequencesYieldsNothing(): void
    {
        $rpc = new GoridgeMultiRPC([FakeRelay::echo()]);

        Assert::same(\iterator_to_array($rpc->getResponses([])), []);
    }

    public function testGetResponsesRejectsUnknownSequence(): never
    {
        $rpc = new GoridgeMultiRPC([FakeRelay::echo()]);

        Expect::exception(RPCException::class)->withMessage(GoridgeMultiRPC::ERR_INVALID_SEQ_NUMBER);

        \iterator_to_array($rpc->getResponses([\PHP_INT_MAX]));
    }

    public function testRejectsRelaysOfAnotherTypeWhileAllRelaysAreBusy(): never
    {
        $rpc = new GoridgeMultiRPC([FakeRelay::echo()]);
        $rpc->callIgnoreResponse('Service.Echo', 'busy');

        Expect::exception(RPCException::class)->withMessageContaining('all relays of the same type');

        new GoridgeMultiRPC([new StreamRelay(STDIN, STDOUT)]);
    }

    /**
     * The relay pool is static and shared with the server-backed MultiRPC tests.
     */
    #[BeforeTest]
    #[AfterTest]
    public function resetRelayPool(): void
    {
        foreach (['freeRelays', 'occupiedRelays', 'seqToRelayMap', 'asyncResponseBuffer'] as $property) {
            (new \ReflectionProperty(GoridgeMultiRPC::class, $property))->setValue(null, []);
        }
    }
}
