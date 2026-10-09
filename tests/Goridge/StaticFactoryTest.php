<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests;

use Spiral\Goridge\Exception;
use Spiral\Goridge\Relay;
use Spiral\Goridge\SocketRelay;
use Spiral\Goridge\SocketType;
use Spiral\Goridge\StreamRelay;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;
use Throwable;

#[Test]
final class StaticFactoryTest
{
    /**
     * @param string $connection
     * @param bool   $expectedException
     */
    #[DataProvider('formatProvider')]
    public function testFormat(string $connection, bool $expectedException = false): void
    {
        Assert::true(true);
        if ($expectedException) {
            Expect::exception(Exception\RelayFactoryException::class);
        }

        try {
            Relay::create($connection);
        } catch (Exception\RelayFactoryException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            //do nothing, that's not a factory issue
        }
    }

    /**
     * @return iterable
     */
    public static function formatProvider(): iterable
    {
        return [
            // format invalid
            ['tcp:localhost:', true],
            ['tcp:/localhost:', true],
            ['tcp//localhost:', true],
            ['tcp//localhost', true],
            // unknown provider
            ['test://localhost', true],
            // pipes require 2 args
            ['pipes://localhost:', true],
            ['pipes://localhost', true],
            // invalid resources
            ['pipes://stdin:test', true],
            ['pipes://test:stdout', true],
            ['pipes://test:test', true],
            // valid format
            ['tcp://localhost'],
            ['tcp://localhost:123'],
            ['unix://localhost:123'],
            ['unix://rpc.sock'],
            ['unix:///tmp/rpc.sock'],
            ['tcp://localhost:abc'],
            ['pipes://stdin:stdout'],
            // in different register
            ['UnIx:///tmp/RPC.sock'],
            ['TCP://Domain.com:42'],
            ['PIPeS://stdIn:stdErr'],
        ];
    }

    public function testTCP(): void
    {
        /** @var SocketRelay $relay */
        $relay = Relay::create('tcp://localhost:0');
        Assert::instanceOf($relay, SocketRelay::class);
        Assert::same($relay->getAddress(), 'localhost');
        Assert::same($relay->getPort(), 0);
        Assert::same($relay->getType(), SocketType::TCP);
    }

    public function testUnix(): void
    {
        /** @var SocketRelay $relay */
        $relay = Relay::create('unix:///tmp/rpc.sock');
        Assert::instanceOf($relay, SocketRelay::class);
        Assert::same($relay->getAddress(), '/tmp/rpc.sock');
        Assert::same($relay->getType(), SocketType::UNIX);
    }

    public function testPipes(): void
    {
        /** @var StreamRelay $relay */
        $relay = Relay::create('pipes://stdin:stdout');
        Assert::instanceOf($relay, StreamRelay::class);
    }
}
