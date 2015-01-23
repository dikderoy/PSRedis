<?php

namespace RedisGuard\Strategy;

/**
 * Class NoBackOff
 *
 * Makes use of the IncrementalBackOff strategy with pre-defined settings.  It allows you to make the back-off
 * strategy more readable and explicit in your code instead of expressing yourself with integer and float
 * configuration parameters
 *
 * @package PSRedis\MasterDiscovery\BackoffStrategy
 */
class NoBackOff implements IBackOffStrategy
{
	private $incrementalStrategy;

	public function __construct()
	{
		$this->incrementalStrategy = new IncrementalBackOff(0, 0);
		$this->incrementalStrategy->setMaxAttempts(0);
	}

	/**
	 *
	 */
	public function reset()
	{
		$this->incrementalStrategy->reset();
	}

	public function getBackOffInMicroSeconds()
	{
		return $this->incrementalStrategy->getBackOffInMicroSeconds();
	}

	public function shouldWeTryAgain()
	{
		return $this->incrementalStrategy->shouldWeTryAgain();
	}
}