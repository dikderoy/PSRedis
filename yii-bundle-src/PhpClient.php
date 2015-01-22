<?php
/**
 * Part of swisstokLK 2015
 * Created by: deroy on 13.01.15:18:23
 */

namespace RedisGuard;

use CComponent;
use CException;
use Exception;
use RedisGuard\Exception\ConnectionError;
use RedisGuard\Exception\ReadOnlyError;
use Yii;

class PhpClient extends CComponent
{
	/**
	 * @var resource redis socket connection
	 */
	protected $_socket;
	/**
	 * @var string hostname to use for connecting to the redis server. Defaults to 'localhost'.
	 */
	public $host = 'localhost';
	/**
	 * @var int the port to use for connecting to the redis server. Default port is 6379.
	 */
	public $port = 6379;
	/**
	 * @var string the password to use to authenticate with the redis server. If not set, no AUTH command will be sent.
	 */
	public $password;
	/**
	 * @var int the redis database to use. This is an integer value starting from 0. Defaults to 0.
	 */
	public $database = 0;
	/**
	 * @var float timeout to use for connection to redis. If not set the timeout set in php.ini will be used: ini_get("default_socket_timeout")
	 */
	public $timeout = null;

	/**
	 * Retrieves a value from cache with a specified key.
	 * This is the implementation of the method declared in the parent class.
	 *
	 * @param string $key a unique key identifying the cached value
	 *
	 * @return string|boolean the value stored in cache, false if the value is not in the cache or expired.
	 */
	protected function getValue($key)
	{
		return $this->executeCommand('GET', array($key));
	}

	/**
	 * Retrieves multiple values from cache with the specified keys.
	 *
	 * @param array $keys a list of keys identifying the cached values
	 *
	 * @return array a list of cached values indexed by the keys
	 */
	protected function getValues($keys)
	{
		$response = $this->executeCommand('MGET', $keys);
		$result   = array();
		$i        = 0;
		foreach ($keys as $key)
			$result[$key] = $response[$i++];
		return $result;
	}

	/**
	 * Stores a value identified by a key in cache.
	 * This is the implementation of the method declared in the parent class.
	 *
	 * @param string  $key the key identifying the value to be cached
	 * @param string  $value the value to be cached
	 * @param integer $expire the number of seconds in which the cached value will expire. 0 means never expire.
	 *
	 * @return bool true if the value is successfully stored into cache, false otherwise
	 * @throws ReadOnlyError
	 */
	protected function setValue($key, $value, $expire)
	{
		try {
			if ($expire == 0)
				return (bool) $this->executeCommand('SET', array($key, $value));
			return (bool) $this->executeCommand('SETEX', array($key, $expire, $value));
		} catch (ConnectionError $e) {
			throw new ReadOnlyError($e);
		}
	}

	/**
	 * Reads the result from socket and parses it
	 * @return array|bool|null|string
	 * @throws CException socket or data problems
	 */
	protected function parseResponse()
	{
		if (($line = fgets($this->_socket)) === false)
			throw new CException('Failed reading data from redis connection socket.');
		$type = $line[0];
		$line = substr($line, 1, -2);
		switch ($type) {
			case '+': // Status reply
				return true;
			case '-': // Error reply
				throw new CException('Redis error: ' . $line);
			case ':': // Integer reply
				// no cast to int as it is in the range of a signed 64 bit integer
				return $line;
			case '$': // Bulk replies
				if ($line == '-1')
					return null;
				$length = $line + 2;
				$data   = '';
				while ($length > 0) {
					if (($block = fread($this->_socket, $length)) === false)
						throw new CException('Failed reading data from redis connection socket.');
					$data .= $block;
					$length -= (function_exists('mb_strlen') ? mb_strlen($block, '8bit') : strlen($block));
				}
				return substr($data, 0, -2);
			case '*': // Multi-bulk replies
				$count = (int) $line;
				$data  = array();
				for ($i = 0; $i < $count; $i++)
					$data[] = $this->parseResponse();
				return $data;
			default:
				throw new CException('Unable to parse data received from redis.');
		}
	}

	/**
	 * Stores a value identified by a key into cache if the cache does not contain this key.
	 * This is the implementation of the method declared in the parent class.
	 *
	 * @param string  $key the key identifying the value to be cached
	 * @param string  $value the value to be cached
	 * @param integer $expire the number of seconds in which the cached value will expire. 0 means never expire.
	 *
	 * @return bool true if the value is successfully stored into cache, false otherwise
	 * @throws ReadOnlyError
	 */
	protected function addValue($key, $value, $expire)
	{
		try {
			if ($expire == 0)
				return (bool) $this->executeCommand('SETNX', array($key, $value));

			if ($this->executeCommand('SETNX', array($key, $value))) {
				$this->executeCommand('EXPIRE', array($key, $expire));
				return true;
			} else
				return false;
		} catch (ConnectionError $e) {
			throw new ReadOnlyError($e);
		}
	}

