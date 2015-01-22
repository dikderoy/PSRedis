<?php


namespace RedisGuard\Strategy;

/**
 * Interface BackoffStrategy
 *
 * Implements logic to decide on whether we should try again after a backoff and how long to backoff
 *
 * @package PSRedis\MasterDiscovery
 */
interface IBackOffStrategy
{
	/**
	 * @return int
	 */
	public function getBackOffInMicroSeconds();

	/**
	 * Resets the state of the back-off implementation.  Should be used when engaging in a logically different master
	 * discovery or reconnection attempt
	 */
	public function reset();

	/**
	 * Verifies if we should stop trying to discover the master or back-off and try again
	 * @return bool
	 */
	public function shouldWeTryAgain();
}