# High-performance PHP-to-Golang IPC bridge

[![Latest Stable Version](https://poser.pugx.org/spiral/goridge/v/stable)](https://packagist.org/packages/spiral/goridge)
![CI](https://github.com/spiral/goridge/workflows/CI/badge.svg)
[![Codecov](https://codecov.io/gh/spiral/goridge/branch/master/graph/badge.svg)](https://codecov.io/gh/spiral/goridge/)
[![Chat](https://img.shields.io/badge/discord-chat-magenta.svg)](https://discord.gg/TFeEmCs)

<img src="https://files.phpclasses.org/graphics/phpclasses/innovation-award-logo.png" height="90px" alt="PHPClasses Innovation Award" align="left"/>

Goridge is high performance PHP-to-Golang codec library which works over native PHP sockets and Golang net/rpc package. The library allows you to call Go service methods from PHP with minimal footprint, structures and `[]byte` support.

<br/>
See https://github.com/spiral/roadrunner - High-performance PHP application server, load-balancer and process manager written in Golang
<br/>

## Features

 - no external dependencies or services, drop-in (64bit PHP version required)
 - low message footprint (17 bytes over any binary payload), binary error detection
 - sockets over TCP or Unix (ext-sockets is required), standard pipes
 - very fast (300k calls per second on Ryzen 1700X over 20 threads)
 - native `net/rpc` integration, ability to connect to existed application(s)
 - standalone protocol usage
 - structured data transfer using json
 - `[]byte` transfer, including big payloads
 - service, message and transport level error handling
 - hackable
 - works on Windows
 - unix sockets powered (also on Windows)

## Installation

```
$ composer require spiral/goridge
```

## Example

```php
<?php

use Spiral\Goridge;
require "vendor/autoload.php";

$rpc = new Goridge\RPC(new Goridge\SocketRelay("127.0.0.1", 6001));
//or, using factory:
$tcpRPC = Goridge\Relay::create('tcp://127.0.0.1:6001');
$unixRPC = Goridge\Relay::create('unix:///tmp/rpc.sock');
$streamRPC = Goridge\Relay::create('pipes://stdin:stdout');

echo $rpc->call("App.Hi", "Antony");
```

> Factory applies the next format: `<protocol>://<arg1>:<arg2>`

More examples can be found in [this directory](./examples).

Check this libraries in order to find suitable socket manager:

 * <https://github.com/fatih/pool>
 * <https://github.com/hashicorp/yamux>

License
-------

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
