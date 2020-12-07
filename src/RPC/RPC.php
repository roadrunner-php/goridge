<?php

/**
 * Dead simple, high performance, drop-in bridge to Golang RPC with zero dependencies
 *
 * @author Wolfy-J
 */

declare(strict_types=1);

namespace Spiral\Goridge\RPC;

use Spiral\Goridge\Exception\GoridgeException;
use Spiral\Goridge\Frame;
use Spiral\Goridge\RelayInterface as Relay;
use Spiral\Goridge\RPC\Codec\RawCodec;
use Spiral\Goridge\RPC\Exception\RPCException;
use Spiral\Goridge\StringableRelayInterface;

class RPC implements RPCInterface
{
    private Relay          $relay;
    private CodecInterface $codec;
    private ?string        $service;

    /** @var positive-int */
    private static int $seq = 0;

    /**
     * @param Relay               $relay
     * @param CodecInterface|null $codec
     */
    public function __construct(Relay $relay, CodecInterface $codec = null)
    {
        $this->relay = $relay;
        $this->codec = $codec ?? new RawCodec();
    }

    /**
     * Create RPC instance with service specific prefix.
     *
     * @param string $service
     * @return RPCInterface
     */
    public function withServicePrefix(string $service): RPCInterface
    {
        $rpc = clone $this;
        $rpc->service = $service;

        return $rpc;
    }

    /**
     * Create RPC instance with service specific codec.
     *
     * @param CodecInterface $codec
     * @return RPCInterface
     */
    public function withCodec(CodecInterface $codec): RPCInterface
    {
        $rpc = clone $this;
        $rpc->codec = $codec;

        return $rpc;
    }

    /**
     * Invoke remove RoadRunner service method using given payload (depends on codec).
     *
     * @param string $method
     * @param mixed  $payload
     * @return mixed
     * @throws GoridgeException
     * @throws RPCException
     */
    public function call(string $method, $payload)
    {
        $this->relay->send(...$this->packRequest($method, $payload));

        // wait for the header confirmation
        $header = $this->relay->waitFrame();

        if (!($header->flags & Frame::CONTROL)) {
            throw new Exception\RPCException('rpc response header is missing');
        }

        $rpc = unpack('Ps', substr($header->body, -8));
        $rpc['m'] = substr($header->body, 0, -8);

        if ($rpc['m'] !== $method || $rpc['s'] !== self::$seq) {
            throw new Exception\RPCException(
                sprintf(
                    'rpc method call, expected %s:%d, got %s%d',
                    $method,
                    self::$seq,
                    $rpc['m'],
                    $rpc['s']
                )
            );
        }

        self::$seq++;

        $response = $this->relay->waitFrame();

        return $this->decodeResponse($response->body, $response->flags);
    }

    /**
     * @param string $body
     * @param int    $flags
     * @return mixed
     *
     * @throws Exception\ServiceException
     */
    private function decodeResponse(string $body, int $flags)
    {
        if ($flags & Frame::ERROR) {
            throw new Exception\ServiceException(
                sprintf(
                    "error '$body' on '%s'",
                    $this->relay instanceof StringableRelayInterface ? (string) $this->relay : get_class($this->relay)
                )
            );
        }

        return $this->codec->decode($body);
    }

    /**
     * @param string $method
     * @param mixed  $payload
     * @return Frame
     */
    private function packRequest(string $method, $payload): array
    {
        if ($this->service !== null) {
            $method = $this->service . '.' . ucfirst($method);
        }

        return [
            new Frame($method, pack('P', self::$seq), Frame::CONTROL),
            new Frame($this->codec->encode($payload), null, Frame::CONTROL | $this->codec->getIndex())
        ];
    }

    /**
     * @param string              $connection
     * @param CodecInterface|null $codec
     * @return RPCInterface
     */
    public static function create(string $connection, CodecInterface $codec = null): RPCInterface
    {
        $relay = \Spiral\Goridge\Relay::create($connection);
        return new static($relay, $codec);
    }
}
