<?php

namespace RedisGuard\Adapter;

use RedisGuard\Client;

/**
 * Interface IClientAdapter
 *
 * Implement this to make sure we have everything we need to talk to Sentinel and Redis clients.
 *
 * @package RedisGuard\Client
 */
interface IClientAdapter
{
	/**
	 * IP address of the redis or sentinel connection
	 *
	 * @param $host
	 */
	public function setHost($host);

	/**
	 * Port of redis or sentinel connection
	 *
	 * @param $port
	 */
	public function setPort($port);

	/**
	 * Proxy to connection mechanism of the redis client
	 * @return mixed
	 */
	public function connect();

	/**
	 * Proxy to the client implementation to verify connection status
	 * @return bool
	 */
	public function isConnected();

	/**
	 * returns adapter wrapping client implementation instance with connection to master node
	 *
	 * @param $nameOfNodeSet
	 *
	 * @return Client
	 */
	public function getMaster($nameOfNodeSet);

	/**
	 * returns adapter wrapping client implementation instance with connection to slave node
	 *
	 * @param $nameOfNodeSet
	 *
	 * @return Client
	 */
	public function getSlave($nameOfNodeSet);

	/**
	 * This inspects the role of a server
	 * @see http://redis.io/commands/role
	 * @return mixed
	 */
	public function getRole();

	/**
	 * This proxies actual redis command calls to the redis client implementation
	 *
	 * @param       $method
	 * @param array $arguments
	 *
	 * @return mixed
	 */
	public function __call($method, array $arguments = []);
}