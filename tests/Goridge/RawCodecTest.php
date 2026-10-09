<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests;

use Spiral\Goridge\Frame;
use Spiral\Goridge\RPC\Codec\RawCodec;
use Spiral\Goridge\RPC\Exception\CodecException;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(RawCodec::class)]
final class RawCodecTest
{
    public function testIndex(): void
    {
        Assert::same((new RawCodec())->getIndex(), Frame::CODEC_RAW);
    }

    public function testPassesStringsThrough(): void
    {
        $codec = new RawCodec();
        $payload = \random_bytes(32);

        Assert::same($codec->encode($payload), $payload);
        Assert::same($codec->decode($payload, 'ignored'), $payload);
    }

    public function testRejectsNonStringPayload(): never
    {
        Expect::exception(CodecException::class)
            ->withMessage('Only string payloads can be send using RawCodec, array given');

        (new RawCodec())->encode(['not', 'a', 'string']);
    }
}
