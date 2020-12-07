<?php

/**
 * Dead simple, high performance, drop-in bridge to Golang RPC with zero dependencies
 *
 * @author Wolfy-J
 */

declare(strict_types=1);

namespace Spiral\Goridge;

/**
 * Communicates with remote server/client over streams using byte payload:
 *
 * [ prefix       ][ payload                               ]
 * [ 1+8+8 bytes  ][ message length|LE ][message length|BE ]
 *
 * prefix:
 * [ flag       ][ message length, unsigned int 64bits, LittleEndian ]
 */
class StreamRelay extends Relay
{
    /** @var resource */
    private $in;

    /** @var resource */
    private $out;

    /**
     * Example:
     * $relay = new StreamRelay(STDIN, STDOUT);
     *
     * @param resource $in  Must be readable.
     * @param resource $out Must be writable.
     *
     * @throws Exception\InvalidArgumentException
     */
    public function __construct($in, $out)
    {
        if (!is_resource($in) || get_resource_type($in) !== 'stream') {
            throw new Exception\InvalidArgumentException('expected a valid `in` stream resource');
        }

        if (!$this->assertReadable($in)) {
            throw new Exception\InvalidArgumentException('resource `in` must be readable');
        }

        if (!is_resource($out) || get_resource_type($out) !== 'stream') {
            throw new Exception\InvalidArgumentException('expected a valid `out` stream resource');
        }

        if (!$this->assertWritable($out)) {
            throw new Exception\InvalidArgumentException('resource `out` must be writable');
        }

        $this->in = $in;
        $this->out = $out;
    }

    /**
     * @return Frame
     */
    public function waitFrame(): ?Frame
    {
        // todo: implement new protocol
        $msg = new Frame(null, null, 0);

        $prefix = $this->fetchPrefix();
        $msg->flags = $prefix['flags'];

        if ($prefix['size'] !== 0) {
            $msg->body = '';
            $leftBytes = $prefix['size'];

            //Add ability to write to stream in a future
            while ($leftBytes > 0) {
                $buffer = fread($this->in, min($leftBytes, self::BUFFER_SIZE));
                if ($buffer === false) {
                    throw new Exception\TransportException('error reading payload from the stream');
                }

                $msg->body .= $buffer;
                $leftBytes -= strlen($buffer);
            }
        }

        return $msg;
    }

    /**
     * @param Frame ...$frame
     */
    public function send(Frame ...$frame): void
    {
        $body = '';
        foreach ($frame as $f) {
            $body = self::packFrame($f);
        }

        if (fwrite($this->out, $body, strlen($body)) === false) {
            throw new Exception\TransportException('unable to write payload to the stream');
        }
    }

    /**
     * @return array Prefix [flag, length]
     *
     * @throws Exception\PrefixException
     */
    private function fetchPrefix(): array
    {
        // todo: update protocol
        $prefixBody = fread($this->in, 17);
        if ($prefixBody === false) {
            throw new Exception\PrefixException('unable to read prefix from the stream');
        }

        $result = unpack('Cflags/Psize/Jrevs', $prefixBody);
        if (!is_array($result)) {
            throw new Exception\PrefixException('invalid prefix');
        }

        if ($result['size'] !== $result['revs']) {
            throw new Exception\PrefixException('invalid prefix (checksum)');
        }

        return $result;
    }

    /**
     * Checks if stream is readable.
     *
     * @param resource $stream
     *
     * @return bool
     */
    private function assertReadable($stream): bool
    {
        $meta = stream_get_meta_data($stream);

        return in_array($meta['mode'], ['r', 'rb', 'r+', 'rb+', 'w+', 'wb+', 'a+', 'ab+', 'x+', 'c+', 'cb+'], true);
    }

    /**
     * Checks if stream is writable.
     *
     * @param resource $stream
     *
     * @return bool
     */
    private function assertWritable($stream): bool
    {
        $meta = stream_get_meta_data($stream);

        return !in_array($meta['mode'], ['r', 'rb'], true);
    }
}