	/**
	 * Deletes a value with the specified key from cache
	 * This is the implementation of the method declared in the parent class.
	 *
	 * @param string $key the key of the value to be deleted
	 *
	 * @return bool if no error happens during deletion
	 * @throws ReadOnlyError
	 */
	protected function deleteValue($key)
	{
		try {
			return (bool) $this->executeCommand('DEL', array($key));
		} catch (ConnectionError $e) {
			throw new ReadOnlyError($e);
		}
	}

	/**
	 * Deletes all values from cache.
	 * This is the implementation of the method declared in the parent class.
	 * @return bool whether the flush operation was successful.
	 * @throws ReadOnlyError
	 */
	protected function flushValues()
	{
		try {
			return $this->executeCommand('FLUSHDB');
		} catch (ConnectionError $e) {
			throw new ReadOnlyError($e);
		}
	}

	/**
	 * get slaves info (sentinel connection only)
	 *
	 * @param $nameOfNodeSet
	 *
	 * @return array|bool
	 */
	public function sentinelGetSlaves($nameOfNodeSet)
	{
		$response = $this->executeCommand('SENTINEL', ['slaves', $nameOfNodeSet]);
		if (empty($response) || !is_array($response) || !isset($response[0]))
			return false;

		$info = [];
		foreach ($response as $k => $slave) {
			$length = count($slave);
			for ($i = 0; $i < $length; $i++) {
				$info[$k][$slave[$i]] = $slave[++$i];
			}
		}
		return $info;
	}

	/**
	 * get master `[ip,port]`
	 *
	 * @param $nameOfNodeSet
	 *
	 * @return array|bool
	 */
	public function sentinelGetMaster($nameOfNodeSet)
	{
		$response = $this->executeCommand('SENTINEL', ['get-master-addr-by-name', $nameOfNodeSet]);
		if (is_array($response) && count($response) == 2)
			return $response;
		return false;
	}

	/**
	 * get server info
	 *
	 * @param string|null $section
	 *
	 * @return array|bool
	 */
	public function info($section = null)
	{
		$response = $this->executeCommand('INFO', [$section]);
		if (!is_array($response) || count($response) < 1)
			return false;
		$info = [];
		foreach ($response as $line) {
			list($k, $v) = explode(':', $line);
			$info[$k] = $v;
		}
		return $info;
	}

	public function role()
	{
		$response = $this->executeCommand('ROLE');
		return (is_array($response)) ? $response : false;
	}

	/**
	 * Proxy to the client implementation to verify connection status
	 *
	 * @return bool
	 */
	public function isConnected()
	{
		return (bool) ($this->_socket);
	}

	/**
	 * Establishes a connection to the redis server.
	 * It does nothing if the connection has already been established.
	 * @throws CException if connecting fails
	 */
	public function connect()
	{
		$this->_socket = @stream_socket_client(
			$this->host . ':' . $this->port,
			$errorNumber,
			$errorDescription,
			$this->timeout ? $this->timeout : ini_get("default_socket_timeout")
		);
		if ($this->_socket) {
			if ($this->password !== null)
				$this->executeCommand('AUTH', array($this->password));
		} else
			throw new ConnectionError('Failed to connect to redis: ' . $errorDescription, (int) $errorNumber);
	}

	/**
	 * connect to particular db in redis
	 *
	 * @param null|int $id redis database id
	 *
	 * @return bool
	 */
	public function select($id = null)
	{
		if ($id === null)
			$id = $this->database;
		return $this->executeCommand('SELECT', array($id));
	}

	/**
	 * Executes a redis command.
	 * For a list of available commands and their parameters see {@link http://redis.io/commands}.
	 *
	 * @param string $name the name of the command
	 * @param array  $params list of parameters for the command
	 *
	 * @return array|bool|null|string Dependend on the executed command this method
	 * will return different data types:
	 * <ul>
	 *   <li><code>true</code> for commands that return "status reply".</li>
	 *   <li><code>string</code> for commands that return "integer reply"
	 *       as the value is in the range of a signed 64 bit integer.</li>
	 *   <li><code>string</code> or <code>null</code> for commands that return "bulk reply".</li>
	 *   <li><code>array</code> for commands that return "Multi-bulk replies".</li>
	 * </ul>
	 * See {@link http://redis.io/topics/protocol redis protocol description}
	 * for details on the mentioned reply types.
	 * @trows CException for commands that return {@link http://redis.io/topics/protocol#error-reply error reply}.
	 */
	public function executeCommand($name, $params = array())
	{
		if ($this->_socket === null)
			$this->connect();
		Yii::trace(__FUNCTION__ . " on {$this->host}:{$this->port}", 'ext.sentinel.underlying.' . static::class);

		array_unshift($params, $name);
		$command = '*' . count($params) . "\r\n";
		foreach ($params as $arg)
			$command .= '$' . strlen($arg) . "\r\n" . $arg . "\r\n";
		fwrite($this->_socket, $command);
		return $this->parseResponse(implode(' ', $params));
	}
}