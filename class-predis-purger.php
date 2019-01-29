<?php
/**
 * Class file for Predis_Purger
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
 * Handle purging Redis via the 3rd party Predis class
 */
class Predis_Purger {

	/**
	 * Predis API object
	 *
	 * @var object $redis_object Predis API object
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

		if ( ! class_exists( 'Predis\Autoloader' ) ) {
			require_once REDIS_CACHE_PURGE_PLUGIN_DIR_PATH . 'predis.php';
		}

		Predis\Autoloader::register();

		$connection_details = Redis_Full_Page_Cache_Purger::get_connection_details();
		$this->redis_object = new Predis\Client(
			array(
				'host' => $connection_details->host,
				'port' => $connection_details->port,
			)
		);

		try {
			$this->redis_object->connect();
		} catch ( Exception $e ) {
			error_log( $e->getMessage() );
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
			$this->redis_object->executeRaw( array( 'DEL', $key ) );
		} catch ( Exception $e ) {
			error_log( $e->getMessage() );
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
			return $this->redis_object->eval( $lua, 1, $pattern );
		} catch ( Exception $e ) {
			error_log( $e->getMessage() );
		}
	}

}

Predis_Purger::get_instance();
