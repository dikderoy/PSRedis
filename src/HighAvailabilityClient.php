<?php


namespace RedisGuard;

use RedisGuard\Exception\ConnectionError;
use RedisGuard\Exception\ReadOnlyError;

/**
 * Class HighAvailabilityClient
 *
 * High Availability Client will proxy all method calls to the actual client implementation connecting to your master
 * node after initiating the discovery process.  Upon connection failures, this client will rediscover the master
 * node and retry the failed command
 *
 * @package RedisGuard
 */
class HighAvailabilityClient
{
	const READ_WRITE = Discovery::MODE_RW;
	const CAN_READ   = Discovery::MODE_ANY;
	/**
	 * @var string how tolerate connection hangups
	 */
	protected $tolerance;
	/**
	 * Holds all configuration to the sentinels to execute the master discovery process
	 * @var Discovery
	 */
	protected $discovery;
	/**
	 * The master node to connect to
	 * @var Client
	 */
	protected $node;

	/**
	 * @param Discovery $discovery
	 * @param int       $tolerance how tolerate connections on hangup
	 */
	public function __construct(Discovery $discovery, $tolerance = self::READ_WRITE)
	{
		$this->discovery = $discovery;
		$this->tolerance = $tolerance;
	}

	/**
	 * Investigates whether we have a connection to execute commands
	 *
	 * @return bool
	 */
	protected function nodeIsUnknown()
	{
		return ($this->node === null);
	}

	/**
	 * Removes the current master after connection errors so that we are forced to start the discovery process again
	 * on the next command proxy
	 *
	 * @return void
	 */
	protected function invalidateConnection()
	{
		$this->node = null;
	}

	/**
	 * We assume that calls to non-existing methods have a corresponding method in the redis client that is being used.
	 * We therefore proxy the request to the current master and if it fails because of connection errors, we attempt
	 * to rediscover the master so that we can re-try the command on that server.
	 *
	 * @param       $method
	 * @param array $arguments
	 *
	 * @return mixed
	 * @throws ConnectionError
	 * @throws \Exception
	 */
	public function __call($method, array $arguments = array())
	{
		try {
			if ($this->nodeIsUnknown()) {
				$this->node = $this->discovery->getNode($this->tolerance);
			}
			return $this->proxyCallToNode($method, $arguments);
		} catch (ReadOnlyError $e) {
			// retry proxying the function only once.  When back-off is needed, it should be implemented in the Discovery object
			$this->node = $this->discovery->getMaster();
			return $this->proxyCallToNode($method, $arguments);
		} catch (ConnectionError $e) {
			if($this->tolerance > self::CAN_READ)
				throw $e;
			$this->node = $this->discovery->getSlave();
			return $this->proxyCallToNode($method, $arguments);
		}
	}

	/**
	 * Proxies a call to a non-existing method in this object to the redis client
	 *
	 * @param       $name
	 * @param array $arguments
	 *
	 * @return mixed
	 */
	private function proxyCallToNode($name, array $arguments)
	{
		return call_user_func_array(array($this->node, $name), $arguments);
	}
}