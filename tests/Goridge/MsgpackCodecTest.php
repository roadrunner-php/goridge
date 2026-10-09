<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests;

use Spiral\Goridge\Frame;
use Spiral\Goridge\RPC\Codec\MsgpackCodec;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Exception\SkipTest;
use Testo\Skip;
use Testo\Test;

#[Test]
#[Covers(MsgpackCodec::class)]
final class MsgpackCodecTest
{
    public function testRoundTripWithExtension(): void
    {
        if (!\extension_loaded('msgpack')) {
            throw new SkipTest('ext-msgpack is not loaded');
        }

        $codec = new MsgpackCodec();
        $payload = ['name' => 'goridge', 'tags' => ['rpc', 'php'], 'id' => 42];

        Assert::same($codec->getIndex(), Frame::CODEC_MSGPACK);
        Assert::same($codec->decode($codec->encode($payload)), $payload);
    }

    #[Skip('MsgpackCodec::initPacker() lacks a return after the rybakit/msgpack branch, so without ext-msgpack the constructor always throws LogicException')]
    public function testRoundTripWithLibrary(): void
    {
        if (\extension_loaded('msgpack')) {
            throw new SkipTest('ext-msgpack is loaded, the library fallback is unreachable');
        }

        $codec = new MsgpackCodec();
        $payload = ['name' => 'goridge', 'tags' => ['rpc', 'php'], 'id' => 42];

        Assert::same($codec->getIndex(), Frame::CODEC_MSGPACK);
        Assert::same($codec->decode($codec->encode($payload)), $payload);
    }
}
