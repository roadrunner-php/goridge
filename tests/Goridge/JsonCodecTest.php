<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests;

use Spiral\Goridge\Frame;
use Spiral\Goridge\RPC\Codec\JsonCodec;
use Spiral\Goridge\RPC\Exception\CodecException;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(JsonCodec::class)]
final class JsonCodecTest
{
    public function testIndex(): void
    {
        Assert::same((new JsonCodec())->getIndex(), Frame::CODEC_JSON);
    }

    public function testRoundTrip(): void
    {
        $codec = new JsonCodec();
        $payload = ['name' => 'goridge', 'tags' => ['rpc', 'php'], 'nested' => ['id' => 1]];

        Assert::same($codec->decode($codec->encode($payload)), $payload);
    }

    public function testDecodePassesIntegerOptionsAsFlags(): void
    {
        $decoded = (new JsonCodec())->decode('{"id":12345678901234567890}', \JSON_BIGINT_AS_STRING);

        Assert::same($decoded, ['id' => '12345678901234567890']);
    }

    public function testEncodeFailure(): never
    {
        Expect::exception(CodecException::class)->withMessageContaining('Json encode: ');

        (new JsonCodec())->encode("\xB1\x31");
    }

    public function testDecodeFailure(): never
    {
        Expect::exception(CodecException::class)
            ->withMessageContaining('Json decode: ')
            ->withPrevious(\JsonException::class);

        (new JsonCodec())->decode('{"broken":');
    }
}
