<?php

declare(strict_types=1);

namespace Spiral\Goridge\RPC;

use RuntimeException;
use Spiral\Goridge\Exception\RelayException;
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
     * @var array<int, RelayInterface>
     */
    private array $freeRelays = [];

    /**
     * Occupied Relays alone is a map of seq to relay to make removal easier once a response is received.
     * @var array<positive-int, RelayInterface>
     */
    private array $occupiedRelays = [];

    /**
     * @var array<int, RelayInterface>
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

    /**
     * @param array<int, RelayInterface> $relays
     */
    public function __construct(
        array $relays,
        CodecInterface $codec = new JsonCodec()
    ) {
        $this->freeRelays = $relays;
        parent::__construct($codec);
    }

    /**
     * @param non-empty-string $connection
     * @param positive-int $count
     */
    public static function create(string $connection, int $count = 50, CodecInterface $codec = new JsonCodec()): self
    {
        assert($count > 0);
        $relays = [];

        for ($i = 0; $i < $count; $i++) {
            $relays[] = Relay::create($connection);
        }

        return new self($relays, $codec);
    }

    /**
     * Force-connects all SocketRelays.
     * Does nothing if no SocketRelay.
     */
    public function preConnectRelays(): void
    {
        if (count($this->freeRelays) === 0) {
            return;
        }

        if (!$this->freeRelays[0] instanceof SocketRelay) {
            return;
        }

        foreach ($this->freeRelays as $relay) {
            assert($relay instanceof SocketRelay);
            // Force connect
            $relay->connect();
        }
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
        // Flush buffer if someone doesn't call getResponse
        if (count($this->asyncResponseBuffer) > 1000) {
            $this->asyncResponseBuffer = [];
        }

        $relay = $this->getNextFreeRelay();
        $relay->send($this->packFrame($method, $payload));
        $seq = self::$seq;
        self::$seq++;
        $this->occupiedRelays[$seq] = $relay;
        $this->seqToRelayMap[$seq] = $relay;
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
        /** @var array<int, positive-int> $relayIndexToSeq */
        $relayIndexToSeq = [];
        $seqsWithResponse = [];

        foreach ($seqs as $seq) {
            if (isset($this->asyncResponseBuffer[$seq])) {
                $seqsWithResponse[] = $seq;
            } elseif (isset($this->seqToRelayMap[$seq])) {
                $relayIndexToSeq[count($relays)] = $seq;
                $relays[] = $this->seqToRelayMap[$seq];
            }
        }

        $index = MultiRelayHelper::findRelayWithMessage($relays);

        if ($index === false) {
            return $seqsWithResponse;
        }

        if (!is_array($index)) {
            $index = [$index];
        }

        foreach ($index as $relayIndex) {
            $seqsWithResponse[] = $relayIndexToSeq[$relayIndex];
        }

        return $seqsWithResponse;
    }

    public function getResponse(int $seq, mixed $options = null): mixed
    {
        $relay = $this->seqToRelayMap[$seq] ?? throw new RPCException('Invalid Seq, unknown');
        unset($this->seqToRelayMap[$seq]);

        if (($frame = $this->asyncResponseBuffer[$seq] ?? null) !== null) {
            unset($this->asyncResponseBuffer[$seq]);
            /**
             * We can assume through @see MultiRPC::getNextFreeRelay() that a relay whose response is already
             * in this buffer has also been added to freeRelays (or is otherwise occupied).
             * Thus we only re-add (and do so without searching for it first) if we don't have the response yet.
             */
        } else {
            $this->freeRelays[] = $this->occupiedRelays[$seq];
            unset($this->occupiedRelays[$seq]);

            $frame = $relay->waitFrame();
        }

        if (count($frame->options) !== 2) {
            throw new RPCException('Invalid RPC frame, options missing');
        }

        if ($frame->options[0] !== $seq) {
            throw new RPCException('Invalid RPC frame, sequence mismatch');
        }

        return $this->decodeResponse($frame, $relay, $options);
    }

    public function getResponses(array $seqs, mixed $options = null): iterable
    {
        $seqsToDo = [];
        $relays = [];

        // Check for seqs that are already in the buffer
        foreach ($seqs as $seq) {
            /** @var positive-int $seq */
            $relay = $this->seqToRelayMap[$seq] ?? throw new RPCException('Invalid Seq, unknown');
            unset($this->seqToRelayMap[$seq]);

            if (($frame = $this->asyncResponseBuffer[$seq] ?? null) !== null) {
                unset($this->asyncResponseBuffer[$seq]);
                /**
                 * We can assume through @see MultiRPC::getNextFreeRelay() that a relay whose response is already
                 * in this buffer has also been added to freeRelays (or is otherwise occupied).
                 * Thus we only re-add (and do so without searching for it first) if we don't have the response yet.
                 */

                yield $seq => $this->decodeResponse($frame, $relay, $options);
            } else {
                $seqsToDo[] = $seq;
                $relays[] = $relay;
                unset($this->occupiedRelays[$seq]);
            }
        }

        $timeoutInMicroseconds = 0;
        while (count($seqsToDo) > 0) {
            // Do a first pass without a timeout. Maybe there's already most responses which would make a timeout unnecessary.
            $index = MultiRelayHelper::findRelayWithMessage($relays, $timeoutInMicroseconds);
            $timeoutInMicroseconds = 100;

            if ($index === false) {
                continue;
            }

            if (!is_array($index)) {
                $index = [$index];
            }

            foreach ($index as $relayIndex) {
                // Splice to update indices
                /** @var RelayInterface $relay */
                $relay = array_splice($relays, $relayIndex, 1)[0];
                /** @var positive-int $seq */
                $seq = array_splice($seqsToDo, $relayIndex, 1)[0];

                // Add before waitFrame() to make sure we keep track of the $relay
                $this->freeRelays[] = $relay;
                $frame = $relay->waitFrame();

                yield $seq => $this->decodeResponse($frame, $relay, $options);
            }
        }
    }

    private function getNextFreeRelay(): RelayInterface
    {
        if (count($this->freeRelays) > 0) {
            /** @psalm-return RelayInterface */
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
                        $this->tryFlushRelay($relay);
                        $this->freeRelays[] = $relay;
                    } else {
                        $occupiedRelaysIgnoreResponse[] = $relay;
                    }
                }

                $this->occupiedRelaysIgnoreResponse = $occupiedRelaysIgnoreResponse;
                return array_pop($this->freeRelays);
            } elseif ($index !== false) {
                /** @var RelayInterface $relay */
                $relay = array_splice($this->occupiedRelaysIgnoreResponse, $index, 1)[0];
                $this->tryFlushRelay($relay);
                return $relay;
            }
        }

        if (count($this->occupiedRelays) > 0) {
            // Check if the other relays have a free one

            // This array_keys/array_values is so we can use socket_select/stream_select
            $relayValues = array_values($this->occupiedRelays);
            $relayKeys = array_keys($this->occupiedRelays);
            $index = MultiRelayHelper::findRelayWithMessage($relayValues);
            // To make sure nobody uses this
            unset($relayValues);

            if ($index === false) {
                if (count($this->occupiedRelaysIgnoreResponse) > 0) {
                    // Wait for an ignore-response relay to become free (the oldest since it makes the most sense)
                    /** @var RelayInterface $relay */
                    $relay = array_shift($this->occupiedRelaysIgnoreResponse);
                    $this->tryFlushRelay($relay);
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

            $key = $relayKeys[$index];

            // Put response into buffer
            $relay = $this->occupiedRelays[$key];
            unset($this->occupiedRelays[$key]);
            $this->tryFlushRelay($relay, true);

            return $relay;
        }

        throw new RuntimeException("No relays???");
    }

    private function tryFlushRelay(RelayInterface $relay, bool $saveResponse = false): void
    {
        try {
            if (!$saveResponse) {
                $relay->waitFrame();
            } else {
                $frame = $relay->waitFrame();
                if (count($frame->options) === 2) {
                    /** @var positive-int $responseSeq */
                    $responseSeq = $frame->options[0];
                    $this->asyncResponseBuffer[$responseSeq] = $frame;
                }
            }
        } catch (RelayException $exception) {
            // Intentionally left blank
        }
    }
}
