# Redis Full Page Cache Purger

Purges keys from Redis when certain WordPress events happen. Pairs well with [EasyEngine](https://easyengine.io/). Based off of the [nginx Helper plugin](https://github.com/rtCamp/nginx-helper). There is no UI. Everything can be configured using constants, actions, and filters.

## Installation

via Composer

```
composer require spiritedmedia/redis-full-page-cache-purger
```

or manually download this plugin and place it in your `/wp-content/plugins/` folder

## How does it work?

When certain actions in WordPress happen (update post, edit term etc.) we determine URLs that need to be flushed and then tell Redis to delete the keys. URLs are transformed into a key that Redis uses to store the full page contents for caching purposes based off of the [Open Resty SRCache nginx module](https://github.com/rtCamp/nginx-helper). Redis purgers can hook into two actions which handle actually talking to Redis and deleting keys.

## Constants
  - `REDIS_CACHE_PURGE_PLUGIN_DIR_PATH` - The main path of the plugin for including other files. Default: `plugin_dir_path( __FILE__ )`
  - `REDIS_CACHE_PURGE_PREFIX` - The prefix used for the cache key. Default: `nginx-cache:`
  - `REDIS_CACHE_PURGE_HOST` - Host or URL to connect to Redis. Default: `127.0.0.1`
  - `REDIS_CACHE_PURGE_PORT` - Port for connecting to Redis. Default: `6379`

## Actions
  - `redis_cache_purge/purge_single_key` - Hook for a purger to listen for single keys to purge from Redis. A cache key is passed like `nginx-cache:httpGETexample.com/`
  - `redis_cache_purge/purge_wildcard_key` - Hook for a purger to listen for a wildcard key to purge from Redis. A cache key is passed like `nginx-cache:httpGETexample.com*`

## Filters
  - `redis_cache_purge/purge_comment` - URLs to be purged when a comment is added or edited. Arguments: `$url`, `$comment`
  - `redis_cache_purge/purge_user` - URLs to be purged when a user is deleted. Arguments: `$url`, `$user_id`
  - `redis_cache_purge/purge_post` - URLs to be purged when a post is edited. Arguments: `$urls`, `$post`
  - `redis_cache_purge/cache_prefix` - The cache key prefix. Arguments: `REDIS_CACHE_PURGE_PREFIX` (The default cache prefix)
  - `redis_cache_purge/cache_key` - The cache key to purge from Redis. Arguments: `$cache_key`, `$url`, `$prefix`
  - `redis_cache_purge/purge_all` - URL to be purged when all URLs are requested to be purged. Arguments: `$url`
