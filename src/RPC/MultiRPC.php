<?php

declare(strict_types=1);

namespace Spiral\Goridge\RPC;

use RuntimeException;
use Spiral\Goridge\Frame;
use Spiral\Goridge\MultiRelayHelper;
use Spiral\Goridge\Relay;
use Spiral\Goridge\RelayInterface;
use Spiral\Goridge\RPC\Codec\JsonCodec;
use Spiral\Goridge\RPC\Exception\RPCException;
use Spiral\Goridge\SocketRelay;

class MultiRPC extends AbstractRPC implements AsyncRPCInterface
{
    /**
     * @var RelayInterface[]
     */
    private array $freeRelays = [];

    /**
     * @var RelayInterface[]
     */
    private array $occupiedRelays = [];

    /**
     * @var RelayInterface[]
     */
    private array $occupiedRelaysIgnoreResponse = [];

    /**
     * @var array<positive-int, RelayInterface>
     */
    private array $seqToRelayMap = [];

    /**
     * Map of seq to response Frame
     * Should only really need to be used in cases of high amounts of traffic
     *
     * @var array<positive-int, Frame>
     */
    private array $asyncResponseBuffer = [];

    public function __construct(
        /** @var RelayInterface[] $relays */
        array          $relays,
        CodecInterface $codec = new JsonCodec()
    )
    {
        $this->freeRelays = $relays;
        parent::__construct($codec);
    }

    public static function create(string $connection, int $count = 50, CodecInterface $codec = new JsonCodec()): self
    {
        assert($count > 0);
        $relays = [];

        for ($i = 0; $i < $count; $i++) {
            $relays[] = Relay::create($connection);
            if ($relays[$i] instanceof SocketRelay) {
                // Force connect
                $relays[$i]->connect();
            }
        }

        return new self($relays, $codec);
    }


    public function call(string $method, mixed $payload, mixed $options = null): mixed
    {
        // Avoid pushing and popping if we can
        if (count($this->freeRelays) > 0) {
            $relay = $this->freeRelays[0];
        } else {
            $relay = $this->getNextFreeRelay();
            $this->freeRelays[] = $relay;
        }

        $relay->send($this->packFrame($method, $payload));

        // wait for the frame confirmation
        $frame = $relay->waitFrame();

        if (count($frame->options) !== 2) {
            throw new RPCException('Invalid RPC frame, options missing');
        }

        if ($frame->options[0] !== self::$seq) {
            throw new RPCException('Invalid RPC frame, sequence mismatch');
        }

        self::$seq++;

        return $this->decodeResponse($frame, $relay, $options);
    }

    public function callIgnoreResponse(string $method, mixed $payload): void
    {
        $relay = $this->getNextFreeRelay();
        $relay->send($this->packFrame($method, $payload));
        $this->occupiedRelaysIgnoreResponse[] = $relay;
        self::$seq++;
    }

    public function callAsync(string $method, mixed $payload): int
    {
        $relay = $this->getNextFreeRelay();
        $relay->send($this->packFrame($method, $payload));
        $this->occupiedRelays[] = $relay;
        $seq = self::$seq;
        $this->seqToRelayMap[$seq] = $relay;
        self::$seq++;
        return $seq;
    }

    public function hasResponse(int $seq): bool
    {
        if (isset($this->asyncResponseBuffer[$seq])) {
            return true;
        }

        if ($this->seqToRelayMap[$seq]->hasFrame()) {
            return true;
        }

        return false;
    }

    public function hasAnyResponse(array $seqs): array
    {
        $relays = [];
        $relayIndexToSeq = [];
        $seqsWithResponse = [];

        foreach ($seqs as $seq) {
            if (isset($this->asyncResponseBuffer[$seq])) {
                $seqsWithResponse[] = $seq;
            } elseif (isset($this->seqToRelayMap[$seq])) {
                $relays[] = $this->seqToRelayMap[$seq];
                $relayIndexToSeq[count($relays) - 1] = $seq;
            }
        }

        $index = MultiRelayHelper::findRelayWithMessage($relays);

        if ($index === false) {
            return $seqsWithResponse;
        }

        if (!is_array($index)) {
            $index = [$index];
        }

        return [...$seqsWithResponse, array_map(fn($in) => $relayIndexToSeq[$in], $index)];
    }

