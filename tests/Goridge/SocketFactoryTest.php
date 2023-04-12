<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests;

use PHPUnit\Framework\TestCase;
use Spiral\Goridge\Exception\InvalidArgumentException;
use Spiral\Goridge\SocketRelay;
use Spiral\Goridge\SocketType;

class SocketFactoryTest extends TestCase
{
    /**
     * @dataProvider constructorProvider
     */
    public function testConstructing(string $address, ?int $port, SocketType $type, ?string $exception = null): void
    {
        $this->assertTrue(true);
        if ($exception !== null) {
            $this->expectException($exception);
        }
        new SocketRelay($address, $port, $type);
    }

    /**
     * @return iterable
     */
    public static function constructorProvider(): iterable
    {
        return [
            //unknown type
            ['localhost', 8080, 8080, InvalidArgumentException::class],
            //invalid ports
            ['localhost', null, SocketType::TCP, InvalidArgumentException::class],
            ['localhost', 66666, SocketType::TCP, InvalidArgumentException::class],
            //ok
            ['localhost', 66666, SocketType::UNIX],
            ['localhost', 8080, SocketType::TCP],
        ];
    }
}
