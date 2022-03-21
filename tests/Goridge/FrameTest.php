<?php

namespace Goridge;

use PHPUnit\Framework\TestCase;
use Spiral\Goridge\Frame;

class FrameTest extends TestCase
{
    public function testByte10DefaultValue(): void
    {
        $frame = new Frame('');
        $this->assertSame(0, $frame->byte10);
    }

    public function testByte10DefaultValuePacked(): void
    {
        $string = Frame::packFrame(new Frame(''));
        $this->assertSame(\chr(0), $string[10]);
    }

    public function testByte10StreamedOutputPacked(): void
    {
        $frame = new Frame('');
        $frame->byte10 = Frame::BYTE10_STREAM;
        $string = Frame::packFrame($frame);
        $this->assertSame(Frame::BYTE10_STREAM, \ord($string[10]));
    }
}
