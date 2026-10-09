<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests\Stub;

use Spiral\Goridge\Frame;
use Spiral\Goridge\RelayInterface;

/**
 * In-process relay: answers every sent frame with the frame built by the responder.
 */
final class FakeRelay implements RelayInterface
{
    /** @var list<Frame> */
    public array $sent = [];

    /** @var list<Frame> */
    private array $pending = [];

    /**
     * @param \Closure(Frame): Frame $responder Builds the response to a request frame.
     */
    public function __construct(
        private readonly \Closure $responder,
    ) {}

    /**
     * Echoes the request body back as a successful response with the request's sequence number.
     */
    public static function echo(): self
    {
        return new self(static fn(Frame $request): Frame => new Frame(
            $request->payload,
            $request->options,
            $request->flags,
        ));
    }

    #[\Override]
    public function waitFrame(): Frame
    {
        return \array_shift($this->pending) ?? throw new \LogicException('No response pending');
    }

    #[\Override]
    public function send(Frame $frame): void
    {
        $this->sent[] = $frame;
        $this->pending[] = ($this->responder)($frame);
    }

    #[\Override]
    public function hasFrame(): bool
    {
        return $this->pending !== [];
    }
}
