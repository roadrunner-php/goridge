<?php

declare(strict_types=1);

namespace Spiral\Goridge\RPC\Codec;

use MessagePack\MessagePack;
use Spiral\Goridge\Frame;
use Spiral\Goridge\RPC\CodecInterface;

/**
 * @psalm-type PackHandler = \Closure(mixed): string
 * @psalm-type UnpackHandler = \Closure(string, mixed|null): mixed
 *
 * @deprecated RoadRunner 3 (Goridge v4) does not support MessagePack: the server drops the connection
 *             on a MessagePack frame. Use the default {@see JsonCodec} or {@see ProtobufCodec} instead.
 *             The codec will be removed in the next major version.
 */
final class MsgpackCodec implements CodecInterface
{
    private const DEPRECATION = 'Spiral\Goridge\RPC\Codec\MsgpackCodec is deprecated: RoadRunner 3 (Goridge v4)'
        . ' does not support MessagePack. Use JsonCodec or ProtobufCodec instead.'
        . ' The codec will be removed in the next major version.';

    /**
     * @var PackHandler
     * @psalm-suppress PropertyNotSetInConstructor Reason: Initialized via {@see initPacker()}
     */
    private \Closure $pack;

    /**
     * @var UnpackHandler
     * @psalm-suppress PropertyNotSetInConstructor Reason: Initialized via {@see initPacker()}
     */
    private \Closure $unpack;

    /**
     * Constructs extension using native or fallback implementation.
     */
    public function __construct()
    {
        // Silenced: the notice reaches only error handlers that collect deprecations, the default one stays quiet.
        @\trigger_error(self::DEPRECATION, \E_USER_DEPRECATED);

        $this->initPacker();
    }

    public function getIndex(): int
    {
        return Frame::CODEC_MSGPACK;
    }

    public function encode(mixed $payload): string
    {
        return ($this->pack)($payload);
    }

    public function decode(string $payload, mixed $options = null): mixed
    {
        return ($this->unpack)($payload, $options);
    }

    /**
     * Init pack and unpack functions.
     *
     * @psalm-suppress MixedArgument
     */
    private function initPacker(): void
    {
        // Is native extension supported
        if (\function_exists('msgpack_pack') && \function_exists('msgpack_unpack')) {
            $this->pack = static fn($payload): string => msgpack_pack($payload);

            $this->unpack = static function (string $payload, $options = null) {
                if ($options !== null) {
                    return msgpack_unpack($payload, $options);
                }

                return msgpack_unpack($payload);
            };

            return;
        }

        // Is composer's library supported
        if (\class_exists(MessagePack::class)) {
            $this->pack = static fn(mixed $payload): string => MessagePack::pack($payload);

            $this->unpack = static fn(string $payload, $options = null): mixed => MessagePack::unpack($payload, $options);

            return;
        }

        throw new \LogicException('Could not initialize codec, please install msgpack extension or library');
    }
}
