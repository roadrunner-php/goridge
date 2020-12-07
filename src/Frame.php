<?php

/**
 * Dead simple, high performance, drop-in bridge to Golang RPC with zero dependencies
 *
 * @author Wolfy-J
 */

declare(strict_types=1);

namespace Spiral\Goridge;

final class Frame
{
    public const ERROR   = 8;
    public const CONTROL = 16;

    /** @var string|null */
    public ?string $body;

    /** @var int */
    public int     $flags;

    /**
     * @param string|null $body
     * @param int         $flags
     */
    public function __construct(?string $body, int $flags = 0)
    {
        $this->body = $body;
        $this->flags = $flags;
    }
}
