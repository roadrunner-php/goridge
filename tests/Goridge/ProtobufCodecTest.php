<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests;

use Google\Protobuf\StringValue;
use Spiral\Goridge\Frame;
use Spiral\Goridge\RPC\Codec\ProtobufCodec;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(ProtobufCodec::class)]
final class ProtobufCodecTest
{
    public function testIndex(): void
    {
        Assert::same((new ProtobufCodec())->getIndex(), Frame::CODEC_PROTO);
    }

    public function testEncodesMessage(): void
    {
        $message = new StringValue(['value' => 'hello']);

        Assert::same((new ProtobufCodec())->encode($message), $message->serializeToString());
    }

    public function testEncodePassesSerializedPayloadThrough(): void
    {
        $serialized = (new StringValue(['value' => 'hello']))->serializeToString();

        Assert::same((new ProtobufCodec())->encode($serialized), $serialized);
    }

    public function testDecodesIntoNewMessageOfGivenClass(): void
    {
        $serialized = (new StringValue(['value' => 'hello']))->serializeToString();

        $decoded = (new ProtobufCodec())->decode($serialized, StringValue::class);

        Assert::instanceOf($decoded, StringValue::class);
        Assert::same($decoded->getValue(), 'hello');
    }

    public function testDecodesIntoGivenMessage(): void
    {
        $serialized = (new StringValue(['value' => 'hello']))->serializeToString();
        $target = new StringValue();

        $decoded = (new ProtobufCodec())->decode($serialized, $target);

        Assert::same($decoded, $target);
        Assert::same($target->getValue(), 'hello');
    }

    public function testDecodeWithoutMessageReturnsPayload(): void
    {
        $serialized = (new StringValue(['value' => 'hello']))->serializeToString();
        $codec = new ProtobufCodec();

        Assert::same($codec->decode($serialized), $serialized);
        Assert::same($codec->decode($serialized, \stdClass::class), $serialized);
    }
}
