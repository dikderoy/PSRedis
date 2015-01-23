<?php


namespace RedisGuard\Strategy;

use RedisGuard\Exception\ConfigurationError;

/**
 * Class IncrementalBackOff
 *
 * Implements incremental back-off logic.  By changing the initial back-off and multiplier, the back-off can be chosen in
 * a very flexible way.  Bad configuration could lead to infinite loops though, so be careful on what kind of logic you
 * implement
 *
 * @package RedisGuard\Client\BackoffStrategy
 */
class IncrementalBackOff implements IBackOffStrategy
{
	/**
	 * The initial back-off in microseconds
	 * @var int
	 */
	protected $initialBackOff;
	/**
	 * The number to multiply the previous back-off with on each back-off.
	 * @var float
	 */
	protected $backOffMultiplier;
	/**
	 * Holds the next back-off value
	 * @var float
	 */
	protected $nextBackOff;
	/**
	 * The maximum number of attempts to take before we don't back-off anymore
	 * @var bool|int
	 */
	protected $maxAttempts = false;
	/**
	 * The number of attempts that were already made
	 * @var int
	 */
	protected $attempts = 0;

	public function __construct($initialBackOff, $backOffMultiplier)
	{
		if ($initialBackOff < 0)
			throw new ConfigurationError('The initial back off cannot be smaller than zero');
		$this->initialBackOff    = $initialBackOff;
		$this->backOffMultiplier = $backOffMultiplier;
		$this->reset();
	}

	/**
	 * Resets the state of the back-off implementation.  Should be used when engaging in a logically different master
	 * discovery or reconnection attempt
	 */
	public function reset()
	{
		$this->nextBackOff = $this->initialBackOff;
		$this->attempts    = 0;
	}

	/**
	 * @param $maxAttempts int
	 */
	public function setMaxAttempts($maxAttempts)
	{
		$this->maxAttempts = $maxAttempts;
	}

	/**
	 * @return int
	 */
	public function getBackOffInMicroSeconds()
	{
		$currentBackOff = $this->nextBackOff;
		$this->nextBackOff *= $this->backOffMultiplier;
		$this->attempts += 1;
		return $currentBackOff;
	}

	/**
	 * Verifies if we should stop trying to discover the master or back-off and try again
	 * @return bool
	 */
	public function shouldWeTryAgain()
	{
		return ($this->maxAttempts === false) ? true : $this->maxAttempts >= $this->attempts + 1;
	}
}