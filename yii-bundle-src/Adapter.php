<?php
/**
 * Part of swisstokLK 2015
 * Created by: deroy on 13.01.15:16:07
 */

namespace RedisGuard;

use CException;
use RedisGuard\Adapter\AbstractClientAdapter;
use RedisGuard\Adapter\IClientAdapter;
use RedisGuard\Exception\SentinelError;
use RedisGuard\PhpClient as RedisClient;
use Yii;

class Adapter extends AbstractClientAdapter implements IClientAdapter
{
	/**
	 * This proxies actual redis command calls to the redis client implementation
	 *
	 * @param       $method
	 * @param array $arguments
	 *
	 * @return mixed
	 */
	public function __call($method, array $arguments = [])
	{
		return call_user_func_array([$this->client, $method], $arguments);
	}

	/**
	 * IP address of the redis or sentinel connection
	 *
	 * @param $ipAddress
	 *
	 * @return mixed
	 */
	public function setHost($ipAddress)
	{
		$this->host = $ipAddress;
	}

	/**
	 * Port of redis or sentinel connection
	 *
	 * @param $port
	 *
	 * @return mixed
	 */
	public function setPort($port)
	{
		$this->port = $port;
	}

	public function getMaster($nameOfNodeSet)
	{
		if ($response = $this->getClient()->sentinelGetMaster($nameOfNodeSet))
			list($masterIp, $masterPort) = $response;
		if (isset($masterIp, $masterPort) && !empty($masterIp) && !empty($masterPort))
			return new Client($masterIp, $masterPort, new static($this->options, Client::ROLE_MASTER), Client::TYPE_REDIS);
		throw new SentinelError('The sentinel does not know the master address');
	}

	public function getSlave($nameOfNodeSet)
	{
		if ($info = $this->getSlaveInfo($nameOfNodeSet))
			return new Client($info[0]['ip'], $info[0]['port'], new static($this->options, Client::ROLE_SLAVE), Client::TYPE_REDIS);
		throw new SentinelError('Slave not available');
	}

	public function getSlaveInfo($nameOfNodeSet)
	{
		if ($response = $this->getClient()->sentinelGetSlaves($nameOfNodeSet))
			return $response;
		throw new SentinelError('can not retrieve slaves info');
	}

	/**
	 * This inspects the role of a server
	 * @see http://redis.io/commands/role
	 * @return mixed
	 * @throws \RedisGuard\Exception\SentinelError
	 */
	public function getRole()
	{
		if ($role = $this->getClient()->role())
			return $role;
		throw new SentinelError('incorrect response, maybe ROLE command not supported, check Redis version is >2.8');
	}

	/**
	 * get underlying client
	 *
	 * @return RedisClient
	 * @throws CException
	 */
	protected function getClient()
	{
		if (!$this->client) {
			$this->client = Yii::createComponent(
				array_merge(
					$this->options,
					['class' => RedisClient::class,
					 'host'  => $this->host,
					 'port'  => $this->port]));
		}
		return $this->client;
	}

	/**
	 * Proxy to the client implementation to verify connection status
	 * @return bool
	 */
	public function isConnected()
	{
		return $this->getClient()->isConnected();
	}

	/**
	 * Establishes a connection to the redis server.
	 * It does nothing if the connection has already been established.
	 * @throws CException if connecting fails
	 */
	public function connect()
	{
		if (!$this->isConnected())
			$this->getClient()->connect();
	}
}