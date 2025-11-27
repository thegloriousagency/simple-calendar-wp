<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Integrations;

use Glorious\ChurchEvents\Post_Types\Event_Post_Type;
use Glorious\ChurchEvents\Post_Types\Location_Post_Type;
use Glorious\ChurchEvents\Meta\Event_Meta_Repository;
use Glorious\ChurchEvents\Support\Hooks;
use Glorious\ChurchEvents\Support\Language_Helper;
use function function_exists;
use function in_array;
use function get_post_meta;
use function update_post_meta;
use function delete_post_meta;
use function get_post_thumbnail_id;
use function set_post_thumbnail;
use function delete_post_thumbnail;

/**
 * Handles Polylang specific registrations (CPT translation + meta sync).
 */
final class Polylang_Integration
{
    /**
     * Meta keys that must remain identical across translations.
     */
    private const SYNC_META_KEYS = [
        Event_Meta_Repository::META_START,
        Event_Meta_Repository::META_END,
        Event_Meta_Repository::META_ALL_DAY,
        Event_Meta_Repository::META_LOCATION,
        Event_Meta_Repository::META_LOCATION_MODE,
        Event_Meta_Repository::META_LOCATION_ID,
        Event_Meta_Repository::META_IS_RECURRING,
        Event_Meta_Repository::META_RECURRENCE_INTERVAL,
        Event_Meta_Repository::META_RECURRENCE_WEEKDAYS,
        Event_Meta_Repository::META_RRULE,
        Event_Meta_Repository::META_EXDATES,
        Event_Meta_Repository::META_RDATES,
    ];
    private const MEDIA_KEYS = ['_thumbnail_id'];

    private static bool $is_syncing = false;

    public function register(): void
    {
        Hooks::add_action('pll_init', [$this, 'register_translatable_post_types']);
        Hooks::add_filter('pll_copy_post_metas', [$this, 'sync_event_meta_keys'], 10, 4);
        Hooks::add_action('save_post_' . Event_Post_Type::POST_TYPE, [$this, 'sync_translations'], 20, 1);
        Hooks::add_action('init', [$this, 'register_strings']);
    }

    /**
     * Registers plugin CPTs with Polylang once the plugin is initialized.
     */
    public function register_translatable_post_types(): void
    {
        if (! function_exists('pll_register_post_type')) {
            return;
        }

        pll_register_post_type(Event_Post_Type::POST_TYPE);
        pll_register_post_type(Location_Post_Type::POST_TYPE);
    }

    /**
     * Ensures target meta keys are always synchronized between translations.
     *
     * @param array<int, string> $meta_keys
     */
    public function sync_event_meta_keys(array $meta_keys, bool $sync, int $from_post_id, int $to_post_id): array
    {
        if (! $sync) {
            return $meta_keys;
        }

        if (! Language_Helper::is_primary_post($from_post_id)) {
            return $meta_keys;
        }

        foreach (self::SYNC_META_KEYS as $key) {
            if (! in_array($key, $meta_keys, true)) {
                $meta_keys[] = $key;
            }
        }

        if (! in_array('_thumbnail_id', $meta_keys, true)) {
            $meta_keys[] = '_thumbnail_id';
        }

        return $meta_keys;
    }

    public function sync_translations(int $post_id): void
    {
        if (self::$is_syncing || ! Language_Helper::is_polylang_active()) {
            return;
        }

        $primary_id = Language_Helper::get_primary_post_id($post_id);
        if (! $primary_id) {
            return;
        }

        $translations = Language_Helper::get_post_translations($post_id);
        if ($translations === []) {
            return;
        }

        $targets = [];
        if ($post_id === $primary_id) {
            foreach ($translations as $translation_id) {
                if ($translation_id === $primary_id) {
                    continue;
                }
                $targets[] = (int) $translation_id;
            }
        } else {
            $targets[] = $post_id;
        }

        if ($targets === []) {
            return;
        }

        $source_meta = $this->collect_meta($primary_id);
        $thumbnail_id = get_post_thumbnail_id($primary_id);

        self::$is_syncing = true;

        foreach ($targets as $translation_id) {
            foreach ($source_meta as $key => $value) {
                if ($value === '' || $value === null || $value === []) {
                    delete_post_meta($translation_id, $key);
                } else {
                    update_post_meta($translation_id, $key, $value);
                }
            }

            $this->sync_thumbnail($translation_id, $thumbnail_id);
        }

        self::$is_syncing = false;
    }

    /**
     * @return array<string, mixed>
     */
    private function collect_meta(int $post_id): array
    {
        $meta = [];

        foreach (self::SYNC_META_KEYS as $key) {
            $meta[$key] = get_post_meta($post_id, $key, true);
        }

        return $meta;
    }

    private function sync_thumbnail(int $target_post_id, ?int $thumbnail_id): void
    {
        if ($thumbnail_id) {
            set_post_thumbnail($target_post_id, $thumbnail_id);

            return;
        }

        delete_post_thumbnail($target_post_id);
    }

    public function register_strings(): void
    {
        if (! function_exists('pll_register_string')) {
            return;
        }

        $strings = [
            'Previous',
            'Next',
            'Today',
            'Filter by category',
            'All categories',
            'Mon',
            'Tue',
            'Wed',
            'Thu',
            'Fri',
            'Sat',
            'Sun',
        ];

        $months = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December',
        ];

        foreach ($strings as $string) {
            pll_register_string('cec_' . strtolower(str_replace(' ', '_', $string)), $string, 'Church Events Calendar');
        }

        foreach ($months as $month) {
            pll_register_string('cec_month_' . strtolower($month), $month, 'Church Events Calendar');
        }
    }
}

