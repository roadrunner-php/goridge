<?php

/**
 * Dead simple, high performance, drop-in bridge to Golang RPC with zero dependencies
 *
 * @author Wolfy-J
 */

declare(strict_types=1);

namespace Spiral\Goridge\RPC\Codec;

use Spiral\Goridge\Frame;
use Spiral\Goridge\RPC\CodecInterface;
use Spiral\Goridge\RPC\Exception\CodecException;

final class JsonCodec implements CodecInterface
{
    /**
     * Coded index, uniquely identified by remote server.
     *
     * @return int
     */
    public function getIndex(): int
    {
        return Frame::CODEC_JSON;
    }

    /**
     * {@inheritDoc}
     */
    public function encode($payload): string
    {
        try {
            $result = \json_encode($payload, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new CodecException(\sprintf('Json encode: %s', $e->getMessage()), (int)$e->getCode(), $e);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function decode(string $payload)
    {
        try {
            return \json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new CodecException(\sprintf('Json decode: %s', $e->getMessage()), (int)$e->getCode(), $e);
        }
    }
}
