<?php
/**
 * Plugin Name:       Redis Full Page Cache Purger
 * Plugin URI:        https://github.com/kingkool68
 * Description:       Purges keys from Redis when certain WordPress events happen. Pairs well with EasyEngine.
 * Version:           0.0.1
 * Author:            Russell Heimlich, Chris Montgomery
 * Requires at least: 3.0
 * Tested up to: 4.9.8
 *
 * @package Redis_Full_Page_Cache_Purger
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
* Define constants
*/
$constants_to_check = array(
	'REDIS_CACHE_PURGE_PLUGIN_DIR_PATH' => plugin_dir_path( __FILE__ ),
	'REDIS_CACHE_PURGE_PREFIX'          => 'nginx-cache:',
	'REDIS_CACHE_PURGE_HOST'            => '127.0.0.1',
	'REDIS_CACHE_PURGE_PORT'            => '6379',
	'REDIS_CACHE_PURGE_LOGGING'         => false,
);

foreach ( $constants_to_check as $constant => $default_value ) {
	if ( ! defined( $constant ) ) {
		define( $constant, $default_value );
	}
}

require REDIS_CACHE_PURGE_PLUGIN_DIR_PATH . 'class-redis-full-page-cache-purger.php';
