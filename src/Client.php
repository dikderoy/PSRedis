<?php

namespace RedisGuard;

use RedisGuard\Adapter\IClientAdapter;

/**
 * Class Client
 *
 * Represents one single sentinel or redis node and provides identification if we want to connect to it
 *
 * @package RedisGuard
 */
class Client
{
	const ROLE_MASTER   = 'master';
	const ROLE_SENTINEL = 'sentinel';
	const ROLE_SLAVE    = 'slave';
	const TYPE_REDIS    = 'redis';
	const TYPE_SENTINEL = 'sentinel';
	/**
	 * @var string
	 */
	protected $host;
	/**
	 * @var integer
	 */
	protected $port;
	/**
	 * Client adapter
	 * @var \RedisGuard\Adapter\AbstractClientAdapter
	 */
	protected $adapter;

	public function __construct($host, $port, IClientAdapter $uninitializedClientAdapter = null, $connectionType = self::TYPE_SENTINEL)
	{
		$this->host = $host;
		$this->port = $port;

		if (empty($uninitializedClientAdapter)) {
			$uninitializedClientAdapter = new Adapter([], $connectionType);
		}
		$this->adapter = $this->initializeClientAdapter($uninitializedClientAdapter);
	}

	public function __call($method, array $arguments = array())
	{
		return call_user_func_array(array($this->adapter, $method), $arguments);
	}

	/**
	 * @return string
	 */
	public function getHost()
	{
		return $this->host;
	}

	/**
	 * @return int
	 */
	public function getPort()
	{
		return $this->port;
	}

	public function getAdapter()
	{
		return $this->adapter;
	}

	/**
	 * @param string $nameOfNodeSet cluster name to ask master of
	 *
	 * @return Client
	 */
	public function getMaster($nameOfNodeSet)
	{
		return $this->adapter->getMaster($nameOfNodeSet);
	}

	/**
	 * @param $nameOfNodeSet
	 *
	 * @return IClientAdapter
	 */
	public function getSlave($nameOfNodeSet)
	{
		return $this->adapter->getSlave($nameOfNodeSet);
	}

	public function getRole()
	{
		return $this->adapter->getRole();
	}

	public function getRoleType()
	{
		$role = $this->getRole();
		return $role[0];
	}

	private function initializeClientAdapter(IClientAdapter $clientAdapter)
	{
		$clientAdapter->setHost($this->getHost());
		$clientAdapter->setPort($this->getPort());

		return $clientAdapter;
	}

	public function connect()
	{
		$this->adapter->connect();
	}

	public function isConnected()
	{

		return (bool) $this->adapter->isConnected();
	}

	public function isMaster()
	{
		return $this->getRoleType() === Client::ROLE_MASTER;
	}

	public function isSentinel()
	{
		return $this->getRoleType() === Client::ROLE_SENTINEL;
	}

	public function isSlave()
	{
		return $this->getRoleType() === Client::ROLE_SLAVE;
	}
}