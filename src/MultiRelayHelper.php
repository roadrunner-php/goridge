<?php

declare(strict_types=1);

namespace Spiral\Goridge;

use Socket;
use function socket_select;

class MultiRelayHelper
{
    /**
     * @param array<array-key, RelayInterface> $relays
     * @return array-key[]|false
     * @internal
     * Returns either
     *  - an array of array keys, even if only one
     *  - or false if none
     */
    public static function findRelayWithMessage(array $relays, int $timeoutInMicroseconds = 0): array|false
    {
        if (count($relays) === 0) {
            return false;
        }

        if ($relays[0] instanceof SocketRelay) {
            $sockets = [];
            $socketIdToRelayIndexMap = [];
            foreach ($relays as $relayIndex => $relay) {
                assert($relay instanceof SocketRelay);

                // A quick-return for a SocketRelay that is not connected yet.
                // A non-connected relay implies that it is free. We can eat the connection-cost if it means
                // we'll have more Relays available.
                // Not doing this would also potentially result in never using the relay in the first place.
                if ($relay->socket === null) {
                    return [$relayIndex];
                }

                $sockets[] = $relay->socket;
                $socketIdToRelayIndexMap[spl_object_id($relay->socket)] = $relayIndex;
            }

            $writes = null;
            $except = null;
            $changes = socket_select($sockets, $writes, $except, 0, $timeoutInMicroseconds);

            if ($changes > 1) {
                return array_map(fn(Socket $socket) => $socketIdToRelayIndexMap[spl_object_id($socket)], $sockets);
            } elseif ($changes === 1) {
                $id = spl_object_id($sockets[0]);
                return [$socketIdToRelayIndexMap[$id]];
            } else {
                return false;
            }
        }

        if ($relays[0] instanceof StreamRelay) {
            $streams = [];
            $streamNameToRelayIndexMap = [];
            foreach ($relays as $relayIndex => $relay) {
                assert($relay instanceof StreamRelay);
                $streams[] = $relay->in;
                $streamNameToRelayIndexMap[(string)$relay->in] = $relayIndex;
            }

            $writes = null;
            $except = null;
            $changes = stream_select($streams, $writes, $except, 0, $timeoutInMicroseconds);

            if ($changes > 1) {
                return array_map(fn($resource) => $streamNameToRelayIndexMap[(string)$resource], $streams);
            } elseif ($changes === 1) {
                $id = (string)$streams[0];
                return [$streamNameToRelayIndexMap[$id]];
            } else {
                return false;
            }
        }

        return false;
    }
}
