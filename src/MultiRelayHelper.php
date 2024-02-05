<?php

declare(strict_types=1);

namespace Spiral\Goridge;

use Socket;
use function socket_select;

class MultiRelayHelper
{
    /**
     * @param array<int, RelayInterface> $relays
     * @return int|int[]|false
     * @internal
     * Returns either
     *  - a single index if only one relay has changed state
     *  - an array of indices if multiple
     *  - or false if none
     */
    public static function findRelayWithMessage(array $relays, int $timeoutInMicroseconds = 0): array|bool|int
    {
        if (count($relays) === 0) {
            return false;
        }

        if ($relays[0] instanceof SocketRelay) {
            $sockets = [];
            foreach ($relays as $index => $relay) {
                assert($relay instanceof SocketRelay);

                // A quick-return for a SocketRelay that is not connected yet.
                // A non-connected relay implies that it is free. We can eat the connection-cost if it means
                // we'll have more Relays available.
                // Not doing this would also potentially result in never using the relay in the first place.
                if($relay->socket === null){
                    return $index;
                }

                $sockets[] = $relay->socket;
            }

            // Map of "id" => index
            $socketIds = array_flip(array_map(fn(Socket $socket) => spl_object_id($socket), $sockets));
            $writes = null;
            $except = null;
            $changes = socket_select($sockets, $writes, $except, 0, $timeoutInMicroseconds);

            if ($changes > 1) {
                return array_map(fn(Socket $socket) => $socketIds[spl_object_id($socket)], $sockets);
            } elseif ($changes === 1) {
                $id = spl_object_id($sockets[0]);
                return $socketIds[$id];
            } else {
                return false;
            }
        }

        if ($relays[0] instanceof StreamRelay) {
            $streams = [];
            foreach ($relays as $relay) {
                assert($relay instanceof StreamRelay);
                $streams[] = $relay->in;
            }

            // Map of "id" => index
            $streamIds = array_flip(array_map(fn($resource) => (string)$resource, $streams));
            $writes = null;
            $except = null;
            $changes = stream_select($streams, $writes, $except, 0, $timeoutInMicroseconds);

            if ($changes > 1) {
                return array_map(fn($resource) => $streamIds[(string)$resource], $streams);
            } elseif ($changes === 1) {
                $id = (string)$streams[0];
                return $streamIds[$id];
            } else {
                return false;
            }
        }

        return false;
    }
}
