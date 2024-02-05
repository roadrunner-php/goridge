<?php

declare(strict_types=1);

namespace Spiral\Goridge;

use Socket;
use function socket_select;

class MultiRelayHelper
{
    /**
     * @internal
     * Returns either
     *  - a single index if only one relay has changed state
     *  - an array of indices if multiple
     *  - or false if none
     * @param RelayInterface[] $relays
     * @return int|array|false
     */
    public static function findRelayWithMessage(array $relays): int|array|false
    {
        if (count($relays) === 0) {
            return false;
        }

        if ($relays[0] instanceof SocketRelay) {
            $sockets = array_map(fn(SocketRelay $relay) => $relay->socket, $relays);
            // Map of "id" => index
            $socketIds = array_flip(array_map(fn(Socket $socket) => spl_object_id($socket), $sockets));
            $writes = null;
            $except = null;
            $changes = socket_select($sockets, $writes, $except, 0);

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
            /** @var resource[] $streams */
            $streams = array_map(fn(StreamRelay $relay) => $relay->in, $relays);
            // Map of "id" => index
            $streamIds = array_flip(array_map(fn($resource) => (string)$resource, $streams));
            $writes = null;
            $except = null;
            $changes = stream_select($streams, $writes, $except, 0);

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
