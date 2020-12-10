<?php

/**
 * Dead simple, high performance, drop-in bridge to Golang RPC with zero dependencies
 *
 * @author Wolfy-J
 */

declare(strict_types=1);

use Spiral\Goridge;

require 'vendor/autoload.php';

$rpc = new Goridge\RPC\RPC(new Goridge\SocketRelay('127.0.0.1', 6001));

print_r(
    $rpc->withServicePrefix('App')
        ->call('Hi', 'Antony!')
);

//$relay->send($f);

//$result = Goridge\Frame::packFrame($f);
//print_r(array_values(unpack('C*', $result)));
//
//print_r(Goridge\Frame::readHeader(substr($result, 0, 8)));
//

//for ($i = 0; $i < strlen($result); $i++) {
//    $b = $result[$i];
//  echo ord($b) . "\n";
//}

//$value = "hello world";

//$crc = Goridge\CRC8::calculate($value);
//print_r($crc);
