<?php
/**
 * Part of RedisGuardYiiBundle 2015
 * Created by: deroy on 21.01.15:23:37
 */

namespace RedisGuard;

use RedisGuard\Exception\ConfigurationError;
use RedisGuard\Exception\ConnectionError;
use RedisGuard\Exception\SentinelError;
use RedisGuard\Strategy\IBackOffStrategy;
use RedisGuard\Strategy\NoBackOff;
use SplFixedArray;

class Discovery
{
	const MODE_ANY = 0;
	const MODE_RW  = 1;
	const MODE_RO  = -1;
	/**
	 * @var string cluster name
	 */
	protected $name;
	/**
	 * @var IBackOffStrategy
	 */
	protected $strategy;
	/**
	 * @var callable|array
	 */
	protected $strategyObserver;
	/**
	 * @var Client[] clients which wrap sentinel connections
	 */
	protected $sentinels = [];

	public function __construct($name, $strategy = null)
	{
		if (empty($name))
			throw new ConfigurationError('name should not be blank');
		$this->name = $name;
		// by default we don't implement a back-off
		if ($strategy === null)
			$this->strategy = new NoBackOff();
	}

	/**
	 * @return string name of cluster
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * in best hope - returns Master Client
	 * but if it cant - first available Slave
	 *
	 * @param int $tolerance
	 *
	 * @return mixed|object
	 * @throws ConfigurationError
	 * @throws ConnectionError
	 */
	public function getNode($tolerance = self::MODE_ANY)
	{
		return $this->discoverNode($tolerance);
	}

	public function getMaster()
	{
		return $this->discoverNode(self::MODE_RW);
	}

	public function getSlave()
	{
		return $this->discoverNode(self::MODE_RO);
	}

	public function getSentinels()
	{
		return SplFixedArray::fromArray($this->sentinels);
	}

	public function addSentinel(Client $sentinelClient)
	{
		$this->sentinels[] = $sentinelClient;
	}

	/**
	 * @param IBackOffStrategy $strategy
	 */
	public function setStrategy($strategy)
	{
		$this->strategy = $strategy;
	}

	/**
	 * @param callable|array $strategyObserver
	 */
	public function setStrategyObserver($strategyObserver)
	{
		$this->strategyObserver = $strategyObserver;
	}

	/**
	 * get working client
	 *
	 * @param int $mode
	 *
	 *    - -1 for slave
	 *    - 1 for master
	 *    - 0 for whatever
	 *
	 * @return mixed
	 * @throws ConfigurationError
	 * @throws ConnectionError
	 */
	protected function discoverNode($mode = self::MODE_ANY)
	{
		$sentinels = $this->getSentinels();
		if ($sentinels->count() == self::MODE_ANY) {
			throw new ConfigurationError('You need to configure and add sentinel nodes before attempting to fetch a node');
		}
		$cluster = $this->getName();
		$this->strategy->reset();
		do {
			foreach ($sentinels as $sentinel) {
				/** @var $sentinel Client */
				try {
					$sentinel->connect();
					switch ($mode) {
						case self::MODE_ANY: {
							$node = $sentinel->getMaster($cluster);
							try {
								$node->isMaster();
							} catch (ConnectionError $e) {
								$node = $sentinel->getSlave($cluster);
							}
							break;
						}
						case self::MODE_RW: {
							$node = $sentinel->getMaster($cluster);
							break;
						}
						case self::MODE_RO: {
							$node = $sentinel->getSlave($cluster);
							break;
						}
					}
					if (empty($node)) //actually it is never true - we get ConnectionError
						continue;
					if ($mode >= self::MODE_ANY && $node->isMaster())
						return $node;
					if ($mode <= self::MODE_ANY && $node->isSlave())
						return $node;
					break; //if we didn't get expected role - we use backOff strategy to wait and try again
				} catch (ConnectionError $e) {
					// on error, try to connect to next sentinel
				} catch (SentinelError $e) {
					// when the sentinel throws an error, we try the next sentinel in the set
				}
			}
			//in cause of failures - we use strategy to wait
			if ($this->strategy->shouldWeTryAgain()) {
				$backOffInMicroseconds = $this->strategy->getBackOffInMicroSeconds();
				if (!empty($this->backOffObserver)) {
					call_user_func($this->backOffObserver, $backOffInMicroseconds);
				}
				usleep($backOffInMicroseconds);
			}
		} while ($this->strategy->shouldWeTryAgain());
		throw new ConnectionError('All sentinels are unreachable');
	}
}