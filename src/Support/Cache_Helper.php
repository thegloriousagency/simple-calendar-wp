<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Support;

use Glorious\ChurchEvents\Post_Types\Event_Post_Type;
use function apply_filters;
use function defined;
use function get_locale;
use function get_option;
use function get_post_type;
use function get_transient;
use function set_transient;
use function update_option;
use const HOUR_IN_SECONDS;
use const MINUTE_IN_SECONDS;

/**
 * Thin wrapper around WordPress transients for plugin-level caching.
 *
 * Caches are segmented per "group" (month HTML vs. JSON events) with simple
 * version bumping for invalidation when event data changes.
 */
final class Cache_Helper
{
    private const GROUP_MONTH = 'month';
    private const GROUP_EVENTS = 'events';
    private const VERSIONS_OPTION = 'cec_cache_versions';

    /**
     * Registers hooks that flush caches when church events change.
     */
    public static function register_invalidation_hooks(): void
    {
        Hooks::add_action('save_post_' . Event_Post_Type::POST_TYPE, [self::class, 'flush_all_caches'], 10, 0);
        Hooks::add_action('trashed_post', [self::class, 'flush_if_event'], 10, 1);
        Hooks::add_action('deleted_post', [self::class, 'flush_if_event'], 10, 1);
        Hooks::add_action('created_church_event_category', [self::class, 'flush_all_caches'], 10, 0);
        Hooks::add_action('edited_church_event_category', [self::class, 'flush_all_caches'], 10, 0);
        Hooks::add_action('delete_church_event_category', [self::class, 'flush_all_caches'], 10, 0);
        Hooks::add_action('created_church_event_tag', [self::class, 'flush_all_caches'], 10, 0);
        Hooks::add_action('edited_church_event_tag', [self::class, 'flush_all_caches'], 10, 0);
        Hooks::add_action('delete_church_event_tag', [self::class, 'flush_all_caches'], 10, 0);

        self::register_polylang_cache_hooks();
    }

    /**
     * Snapshot of current cache versions.
     *
     * @return array{month:int, events:int}
     */
    public static function get_versions(): array
    {
        $versions = self::get_versions_array();

        return [
            'month' => (int) $versions[self::GROUP_MONTH],
            'events' => (int) $versions[self::GROUP_EVENTS],
        ];
    }

    /**
     * Flushes all cache groups when a church event post is modified outside of save_post_{post_type}.
     */
    public static function flush_if_event(int $post_id): void
    {
        if (get_post_type($post_id) !== Event_Post_Type::POST_TYPE) {
            return;
        }

        self::flush_all_caches();
    }

    /**
     * Bumps cache versions for both groups, effectively invalidating all cached entries.
     */
    public static function flush_all_caches(): void
    {
        self::bump_version(self::GROUP_MONTH);
        self::bump_version(self::GROUP_EVENTS);
    }

    /**
     * Attempts to fetch a cached value by key.
     *
     * @return mixed|null
     */
    public static function get_cached(string $key)
    {
        if (! self::is_cache_enabled()) {
            return null;
        }

        $value = get_transient($key);

        return $value === false ? null : $value;
    }

    /**
     * Stores a cache value with the provided TTL.
     *
     * @param mixed $value
     */
    public static function set_cached(string $key, $value, int $ttl): void
    {
        if (! self::is_cache_enabled()) {
            return;
        }

        set_transient($key, $value, $ttl);
    }

    /**
     * Returns the cache key for a month-view HTML response.
     */
    public static function build_month_cache_key(
        int $year,
        int $month,
        string $category,
        ?string $locale = null,
        ?string $language = null
    ): string {
        return sprintf(
            'cec_month_%d_%d_%02d_%s_%s_%s',
            self::get_version(self::GROUP_MONTH),
            $year,
            $month,
            self::hash_fragment($category),
            self::hash_fragment($locale ?? get_locale() ?: 'default'),
            self::hash_fragment($language ?? 'none')
        );
    }

    /**
     * Returns the cache key for JSON events data.
     */
    public static function build_events_cache_key(
        string $start,
        string $end,
        string $category,
        string $tag,
        string $limit,
        ?string $locale = null,
        ?string $language = null
    ): string {
        return sprintf(
            'cec_events_%d_%s_%s_%s_%s_%s_%s_%s',
            self::get_version(self::GROUP_EVENTS),
            self::hash_fragment($start),
            self::hash_fragment($end),
            self::hash_fragment($category),
            self::hash_fragment($tag),
            self::hash_fragment($limit),
            self::hash_fragment($locale ?? get_locale() ?: 'default'),
            self::hash_fragment($language ?? 'none')
        );
    }

    /**
     * Default TTL for month cache (filterable).
     */
    public static function get_month_cache_ttl(): int
    {
        return (int) apply_filters('cec_month_cache_ttl', 6 * HOUR_IN_SECONDS);
    }

    /**
     * Default TTL for JSON events cache (filterable).
     */
    public static function get_events_cache_ttl(): int
    {
        return (int) apply_filters('cec_events_cache_ttl', 10 * MINUTE_IN_SECONDS);
    }

    /**
     * Determines whether caching is enabled (filterable).
     */
    public static function is_cache_enabled(): bool
    {
        return (bool) apply_filters('cec_enable_cache', true);
    }

    /**
     * Retrieves the current version for the provided cache group.
     */
    private static function get_version(string $group): int
    {
        $versions = self::get_versions_array();

        return (int) $versions[$group];
    }

    /**
     * Increments the version for a cache group, invalidating existing keys.
     */
    private static function bump_version(string $group): void
    {
        $versions = self::get_versions_array();
        $versions[$group] = (int) (($versions[$group] ?? 1) + 1);
        update_option(self::VERSIONS_OPTION, $versions, false);
    }

    /**
     * Generates a short hash fragment for cache keys.
     */
    private static function hash_fragment(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            $value = 'none';
        }

        return substr(md5($value), 0, 10);
    }

    /**
     * Ensures both cache versions exist in the options table.
     *
     * @return array<string, int>
     */
    private static function get_versions_array(): array
    {
        $versions = (array) get_option(self::VERSIONS_OPTION, []);
        $changed = false;

        foreach ([self::GROUP_MONTH, self::GROUP_EVENTS] as $group) {
            if (! isset($versions[$group])) {
                $versions[$group] = 1;
                $changed = true;
            }
        }

        if ($changed) {
            update_option(self::VERSIONS_OPTION, $versions, false);
        }

        return $versions;
    }

    private static function register_polylang_cache_hooks(): void
    {
        if (! defined('POLYLANG_VERSION')) {
            return;
        }

        Hooks::add_action('pll_save_post', [self::class, 'flush_if_event'], 10, 1);
        Hooks::add_action('pll_translation_linked', [self::class, 'flush_if_event'], 10, 1);
    }
}

