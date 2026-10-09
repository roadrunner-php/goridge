<p align="center">
    <a href="https://roadrunner.dev"><picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://github.com/roadrunner-server/.github/assets/8040338/e6bde856-4ec6-4a52-bd5b-bfe78736c1ff">
        <img alt="RoadRunner" src="https://github.com/roadrunner-server/.github/assets/8040338/040fb694-1dd3-4865-9d29-8e0748c2c8b8" style="width: 6in; display: block">
    </picture></a>
</p>

<p align="center">High-performance PHP-to-Golang IPC bridge</p>

<div align="center">

[![Documentation](https://img.shields.io/badge/Documentation-blue?style=for-the-badge&logo=gitbook&logoColor=white)](https://docs.roadrunner.dev/docs/php-worker/rpc)
[![Sponsor](https://img.shields.io/static/v1?style=for-the-badge&label=&message=Sponsor&logo=githubsponsors&logoColor=white&color=%23EA4AAA)](https://github.com/sponsors/roadrunner-server)

[![Psalm Level](https://shepherd.dev/github/roadrunner-php/goridge/level.svg)](https://shepherd.dev/github/roadrunner-php/goridge)
[![Type Coverage](https://shepherd.dev/github/roadrunner-php/goridge/coverage.svg)](https://shepherd.dev/github/roadrunner-php/goridge)

</div>

<br />

Goridge is a high-performance PHP-to-Golang codec library which works over native PHP sockets and the Golang `net/rpc` package. The library allows you to call Go service methods from PHP with minimal footprint, structures and `[]byte` support.
The Golang part lives in [roadrunner-server/goridge](https://github.com/roadrunner-server/goridge); the library is the transport layer of [RoadRunner](https://github.com/roadrunner-server/roadrunner), a high-performance PHP application server, load-balancer and process manager written in Golang.

## Get Started

### Installation

```bash
composer require spiral/goridge
```

[![PHP](https://img.shields.io/packagist/php-v/spiral/goridge.svg?style=flat-square&logo=php)](https://packagist.org/packages/spiral/goridge)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/spiral/goridge.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/spiral/goridge)
[![License](https://img.shields.io/packagist/l/spiral/goridge.svg?style=flat-square)](LICENSE)
[![Total Downloads](https://img.shields.io/packagist/dt/spiral/goridge.svg?style=flat-square)](https://packagist.org/packages/spiral/goridge/stats)

### Calling a Go service

```php
<?php

use Spiral\Goridge;
require "vendor/autoload.php";

$rpc = new Goridge\RPC\RPC(
    Goridge\Relay::create('tcp://127.0.0.1:6001')
);

//or, using factory:
$tcpRPC = Goridge\RPC\RPC::create('tcp://127.0.0.1:6001');
$unixRPC = Goridge\RPC\RPC::create('unix:///tmp/rpc.sock');
$streamRPC = Goridge\RPC\RPC::create('pipes://stdin:stdout');

echo $rpc->call("App.Hi", "Antony");
```

> Factory applies the next format: `<protocol>://<arg1>:<arg2>`

More examples, including the Go server side, can be found in [this directory](./examples).

### Codecs

JSON is the default codec. Use `withCodec()` to switch to `MsgpackCodec`, `ProtobufCodec` or `RawCodec` from the `Spiral\Goridge\RPC\Codec` namespace.

## Features

 - no external dependencies or services, drop-in (64bit PHP version required)
 - sockets over TCP or Unix (ext-sockets is required), standard pipes
 - very fast (300k calls per second on Ryzen 1700X over 20 threads)
 - native `net/rpc` integration, ability to connect to existing application(s)
 - standalone protocol usage
 - structured data transfer using JSON, MessagePack or Protobuf, and raw payloads
 - `[]byte` transfer, including big payloads
 - asynchronous calls over a pool of relays with `MultiRPC`
 - service, message and transport level error handling
 - hackable
 - works on Windows
 - unix sockets powered (also on Windows)
