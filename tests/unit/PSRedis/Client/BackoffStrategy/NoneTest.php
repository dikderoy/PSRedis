<?php


namespace PSRedis\MasterDiscovery\BackoffStrategy;


use RedisGuard\Strategy\NoBackOff;

class NoneTest extends \PHPUnit_Framework_TestCase
{
    public function testBackoffIsZero()
    {
        $backoff = new NoBackOff();
        $this->assertEquals(0, $backoff->getBackOffInMicroSeconds(), 'Backoff should be zero');
    }

    public function testBackoffIsZeroAfterReset()
    {
        $backoff = new NoBackOff();
        $backoff->reset();
        $this->assertEquals(0, $backoff->getBackOffInMicroSeconds(), 'Backoff is still zero after reset');
    }

    public function testTryingAgain()
    {
        $backoff = new NoBackOff();
        $this->assertFalse($backoff->shouldWeTryAgain(), 'Never try again with this strategy');
    }
}
