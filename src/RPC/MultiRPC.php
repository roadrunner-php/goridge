<?php

declare(strict_types=1);

namespace Spiral\Goridge\RPC;

use Spiral\Goridge\Exception\RelayException;
use Spiral\Goridge\Exception\TransportException;
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
     * Without cloning relays explicitly they get shared and thus, when one RPC gets called, the freeRelays array
     * in the other RPC stays the same, making it reuse the just-used and still occupied relay.
     */
    public function __clone()
    {
        foreach ($this->freeRelays as $key => $relay) {
            $this->freeRelays[$key] = clone $relay;
        }
        foreach ($this->occupiedRelays as $key => $relay) {
            $this->occupiedRelays[$key] = clone $relay;
        }
        $this->seqToRelayMap = $this->asyncResponseBuffer = [];
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
        $relayIndex = $this->ensureFreeRelayAvailable();
        $relay = $this->freeRelays[$relayIndex];

        $relay->send($this->packFrame($method, $payload));

        // wait for the frame confirmation
        $frame = $this->getResponseFromRelay($relay, self::$seq, true);

        self::$seq++;

        return $this->decodeResponse($frame, $relay, $options);
    }

    public function callIgnoreResponse(string $method, mixed $payload): void
    {
        $relayIndex = $this->ensureFreeRelayAvailable();
        $relay = $this->freeRelays[$relayIndex];

        $relay->send($this->packFrame($method, $payload));

        $seq = self::$seq;
        self::$seq++;
        $this->occupiedRelays[$seq] = $relay;
        // Last index so no need for array_pop or stuff
        unset($this->freeRelays[$relayIndex]);
    }

    public function callAsync(string $method, mixed $payload): int
    {
        // Flush buffer if someone doesn't call getResponse
        if (count($this->asyncResponseBuffer) > 10_000) {
            // We don't need to clean up occupiedRelays here since the buffer is solely for responses already
            // fetched from relays, and those relays are put back to freeRelays in getNextFreeRelay()
            $this->seqToRelayMap = array_diff_key($this->seqToRelayMap, $this->asyncResponseBuffer);
            $this->asyncResponseBuffer = [];
        }

        $relayIndex = $this->ensureFreeRelayAvailable();
        $relay = $this->freeRelays[$relayIndex];

        $relay->send($this->packFrame($method, $payload));

        $seq = self::$seq;
        self::$seq++;
        $this->occupiedRelays[$seq] = $relay;
        $this->seqToRelayMap[$seq] = $relay;
        // Last index so no need for array_pop or stuff
        unset($this->freeRelays[$relayIndex]);

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

    public function hasResponses(array $seqs): array
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

        /** @var int[]|false $index */
        $index = MultiRelayHelper::findRelayWithMessage($relays);

        if ($index === false) {
            return $seqsWithResponse;
        }

        foreach ($index as $relayIndex) {
            $seqsWithResponse[] = $relayIndexToSeq[$relayIndex];
        }

        return $seqsWithResponse;
    }

    public function getResponse(int $seq, mixed $options = null): mixed
    {
        $relay = $this->seqToRelayMap[$seq] ?? throw new RPCException('Invalid seq, unknown');
        unset($this->seqToRelayMap[$seq]);

        if (($frame = $this->getResponseFromBuffer($seq)) !== null) {
            /**
             * We can assume through @see MultiRPC::ensureFreeRelayAvailable() that a relay whose response is already
             * in this buffer has also been added to freeRelays (or is otherwise occupied).
             * Thus we only re-add (and do so without searching for it first) if we don't have the response yet.
             */
        } else {
            $this->freeRelays[] = $this->occupiedRelays[$seq];
            unset($this->occupiedRelays[$seq]);

            if ($relay instanceof SocketRelay && !$relay->isConnected()) {
                throw new TransportException("Unable to read payload from the stream");
            }

            $frame = $this->getResponseFromRelay($relay, $seq, true);
        }

        return $this->decodeResponse($frame, $relay, $options);
    }

    public function getResponses(array $seqs, mixed $options = null): iterable
    {
        $seqsKeyed = array_flip($seqs);

        // Fetch all responses already buffered
        /** @var array<positive-int, Frame> $responsesBuffered */
        $responsesBuffered = array_intersect_key($this->asyncResponseBuffer, $seqsKeyed);

        // Fetch all relays for buffered responses
        /** @var array<positive-int, RelayInterface> $seqsToRelays */
        $seqsToRelays = array_intersect_key($this->seqToRelayMap, $responsesBuffered);

        if (count($seqsToRelays) !== count($responsesBuffered)) {
            throw new RPCException("Invalid seq, unknown");
        }

        foreach ($responsesBuffered as $seq => $frame) {
            $relay = $seqsToRelays[$seq];
            yield $seq => $this->decodeResponse($frame, $relay, $options);
            unset($this->asyncResponseBuffer[$seq], $seqsKeyed[$seq], $seqsToRelays[$seq]);
        }

        // Fetch all relays that are still occupied
        $seqsToRelays = array_intersect_key($this->occupiedRelays, $seqsKeyed);

        if (count($seqsToRelays) !== count($seqsKeyed)) {
            throw new RPCException("Invalid seq, unknown");
        }

        $timeoutInMicroseconds = 0;
        while (count($seqsToRelays) > 0) {
            // Do a first pass without a timeout. Maybe there's already most responses which would make a timeout unnecessary.
            /** @var positive-int[]|false $seqsReceivedResponse */
            $seqsReceivedResponse = MultiRelayHelper::findRelayWithMessage($seqsToRelays, $timeoutInMicroseconds);
            $timeoutInMicroseconds = 500;

            if ($seqsReceivedResponse === false) {
                if ($this->checkAllOccupiedRelaysStillConnected()) {
                    if (count(array_diff_key($seqsToRelays, $this->occupiedRelays))) {
                        throw new RPCException("Invalid seq, unknown");
                    }
                }
                continue;
            }

            foreach ($seqsReceivedResponse as $seq) {
                $relay = $seqsToRelays[$seq];
                $this->freeRelays[] = $relay;
                unset($this->occupiedRelays[$seq]);

                $frame = $this->getResponseFromRelay($relay, $seq, true);
                yield $seq => $this->decodeResponse($frame, $relay, $options);

                unset($seqsToRelays[$seq], $this->seqToRelayMap[$seq]);
            }
        }
    }

    /**
     * Returns array-key of free relay
     * @throws RPCException
     */
    private function ensureFreeRelayAvailable(): int
    {
        if (count($this->freeRelays) > 0) {
            /** @psalm-return int */
            return array_key_last($this->freeRelays);
        }

        if (count($this->occupiedRelays) === 0) {
            throw new RPCException("No relays available at all");
        }

        while (count($this->freeRelays) === 0) {
            /** @var positive-int[]|false $index */
            $index = MultiRelayHelper::findRelayWithMessage($this->occupiedRelays);

            if ($index === false) {
                if ($this->checkAllOccupiedRelaysStillConnected()) {
                    continue;
                } else {
                    $index = [array_key_first($this->occupiedRelays)];
                }
            }

            // Flush as many relays as we can up until a limit (arbitrarily 10?)
            for ($i = 0, $max = min(10, count($index)); $i < $max; $i++) {
                /** @var positive-int $seq */
                $seq = $index[$i];
                $relay = $this->occupiedRelays[$seq];
                $this->freeRelays[] = $relay;
                unset($this->occupiedRelays[$seq]);
                // Save response if in seqToRelayMap (aka a response is expected)
                // only save response in case of mismatched seq = response not in seqToRelayMap
                try {
                    $this->getResponseFromRelay($relay, $seq, !isset($this->seqToRelayMap[$seq]));
                } catch (RelayException|RPCException) {
                    // Intentionally left blank
                }
            }
        }

        // Sometimes check if all occupied relays are even still connected
        $this->checkAllOccupiedRelaysStillConnected();

        return array_key_last($this->freeRelays);
    }

    /**
     * Gets a response from the relay, blocking for it if necessary, with some error handling in regards to mismatched seq
     *
     * @param RelayInterface $relay
     * @param positive-int $expectedSeq
     * @param bool $onlySaveResponseInCaseOfMismatchedSeq
     * @return Frame
     */
    private function getResponseFromRelay(RelayInterface $relay, int $expectedSeq, bool $onlySaveResponseInCaseOfMismatchedSeq = false): Frame
    {
        $frame = $relay->waitFrame();

        if (count($frame->options) !== 2) {
            throw new RPCException('Invalid RPC frame, options missing');
        }

        if ($frame->options[0] !== $expectedSeq) {
            // Save response since $seq was invalid but the response may not
            /** @var positive-int $responseSeq */
            $responseSeq = $frame->options[0];
            $this->asyncResponseBuffer[$responseSeq] = $frame;

            throw new RPCException('Invalid RPC frame, sequence mismatch');
        }

        if (!$onlySaveResponseInCaseOfMismatchedSeq) {
            $this->asyncResponseBuffer[$expectedSeq] = $frame;
        }

        return $frame;
    }

    /**
     * Tries to get a response (Frame) from the buffer and unsets the entry if it finds the response.
     *
     * @param positive-int $seq
     * @return Frame|null
     */
    private function getResponseFromBuffer(int $seq): ?Frame
    {
        if (($frame = $this->asyncResponseBuffer[$seq] ?? null) !== null) {
            unset($this->asyncResponseBuffer[$seq]);
        }

        return $frame;
    }

    private function checkAllOccupiedRelaysStillConnected(): bool
    {
        if (($relaysNotConnected = MultiRelayHelper::checkConnected($this->occupiedRelays)) !== false) {
            /** @var positive-int $seq */
            foreach ($relaysNotConnected as $seq) {
                $this->freeRelays[] = $this->occupiedRelays[$seq];
                unset($this->seqToRelayMap[$seq], $this->occupiedRelays[$seq]);
            }

            return true;
        }

        return false;
    }
}
