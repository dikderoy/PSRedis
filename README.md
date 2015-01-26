# Redis Guard - redis client wrapper for PHP, providing sentinel operations & connection fallback

A PHP client for redis sentinel connections as a wrapper on other redis clients.

## Installation

The easiest way to install is by using composer.
The package will soon be available on [packagist](https://packagist.org/packages/sparkcentral/predis-sentinel),
so installing should be as easy as putting the following in your composer file:

```json
"require": {
    "dikderoy/redis-guard": "*"
},
```

## Why Fork?

General idea behind this fork is to get rid off redundant dependencies such as Predis.
I'm planning to reimplement all general PHP clients support as separate packages - **HELP** much appreciated.
As soon as packages will be ready - they will appear in suggest section of `composer.json`.

Also RedisGuard provides mechanics to organize live fallback of connections with `HighAvailabilityClient` & `ICallStrategy`

Code Style is much more consistent and clear. General refactoring done. Architecture changed a little.

**IMPORTANT** This fork **IS NOT** compatible with original library!

## Usage

### Basic example

The most basic example makes use of internal native (pure-php) redis client.
Unlike original library no defaults assumed inside.
This is the least amount of code needed to get your app talk to Redis using pure php
(only commands needed for simple key-value cache are implemented).

make sure you required dikderoy/redis-guard-pure-php package in composer - so client implementation & adapter is available in runtime

```php
// configure where to find the sentinel nodes

$sentinels = [
    new RedisGuard\Client('192.168.50.40', '26379', new RedisGuard\PurePhp\Adapter()),
    new RedisGuard\Client('192.168.50.41', '26379', new RedisGuard\PurePhp\Adapter()),
    new RedisGuard\Client('192.168.50.30', '26379', new RedisGuard\PurePhp\Adapter()),
];

// now we can configure the master name and the sentinel nodes

$discovery = new RedisGuard\Discovery('nodeSetName');
foreach ($sentinels as &$sentinel) {
    $discovery->addSentinel($sentinel);
}

// discover where the master is
$master = $discovery->getMaster();

//perform operations implemented in RedisGuard\PurePhp\Client
$master->set('key', 'value', 3600);
```

### Auto failover

You have the option of letting the library handle the failover.  That means that in case of connection errors, the
library will select the new master/slave or reconnect to the existing ones depending of what the sentinels decide is the
proper action.  After this reconnection, the command will be re-tried.
You can add your own CallStrategy to handle back-off. 

```php
// configuration of $discovery
$masterDiscovery = ...

// using the $discovery as a dependency in an Highly Available Client (HighAvailabilityClient)
$client = new HighAvailabilityClient($masterDiscovery);
$client->set('test', 'ok');
$test = $client->get('test');
```

by default HighAvailabilityClient uses `DefaultCallStrategy` which provides no back-off (immediate back-off - same as original library).
You can write your own (implement `RedisGuard\Strategy\ICallStrategy` interface for this)
and use one of 2 ways to implement back-off:

1. use strategy to talk with HighAvailabilityClient (with exceptions)
    - define set of methods on which you should throw `RedisGuard\Exception\ReadOnlyError` from inside strategy
        to tell `HighAvailabilityClient` what that command requires **MASTER** to be executed.
    - rest of them should produce `RedisGuard\Exception\ConnectionError` to tell `HighAvailabilityClient`
        what **SLAVE** can be used to perform this command
2. implement your own `try{call}catch(e){call again}` mechanism inside of strategy

### Customizing the adapter

You can choose what kind of client adapter to use or even write your own.
If you write your own you need to make sure you implement the `RedisGuard\Client\IClientAdapter` interface.

### Configuring backoff

When we fail to discover the location of the master, we need to back off and try again.  The back off mechanism is
configurable and you can implement your own by implementing the `RedisGuard\Client\IBackOffStrategy`

Here is an example using the incremental backoff strategy:

```php
$sentinel = new Client('192.168.50.40', '26379');
$masterDiscovery = new Discovery('nodeSetName');
$masterDiscovery->addSentinel($sentinel);

// create a back-off strategy (half a second initially and increment with half of the back-off on each successive try)
$incrementalBackOff = new IncrementalBackOff(500000, 1.5);
$incrementalBackOff->setMaxAttempts(10);

// configure the master discovery with this back off strategy
$masterDiscovery->setBackOffStrategy($incrementalBackOff);

// try to discover the master
$master = $masterDiscovery->getMaster();
```

## Testing

*TBD* - refactoring & new features broke test suite - fix coming soon

### Unit testing

We use [PHPUnit](https://github.com/sebastianbergmann/phpunit) for unit testing
and [Phake](https://github.com/mlively/Phake) for mocking.
Both are installed using composer. Running the unit tests can be done using the following command:

    ./vendor/bin/phpunit -c ./phpunit.xml --bootstrap ./tests/bootstrap.php

### Integration testing

To run the integration tests, make sure you install [Vagrant](http://www.vagrantup.com).
We have used it together with [VirtualBox](https://www.virtualbox.org).

The VM's are provisioned with [ansible](http://www.ansible.com/home).

After installing all of these, execute the following in the project root to provision the machines:

    vagrant up

After that, run the integration tests with

    ./vendor/bin/phpunit -c ./phpunit.int.xml --bootstrap ./tests/bootstrap.int.php

You will see some warnings that need to be fixed, but the tests themselves should all pass.  The warnings are a result
of the reset of the environment just before every integration test.

## RoadMap

- encapsulate back-off login inside of BackOffStrategy completely
- implement PurePhp package
- provide $discovery as dependency to ICallStrategy
- add readOnlySetCallStrategy as abstract/base/default and encapsulate call logic where
- fix unit tests
- replace microseconds with milliseconds
- release v.1 on packagist
- release pure-php package on packagist