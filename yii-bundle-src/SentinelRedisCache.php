<?php
/**
 * Part of swisstokLK 2015
 * Created by: deroy on 13.01.15:15:13
 */

namespace RedisGuard;

use CCache;
use CException;
use Exception;
use ICache;
use PSRedis\MasterDiscovery;

/**
 * Class SentinelRedisCache
 *
 * overrides CRedisCache to use sentinel proxy PSRedis
 *
 * configuring required:
 *
 * ```
 * [
 *  'clusterName' =>'my_master',
 *  'sentinels' = [
 *        ['host' => 'localhost', 'port' => 26379,]
 *  ],
 *  'connection' = [
 *        'host'     => 'localhost',
 *        'port'     => 6379,
 *        'database' => 0,
 *        'password' => null
 *  ],
 * ]
 * ```
 *
 * @package RedisGuard
 */
class SentinelRedisCache extends CCache implements ICache
{
	/**
	 * @var Client[]
	 */
	protected $_sentinels = [];
	/**
	 * @var MasterDiscovery
	 */
	protected $_discovery;
	/**
	 * @var \RedisGuard\Adapter\AbstractClientAdapter
	 */
	protected $adapter;
	/**
	 * @var string name of cluster group (master name) to ask from sentinel
	 */
	public $clusterName = null;
	/**
	 * @var array sentinels config
	 */
	public $sentinels = [
		['host' => 'localhost',
		 'port' => 26379,]
	];
	/**
	 * @var array connection config (db, password, etc)
	 */
	public $connection = [
		'host'     => 'localhost',
		'port'     => 6379,
		'database' => 0,
		'password' => null
	];

	protected function getValue($key)
	{
		return $this->adapter->{__FUNCTION__}($key);
	}

	protected function getValues($keys)
	{
		return $this->adapter->{__FUNCTION__}($keys);
	}

	protected function setValue($key, $value, $expire)
	{
		return $this->adapter->{__FUNCTION__}($key, $value, $expire);
	}

	protected function addValue($key, $value, $expire)
	{
		return $this->adapter->{__FUNCTION__}($key, $value, $expire);
	}

	protected function deleteValue($key)
	{
		return $this->adapter->{__FUNCTION__}($key);
	}

	protected function flushValues()
	{
		return $this->adapter->{__FUNCTION__}();
	}

	protected function connect()
	{
		try {
			$this->_discovery = $discovery = new MasterDiscovery($this->clusterName);
			foreach ($this->sentinels as $k => $sentinel) {
				$this->_sentinels[$k] = $client = new Client($sentinel['host'], $sentinel['port'], new Adapter($this->connection));
				$this->_discovery->addSentinel($client);
			}
			$this->adapter = new HighAvailabilityClient($discovery);
			/** @noinspection PhpUndefinedMethodInspection | this exist in underlying client realization */
			$this->adapter->select();
		} catch (Exception $e) {
			throw new CException('Redis cluster error: ' . $e->getMessage(), 0, $e);
		}
	}

	public function executeCommand($name, $params = array())
	{
		if (!$this->adapter)
			$this->connect();
		//proxy to underlying adapter & client
		return $this->adapter->{__FUNCTION__}($name, $params);
	}
}