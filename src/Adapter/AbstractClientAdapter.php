<?php

namespace RedisGuard\Adapter;

use RedisGuard\Client;

/**
 * Class AbstractClientAdapter
 *
 * Common functionality to other client adapters.
 *
 * @package RedisGuard\Client\Adapter
 */
abstract class AbstractClientAdapter
{
	/** @var  Client */
	protected $client;
	/**
	 * connection config (database,password, etc),
	 * will be passed to actual client
	 * @var array
	 */
	protected $options = [];
	/**
	 * @var string hostname to use for connecting to the redis server. Defaults to 'localhost'.
	 */
	protected $host = 'localhost';
	/**
	 * @var int the port to use for connecting to the redis server. Default port is 6379.
	 */
	protected $port = 6379;

	public function __construct(array $options = [])
	{
		$this->options = $options;
	}

	public function setHost($host)
	{
		$this->host = $host;
	}

	public function setPort($port)
	{
		$this->port = $port;
	}
}