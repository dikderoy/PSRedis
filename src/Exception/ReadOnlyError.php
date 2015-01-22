<?php
/**
 * Part of RedisGuard 2015
 * Created by: deroy on 22.01.15:19:27
 */

namespace RedisGuard\Exception;

class ReadOnlyError extends ConnectionError
{
	public function __construct(\Exception $previous)
	{
		parent::__construct($previous->getMessage(), $previous->getCode(), $previous);
	}
}