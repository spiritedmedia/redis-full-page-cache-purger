<?php
/**
 * Class file for Redis_Full_Page_Cache_Purger
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
 * Purge Redis keys after certain WordPress events.
 */
class Redis_Full_Page_Cache_Purger {

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
	 * Load required dependencies to talk to Redis depending on what is installed
	 */
	public function setup() {
		if ( class_exists( 'Redis' ) ) { // Use PHP5-Redis extension if installed.
			require_once REDIS_CACHE_PURGE_PLUGIN_DIR_PATH . 'class-php-redis-purger.php';
		} else {
			require_once REDIS_CACHE_PURGE_PLUGIN_DIR_PATH . 'class-predis-purger.php';
		}
	}

	/**
	 * Hook into WordPress via actions
	 */
	public function setup_actions() {
		add_action( 'post_updated', array( $this, 'purge_post' ), 20, 3 );
		add_action( 'delete_post', array( $this, 'purge_post' ), 20, 2 );
		add_action( 'transition_comment_status', array( $this, 'action_transition_comment_status' ), 20, 3 );

		add_action( 'edit_terms', array( $this, 'action_edit_terms' ), 20, 2 );
		add_action( 'pre_delete_term', array( $this, 'action_edit_terms' ), 20, 2 );

		add_action( 'delete_user', array( $this, 'action_delete_user' ), 20, 1 );
	}

	/**
	 * Handle purging a post when a comment is transitioned
	 *
	 * @param  string     $new_status New comment status.
	 * @param  string     $old_status Old comment status.
	 * @param  WP_Comment $comment    The comment object being modified.
	 */
	public function action_transition_comment_status( $new_status = '', $old_status = '', $comment ) {
		if ( ! empty( $comment->$comment_post_ID ) ) {
			$url = get_permalink( $comment->comment_post_ID ) . '*';
			$url = apply_filters( 'redis_cache_purge/purge_comment', $url, $comment );
			static::log( 'Purging comments for post ' . $comment->comment_post_ID );
			static::purge( $urls );
		}
	}

	/**
	 * Purge URLs when a term is modified
	 *
	 * @param  integer $term_id       ID of the term being modified.
	 * @param  string  $taxonomy_slug Taxonomy of the term being modified.
	 */
	public function action_edit_terms( $term_id = 0, $taxonomy_slug = '' ) {
		$taxonomy = get_taxonomy( $taxonomy_slug );
		if ( ! is_object( $taxonomy ) ) {
			return;
		}

		// Check if the taxonomy is public,
		// if not there is nothing to purge from cache.
		if ( empty( $taxonomy->public ) || ! $taxonomy->public ) {
			return;
		}
		$urls   = array();
		$urls[] = get_term_link( $term_id, $taxonomy );

		$query_args         = array(
			'post_type'     => 'any',
			'post_status'   => 'public',
			'numberposts'   => -1,
			'no_found_rows' => true,
			'tax_query'     => array(
				array(
					'taxonomy' => $taxonomy_slug,
					'field'    => 'id',
					'terms'    => $term_id,
				),
			),
		);
		$tagged_posts_query = new WP_Query( $query_args );
		if ( ! empty( $tagged_posts_query->posts ) ) {
			foreach ( $tagged_posts_query->posts as $post ) {
				$urls[] = get_permalink( $post ) . '*';
			}
		}
		$urls = apply_filters( 'redis_cache_purge/purge_terms', $urls, $term_id, $taxonomy );
		static::log( 'Purging terms (' . $term_id . ', ' . $taxonomy_slug . ')' );
		static::purge( $urls );
	}

	/**
	 * Purge URLs when a user is deleted
	 *
	 * @param  integer $user_id ID of the user being deleted.
	 */
	public function action_delete_user( $user_id = 0 ) {
		$url = get_author_posts_url( $user_id ) . '*';
		$url = apply_filters( 'redis_cache_purge/purge_user', $url, $user_id );
		static::log( 'Purging user (' . $user_id . ')' );
		static::purge( $url );
	}