    public function getResponse(int $seq, mixed $options = null): mixed
    {
        if (($relay = $this->seqToRelayMap[$seq] ?? null) !== null) {
            if (($frame = $this->asyncResponseBuffer[$seq] ?? null) !== null) {
                unset($this->asyncResponseBuffer[$seq]);
                /**
                 * We can assume through @see MultiRPC::getNextFreeRelay() that a relay whose response is already
                 * in this buffer has also been added to freeRelays (or is otherwise occupied).
                 * Thus we only re-add (and do so without searching for it first) if we don't have the response yet.
                 */
            } else {
                $frame = $relay->waitFrame();
                if (($index = array_search($relay, $this->occupiedRelays, true)) !== false) {
                    $this->freeRelays[] = array_slice($this->occupiedRelays, $index, 1)[0];
                }
            }
        } else {
            throw new RPCException('Invalid Seq, unknown');
        }

        if (count($frame->options) !== 2) {
            throw new RPCException('Invalid RPC frame, options missing');
        }

        if ($frame->options[0] !== $seq) {
            throw new RPCException('Invalid RPC frame, sequence mismatch');
        }

        return $this->decodeResponse($frame, $relay, $options);
    }

    private function getNextFreeRelay(): RelayInterface
    {
        if (count($this->freeRelays) > 0) {
            return array_pop($this->freeRelays);
        }

        if (count($this->occupiedRelaysIgnoreResponse) > 0) {
            $index = MultiRelayHelper::findRelayWithMessage($this->occupiedRelaysIgnoreResponse);

            // Flush all available relays
            if (is_array($index)) {
                $occupiedRelaysIgnoreResponse = [];
                $indexKeyed = array_flip($index);
                foreach ($this->occupiedRelaysIgnoreResponse as $relayIndex => $relay) {
                    if (isset($indexKeyed[$relayIndex])) {
                        $relay->waitFrame();
                        $this->freeRelays[] = $relay;
                    } else {
                        $occupiedRelaysIgnoreResponse[] = $relay;
                    }
                }

                $this->occupiedRelaysIgnoreResponse = $occupiedRelaysIgnoreResponse;
                return array_pop($this->freeRelays);
            } elseif ($index !== false) {
                $relay = array_slice($this->occupiedRelaysIgnoreResponse, $index, 1)[0];
                $relay->waitFrame();
                return $relay;
            }
        }

        if (count($this->occupiedRelays) > 0) {
            // Check if the other relays have a free one
            $index = MultiRelayHelper::findRelayWithMessage($this->occupiedRelays);

            if ($index === false) {
                if (count($this->occupiedRelaysIgnoreResponse) > 0) {
                    // Wait for an ignore-response relay to become free (the oldest since it makes the most sense)
                    $relay = array_shift($this->occupiedRelaysIgnoreResponse);
                    $relay->waitFrame();
                    return $relay;
                } else {
                    // Use the oldest occupied relay for this instead
                    $index = 0;
                }
            }

            // Choose first one since it's the oldest and we don't want to flush all occupied relays
            if (is_array($index)) {
                $index = $index[0];
            }

            // Put response into buffer
            $relay = array_slice($this->occupiedRelays, $index, 1)[0];
            $frame = $relay->waitFrame();

            if (count($frame->options) === 2) {
                $responseSeq = $frame->options[0];
                $this->asyncResponseBuffer[$responseSeq] = $frame;
            }

            return $relay;
        }

        throw new RuntimeException("No relays???");
    }
}
