<?php

namespace Spiral\Goridge\Tests;

use Spiral\Goridge\Frame;
use Testo\Assert;
use Testo\Test;

#[Test]
final class FrameTest
{
    public function testByte10DefaultValue(): void
    {
        $frame = new Frame('');
        Assert::same($frame->byte10, 0);
    }

    public function testByte10DefaultValuePacked(): void
    {
        $string = Frame::packFrame(new Frame(''));
        Assert::same($string[10], \chr(0));
    }

    public function testByte10StreamedOutputPacked(): void
    {
        $frame = new Frame('');
        $frame->byte10 = Frame::BYTE10_STREAM;
        $string = Frame::packFrame($frame);
        Assert::same(\ord($string[10]), Frame::BYTE10_STREAM);
    }
}
