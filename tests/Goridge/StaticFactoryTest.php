<?php

declare(strict_types=1);

namespace Spiral\Goridge\Tests;

use PHPUnit\Framework\TestCase;
use Spiral\Goridge\Exception;
use Spiral\Goridge\Relay;
use Spiral\Goridge\SocketRelay;
use Spiral\Goridge\SocketType;
use Spiral\Goridge\StreamRelay;
use Throwable;

class StaticFactoryTest extends TestCase
{
    /**
     * @dataProvider formatProvider
     * @param string $connection
     * @param bool   $expectedException
     */
    public function testFormat(string $connection, bool $expectedException = false): void
    {
        $this->assertTrue(true);
        if ($expectedException) {
            $this->expectException(Exception\RelayFactoryException::class);
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
        $this->assertInstanceOf(SocketRelay::class, $relay);
        $this->assertSame('localhost', $relay->getAddress());
        $this->assertSame(0, $relay->getPort());
        $this->assertSame(SocketType::TCP, $relay->getType());
    }

    public function testUnix(): void
    {
        /** @var SocketRelay $relay */
        $relay = Relay::create('unix:///tmp/rpc.sock');
        $this->assertInstanceOf(SocketRelay::class, $relay);
        $this->assertSame('/tmp/rpc.sock', $relay->getAddress());
        $this->assertSame(SocketType::UNIX, $relay->getType());
    }

    public function testPipes(): void
    {
        /** @var StreamRelay $relay */
        $relay = Relay::create('pipes://stdin:stdout');
        $this->assertInstanceOf(StreamRelay::class, $relay);
    }
}
