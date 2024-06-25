<?php 

declare(strict_types=1);

namespace Spiral\Goridge\Tests;

use Swoole\Coroutine as Co;
use Swoole\Coroutine\Barrier;

class SwooleTest extends RPCTest
{
    public function testNoExceptionWithSwooleCoroutine(): void
    {
        Co::set(['hook_flags'=> SWOOLE_HOOK_ALL]);
        Co\run(function () {
            $methods = [
                'testManualConnect',
                'testReconnect',
                'testPingPong',
                'testPrefixPingPong',
                'testPingNull',
                'testNegate',
                'testNegateNegative',
                'testLongEcho',
                'testRawBody',
                'testPayload',
                'testPayloadWithMap',

                // 'testBrokenPayloadMap',
                // 'testJsonException'
                // 'testInvalidService',
                // 'testInvalidMethod',
                // 'testConvertException',
                // 'testBadPayload',

                // 'testLongRawBody',
            ];

            foreach ($methods as $method) {
                $barrier = Barrier::make();
                for ($i = 0; $i < 2; $i++) {
                    go(function () use ($barrier, $method) {
                        $this->$method();
                    });
                }
                Barrier::wait($barrier);
            }
        });
    }
}