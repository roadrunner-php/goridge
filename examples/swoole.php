<?php

declare(strict_types=1);

use Spiral\Goridge;
use Swoole\Coroutine as Co;
use Swoole\Coroutine\Barrier;

require 'vendor/autoload.php';

Co::set(['hook_flags'=> SWOOLE_HOOK_ALL]);
Co\Run(function () {
    $barrier = Barrier::make();
    for ($i = 0; $i < 3; $i++) {
        go(function () use ($barrier) {
            $rpc = new Goridge\RPC\RPC(
                Goridge\Relay::create('tcp://127.0.0.1:6001')
            );
            echo $rpc->call('App.Hi', 'Antony');
        });
    }
    Barrier::wait($barrier);
});