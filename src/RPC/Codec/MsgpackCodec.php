<?php

/**
 * Dead simple, high performance, drop-in bridge to Golang RPC with zero dependencies
 *
 * @author Wolfy-J
 */

declare(strict_types=1);

namespace Spiral\Goridge\RPC\Codec;

use MessagePack\MessagePack;
use Spiral\Goridge\Frame;
use Spiral\Goridge\RPC\CodecInterface;

/**
 * @psalm-type PackHandler = \Closure(mixed): string
 * @psalm-type UnpackHandler = \Closure(string): mixed
 */
final class MsgpackCodec implements CodecInterface
{
    /**
     * @var PackHandler
     * @psalm-suppress PropertyNotSetInConstructor Reason: Initialized via private method
     */
    private \Closure $pack;

    /**
     * @var UnpackHandler
     * @psalm-suppress PropertyNotSetInConstructor Reason: Initialized via private method
     */
    private \Closure $unpack;

    /**
     * Constructs extension using native or fallback implementation.
     */
    public function __construct()
    {
        $this->initPacker();
    }

    /**
     * Coded index, uniquely identified by remote server.
     *
     * @return int
     */
    public function getIndex(): int
    {
        return Frame::CODEC_MSGPACK;
    }

    /**
     * @param mixed $payload
     * @return string
     */
    public function encode($payload): string
    {
        return ($this->pack)($payload);
    }

    /**
     * @param string $payload
     * @return mixed
     */
    public function decode(string $payload)
    {
        return ($this->unpack)($payload);
    }

    /**
     * Init pack and unpack functions.
     */
    private function initPacker(): void
    {
        // Is native extension supported
        if (\function_exists('msgpack_pack') && \function_exists('msgpack_unpack')) {
            $this->pack = static function ($payload): string {
                return msgpack_pack($payload);
            };

            $this->unpack = static function (string $payload) {
                return msgpack_unpack($payload);
            };

            return;
        }

        // Is composer's library supported
        if (\class_exists(MessagePack::class)) {
            $this->pack = static function ($payload): string {
                return MessagePack::pack($payload);
            };

            $this->unpack = static function (string $payload) {
                return MessagePack::unpack($payload);
            };
        }

        throw new \LogicException('Could not initialize codec, please install msgpack extension or library');
    }
}
