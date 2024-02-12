<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests;

use Spiral\Goridge\SocketType;

class TCPRPCTest extends RPC
{
    public const SOCK_ADDR = '127.0.0.1';
    public const SOCK_PORT = 7079;
    public const SOCK_TYPE = SocketType::TCP;
}
