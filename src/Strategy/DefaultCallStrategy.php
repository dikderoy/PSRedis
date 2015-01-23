<?php
/**
 * Part of RedisGuard 2015
 * Created by: deroy on 23.01.15:18:01
 */

namespace RedisGuard\Strategy;

use RedisGuard\Exception\ConnectionError;
use RedisGuard\Exception\ReadOnlyError;

class DefaultCallStrategy implements ICallStrategy
{
	/**
	 * use call_user_func_array() to proxy call to $node,
	 *
	 * before or after you can define whatever to throw ReadOnlyError to let
	 * HighAvailabilityClient try redirect call to Master (if on Slave connection)
	 * or fail with ConnectionError
	 *
	 * @param array|callable $target - callable for first argument of call_user_func_array
	 * @param array          $arguments
	 *
	 * @throws ReadOnlyError
	 * @throws ConnectionError
	 * @return mixed
	 */
	public function proxyCall($target, array $arguments = [])
	{
		return call_user_func_array($target, $arguments);
	}
}