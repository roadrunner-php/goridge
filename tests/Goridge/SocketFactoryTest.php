<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests;

use Spiral\Goridge\Exception\InvalidArgumentException;
use Spiral\Goridge\SocketRelay;
use Spiral\Goridge\SocketType;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
final class SocketFactoryTest
{
    public static function constructorProvider(): iterable
    {
        return [
            //invalid ports
            ['localhost', null, SocketType::TCP, InvalidArgumentException::class],
            ['localhost', 66666, SocketType::TCP, InvalidArgumentException::class],
            //ok
            ['localhost', 66666, SocketType::UNIX],
            ['localhost', 8080, SocketType::TCP],
        ];
    }

    #[DataProvider('constructorProvider')]
    public function testConstructing(string $address, ?int $port, SocketType $type, ?string $exception = null): void
    {
        Assert::true(true);
        if ($exception !== null) {
            Expect::exception($exception);
        }
        new SocketRelay($address, $port, $type);
    }
}
