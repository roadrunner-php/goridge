<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests;

use PHPUnit\Framework\TestCase;
use Spiral\Goridge\Exception\InvalidArgumentException;
use Spiral\Goridge\SocketRelay;

class SocketFactoryTest extends TestCase
{
    /**
     * @dataProvider constructorProvider
     * @param string      $address
     * @param int|null    $port
     * @param int         $type
     * @param string|null $exception
     */
    public function testConstructing(string $address, ?int $port, int $type, ?string $exception = null): void
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
    public function constructorProvider(): iterable
    {
        return [
            //unknown type
            ['localhost', 8080, 8080, InvalidArgumentException::class],
            //invalid ports
            ['localhost', null, 0, InvalidArgumentException::class],
            ['localhost', 66666, 0, InvalidArgumentException::class],
            //ok
            ['localhost', 66666, 1],
            ['localhost', 8080, 0],
        ];
    }
}
