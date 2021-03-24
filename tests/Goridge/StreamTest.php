<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Goridge;

use PHPUnit\Framework\TestCase;
use Spiral\Goridge\Frame;
use Spiral\Goridge\StreamRelay;

class StreamTest extends TestCase
{
    public function testMessagePassing(): void
    {
        $resource = fopen('php://memory', 'r+');

        $relay = new StreamRelay($resource, $resource);

        $in = new Frame('hello world', [100, 9001], Frame::CODEC_RAW);
        $relay->send($in);

        fseek($resource, 0);

        $this->assertEquals($in, $relay->waitFrame());
    }
}
