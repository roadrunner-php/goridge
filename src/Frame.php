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

    /** @var string|null */
    public ?string $options;

    /** @var int */
    public int     $flags;

    /**
     * @param string|null $body
     * @param string|null $options
     * @param int         $flags
     */
    public function __construct(?string $body, ?string $options, int $flags = 0)
    {
        $this->body = $body;
        $this->options = $options;
        $this->flags = $flags;
    }
}