	/**
	 * Purge URLs related to a post
	 *
	 * @param  WP_Post $post A post object or post ID.
	 */
	public function purge_post( $post ) {
		$post = get_post( $post );

		// Don't need to flush post revisions.
		if ( wp_is_post_revision( $post->ID ) ) {
			return;
		}

		/**
		 * Rejiggering the $post object if the post is a draft so we can get a
		 * real permalink to flush
		 *
		 * BAD: http://example.com/?post_type=foo&p=123
		 * GOOD: http://example.com/foo/2015/12/30/a-draft-post/
		 *
		 * @link http://wordpress.stackexchange.com/a/42988/2744
		 */
		if ( in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft' ) ) ) {
			$post->post_status = 'published';
			$post->post_name   = sanitize_title( $post->post_name ? $post->post_name : $post->post_title, $post->ID );
		}

		$urls   = array();
		$urls[] = get_permalink( $post ) . '*';

		$post_type_archive_url = get_post_type_archive_link( $post->post_type );
		if ( $post_type_archive_url ) {
			$urls[] = $post_type_archive_url . '*';
		}

		$urls[] = trailingslashit( get_site_url() );
		$urls[] = trailingslashit( get_site_url() ) . 'page/*';

		// Flush any archives of terms associated with this post.
		$taxonomies = get_taxonomies(
			array(
				'public' => true,
			)
		);
		$terms      = wp_get_object_terms( $post->ID, $taxonomies );
		foreach ( $terms as $term ) {
			$urls[] = get_term_link( $term ) . '*';
		}

		$urls = apply_filters( 'redis_cache_purge/purge_post', $urls, $post );
		static::log( 'Purging post (' . $post->ID . ')' );
		static::purge( $urls );
	}

	/**
	 * Handle purging URLs from Redis
	 *
	 * @param array|string $urls URLs to be purged.
	 */
	static public function purge( $urls = array() ) {
		if ( is_string( $urls ) ) {
			$urls = array( $urls );
		}
		foreach ( $urls as $url ) {
			if ( filter_var( $url, FILTER_VALIDATE_URL ) === false ) {
				continue;
			}
			$cache_key = static::get_cache_key( $url );
			static::log(
				array(
					'url'       => $url,
					'cache_key' => $cache_key,
				)
			);
			if ( strpos( $cache_key, '*' ) === false ) {
				do_action( 'redis_cache_purge/purge_single_key', $cache_key );
			} else {
				do_action( 'redis_cache_purge/purge_wildcard_key', $cache_key );
			}
		}
	}

	/**
	 * Helper function to easily purge all pages from the full page cache
	 */
	static public function purge_all() {
		$url = trailingslashit( get_site_url() ) . '*';
		$url = apply_filters( 'redis_cache_purge/purge_all', $url );
		static::purge( $url );
	}

	/**
	 * Get the details for connecting to Redis
	 *
	 * @return object Host and port details for connecting to Redis
	 */
	static public function get_connection_details() {
		return (object) array(
			'host' => REDIS_CACHE_PURGE_HOST,
			'port' => REDIS_CACHE_PURGE_PORT,
		);
	}

	/**
	 * Get the cache key for a given URL
	 *
	 * @param string $url The URL generate a cache key for so it can be purged.
	 * @return string     The cache key
	 */
	static public function get_cache_key( $url = '' ) {
		$parse = wp_parse_url( $url );
		if ( empty( $parse['path'] ) ) {
			$parse['path'] = '';
		}
		$prefix    = apply_filters( 'redis_cache_purge/cache_prefix', REDIS_CACHE_PURGE_PREFIX );
		$cache_key = $prefix . $parse['scheme'] . 'GET' . $parse['host'] . $parse['path'];
		return apply_filters( 'redis_cache_purge/cache_key', $cache_key, $url, $prefix );
	}

	/**
	 * Log messages to the error_log
	 */
	static public function log() {
		if ( ! defined( 'REDIS_CACHE_PURGE_LOGGING' ) || ! REDIS_CACHE_PURGE_LOGGING ) {
			return;
		}
		foreach ( func_get_args() as $arg ) {
			if ( is_array( $arg ) || is_object( $arg ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log(
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					print_r( $arg, true )
				);
			} else {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( $arg );
			}
		}
	}
}
Redis_Full_Page_Cache_Purger::get_instance();
