<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests;

use Spiral\Goridge\Frame;
use Spiral\Goridge\StreamRelay;
use Testo\Assert;
use Testo\Test;

#[Test]
final class StreamTest
{
    public function testMessagePassing(): void
    {
        $resource = fopen('php://memory', 'rb+');

        $relay = new StreamRelay($resource, $resource);

        $in = new Frame('hello world', [100, 9001], Frame::CODEC_RAW);
        $relay->send($in);

        fseek($resource, 0);

        Assert::equals($relay->waitFrame(), $in);
    }
}
