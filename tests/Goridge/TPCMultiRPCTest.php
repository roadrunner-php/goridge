<?php

declare(strict_types=1);

namespace Goridge;

use Spiral\Goridge\SocketType;
use Spiral\Goridge\Tests\MultiRPC;

class TPCMultiRPCTest extends MultiRPC
{
    public const SOCK_ADDR = '127.0.0.1';
    public const SOCK_PORT = 7079;
    public const SOCK_TYPE = SocketType::TCP;
}
