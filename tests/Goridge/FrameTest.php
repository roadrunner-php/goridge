<?php

namespace Spiral\Goridge\Tests;

use Spiral\Goridge\Exception\InvalidArgumentException;
use Spiral\Goridge\Frame;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(Frame::class)]
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

    public function testPackedFrameRestoresAllFields(): void
    {
        $frame = new Frame('payload', [7, 4294967295], Frame::CODEC_JSON | Frame::ERROR);
        $frame->byte10 = Frame::BYTE10_STREAM | Frame::BYTE10_STOP;
        $frame->byte11 = 42;
        $packed = Frame::packFrame($frame);

        $header = Frame::readHeader($packed);
        $restored = Frame::initFrame($header, \substr($packed, 12));

        Assert::same($header, [Frame::CODEC_JSON | Frame::ERROR, 2, 7, 3, 42]);
        Assert::equals($restored, $frame);
    }

    public function testNullPayloadIsPackedAsEmpty(): void
    {
        $packed = Frame::packFrame(new Frame(null));

        Assert::same(\strlen($packed), 12);
        Assert::same(Frame::readHeader($packed)[2], 0);
    }

    public function testSetFlagCombinesFlags(): void
    {
        $frame = new Frame('', flags: Frame::CODEC_RAW);

        $frame->setFlag(Frame::ERROR, Frame::CONTROL);

        Assert::same($frame->flags, Frame::CODEC_RAW | Frame::ERROR | Frame::CONTROL);
        Assert::true($frame->hasFlag(Frame::ERROR));
        Assert::false($frame->hasFlag(Frame::CODEC_JSON));
    }

    public function testSetFlagRejectsNonByteValue(): never
    {
        $frame = new Frame('');

        Expect::exception(InvalidArgumentException::class)->withMessage('Flags can be byte only');

        $frame->setFlag(Frame::CONTROL, 256);
    }

    public function testHasFlagRejectsNonByteValue(): never
    {
        $frame = new Frame('');

        Expect::exception(InvalidArgumentException::class)->withMessage('Flags can be byte only');

        $frame->hasFlag(256);
    }

    public function testSetOptionsReplacesOptions(): void
    {
        $frame = new Frame('', [1, 2, 3]);

        $frame->setOptions(4, 5);

        Assert::same($frame->options, [4, 5]);
    }
}
