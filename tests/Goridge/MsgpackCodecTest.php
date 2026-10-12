<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests;

use Spiral\Goridge\Frame;
use Spiral\Goridge\RPC\Codec\MsgpackCodec;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Exception\SkipTest;
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

    public function testConstructorTriggersDeprecation(): void
    {
        $notices = [];
        \set_error_handler(static function (int $level, string $message) use (&$notices): bool {
            $notices[] = [$level, $message];
            return true;
        });

        try {
            new MsgpackCodec();
        } finally {
            \restore_error_handler();
        }

        Assert::count($notices, 1);
        Assert::same($notices[0][0], \E_USER_DEPRECATED);
        Assert::string($notices[0][1])->contains('MsgpackCodec is deprecated');
    }
}
