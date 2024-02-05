<?php

namespace Spiral\Goridge\RPC;

use Spiral\Goridge\Exception\GoridgeException;
use Spiral\Goridge\RPC\Exception\RPCException;
use Spiral\Goridge\RPC\Exception\ServiceException;

interface AsyncRPCInterface extends RPCInterface
{
    /**
     * Invoke remote RoadRunner service method using given payload (free form) non-blockingly and ignore the response.
     *
     * @param non-empty-string $method
     *
     * @throws GoridgeException
     */
    public function callIgnoreResponse(string $method, mixed $payload): void;

    /**
     * Invoke remote RoadRunner service method using given payload (free form) non-blockingly but accept a response.
     *
     * @param non-empty-string $method
     * @return positive-int An "ID" to check whether a response has been received and to fetch said response.
     *
     * @throws GoridgeException
     */
    public function callAsync(string $method, mixed $payload): int;

    /**
     * Check whether a response has been received using the "ID" obtained through @see AsyncRPCInterface::callAsync() .
     *
     * @param positive-int $seq
     * @return bool
     */
    public function hasResponse(int $seq): bool;

    /**
     * Checks the "ID"s obtained through @see AsyncRPCInterface::callAsync() if any of them got a response yet.
     * Returns an array of "ID"s that do.
     *
     * @param positive-int[] $seqs
     * @return positive-int[]
     */
    public function hasAnyResponse(array $seqs): array;

    /**
     * Fetch the response for the "ID" obtained through @see AsyncRPCInterface::callAsync() .
     * @throws RPCException
     * @throws ServiceException
     */
    public function getResponse(int $seq, mixed $options = null): mixed;
}
