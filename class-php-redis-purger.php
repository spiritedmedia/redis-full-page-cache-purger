<?php
/**
 * Class file for PHP_Redis_Purger
 *
 * @category Components
 * @package WordPress
 * @subpackage Redis_Full_Page_Cache_Purger
 * @author Spirited Media <contact@spiritedmedia.com>
 * @license MIT
 * @link https://spiritedmedia.com
 * @since 0.0.1
 */

/**
 * Handle purging Redis via the built-in PHP Redis class
 */
class PHP_Redis_Purger {

	/**
	 * PHP Redis API object
	 *
	 * @var object $redis_object PHP Redis API object
	 */
	public $redis_object;

	/**
	 * Get an instance of this class
	 */
	public static function get_instance() {
		static $instance = null;
		if ( null === $instance ) {
			$instance = new static();
			$instance->setup();
			$instance->setup_actions();
		}
		return $instance;
	}

	/**
	 * Setup connection to Redis
	 */
	public function setup() {

		if ( ! class_exists( 'Redis' ) ) {
			return;
		}

		try {
			$connection_details = Redis_Full_Page_Cache_Purger::get_connection_details();
			$this->redis_object = new Redis();
			$this->redis_object->connect(
				$connection_details->host,
				$connection_details->port,
				5
			);

		} catch ( Exception $e ) {
			erorr_log( $e->getMessage() );
		}
	}

	/**
	 * Hook into the Redis purger via actions
	 */
	public function setup_actions() {
		add_action( 'redis_cache_purge/purge_single_key', array( $this, 'delete_single_key' ) );
		add_action( 'redis_cache_purge/purge_wildcard_key', array( $this, 'delete_keys_by_wildcard' ) );
	}

	/**
	 * Delete a single key from Redis
	 * e.g. $key can be nginx-cache:httpGETexample.com/
	 *
	 * @param string $key Cache key to be deleted.
	 */
	public function delete_single_key( $key ) {
		try {
			$this->redis_object->del( $key );
		} catch ( Exception $e ) {
			erorr_log( $e->getMessage() );
		}
	}

	/**
	 * Delete one or more keys based on wildcard pattern
	 * e.g. $key can be nginx-cache:httpGETexample.com*
	 *
	 * Lua Script block to delete multiple keys using wildcard
	 * script will return number of keys deleted
	 * if return value is 0, that means no matches were found
	 *
	 * Call redis eval and return value from lua script
	 *
	 * @param string $pattern Cache key pattern.
	 */
	public function delete_keys_by_wildcard( $pattern ) {

		// Lua Script.
		$lua = <<<LUA
local k =  0
for i, name in ipairs(redis.call('KEYS', KEYS[1]))
do
	redis.call('DEL', name)
	k = k+1
end
return k
LUA;

		try {
			$this->redis_object->eval( $lua, array( $pattern ), 1 );
		} catch ( Exception $e ) {
			error_log( $e->getMessage() );
		}
	}

}

PHP_Redis_Purger::get_instance();
