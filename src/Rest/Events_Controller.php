<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Rest;

use DateTimeImmutable;
use Glorious\ChurchEvents\Meta\Event_Meta_Repository;
use Glorious\ChurchEvents\Post_Types\Event_Post_Type;
use Glorious\ChurchEvents\Recurrence\Recurrence_Engine;
use Glorious\ChurchEvents\Recurrence\Recurrence_Rule;
use Glorious\ChurchEvents\Support\Cache_Helper;
use Glorious\ChurchEvents\Support\Log_Helper;
use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Server;
use function __;
use function determine_locale;
use function add_action;
use function array_filter;
use function array_map;
use function array_slice;
use function array_values;
use function get_permalink;
use function get_the_title;
use function get_locale;
use function get_the_terms;
use function is_numeric;
use function is_wp_error;
use function function_exists;
use function pll_current_language;
use function pll_get_post_language;
use function pll_get_post;
use function rest_ensure_response;
use function sanitize_text_field;
use function strcmp;
use function usort;
use function wp_timezone;
use function wp_unslash;
use const DATE_ATOM;

/**
 * REST controller returning expanded event occurrences as JSON.
 *
 * The endpoint exposes a read-only API for external consumers to query
 * fully expanded occurrences (RRULE + EXDATE/RDATE aware) within a date range.
 */
final class Events_Controller
{
    private const MAX_RANGE_DAYS = 366;
    private const MAX_LIMIT = 500;

    private Event_Meta_Repository $repository;
    private Recurrence_Engine $engine;

    public function __construct(Event_Meta_Repository $repository, Recurrence_Engine $engine)
    {
        $this->repository = $repository;
        $this->engine = $engine;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route(
            'church-events/v1',
            '/events',
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'handle_events'],
                'permission_callback' => '__return_true',
                'args' => [
                    'start' => [
                        'type' => 'string',
                        'required' => true,
                    ],
                    'end' => [
                        'type' => 'string',
                        'required' => true,
                    ],
                    'category' => [
                        'type' => 'string',
                        'required' => false,
                    ],
                    'tag' => [
                        'type' => 'string',
                        'required' => false,
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'required' => false,
                    ],
                    'lang' => [
                        'type' => 'string',
                        'required' => false,
                    ],
                ],
            ]
        );
    }

    public function handle_events(WP_REST_Request $request)
    {
        $range = $this->parse_range($request);
        if (is_wp_error($range)) {
            return rest_ensure_response($range);
        }

        [$rangeStart, $rangeEnd] = $range;

        $limit = $this->sanitize_limit($request->get_param('limit'));
        $category = $this->sanitize_taxonomy_param($request->get_param('category'));
        $tag = $this->sanitize_taxonomy_param($request->get_param('tag'));
        $language = $this->determine_language($this->sanitize_language($request->get_param('lang')));

        $locale = $this->get_locale_code();
        $cache_key = Cache_Helper::build_events_cache_key(
            $rangeStart->format(DATE_ATOM),
            $rangeEnd->format(DATE_ATOM),
            $this->build_taxonomy_fragment($category),
            $this->build_taxonomy_fragment($tag),
            $limit !== null ? (string) $limit : 'none',
            $locale,
            $language
        );

        $cached = Cache_Helper::get_cached($cache_key);
        if ($cached !== null) {
            return rest_ensure_response($cached);
        }

        $posts = $this->filter_posts_by_language(
            $this->query_events($rangeStart, $rangeEnd, $category, $tag),
            $language
        );

        $occurrences = [];

        foreach ($posts as $post) {
            $rule = Recurrence_Rule::fromMeta((int) $post->ID, $this->repository);
            $expanded = $this->engine->expand($rule, $rangeStart, $rangeEnd);
            $meta = $this->repository->get_meta((int) $post->ID);

            foreach ($expanded as $occurrence) {
                $occurrences[] = [
                    'event_id' => (int) $post->ID,
                    'title' => get_the_title($post),
                    'permalink' => $this->resolve_permalink($post, $language),
                    'start' => $occurrence['start']->format(DATE_ATOM),
                    'end' => isset($occurrence['end']) ? $occurrence['end']->format(DATE_ATOM) : null,
                    'all_day' => (bool) ($meta[Event_Meta_Repository::META_ALL_DAY] ?? false),
                    'location' => (string) ($meta[Event_Meta_Repository::META_LOCATION] ?? ''),
                    'location_mode' => (string) ($meta[Event_Meta_Repository::META_LOCATION_MODE] ?? 'custom'),
                    'location_id' => (int) ($meta[Event_Meta_Repository::META_LOCATION_ID] ?? 0) ?: null,
                    'categories' => $this->format_terms(get_the_terms($post, 'church_event_category')),
                    'tags' => $this->format_terms(get_the_terms($post, 'church_event_tag')),
                ];
            }
        }

        usort(
            $occurrences,
            static fn(array $a, array $b): int => strcmp($a['start'], $b['start'])
        );

        if ($limit !== null) {
            $occurrences = array_slice($occurrences, 0, $limit);
        }

        $payload = [
            'start' => $rangeStart->format(DATE_ATOM),
            'end' => $rangeEnd->format(DATE_ATOM),
            'count' => count($occurrences),
            'occurrences' => $occurrences,
        ];

        Cache_Helper::set_cached($cache_key, $payload, Cache_Helper::get_events_cache_ttl());

        return rest_ensure_response($payload);
    }

    /**
     * Performs an initial WP_Query to limit the events before RRULE expansion.
     *
     * @return array<int, \WP_Post>
     */
    private function query_events(DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd, ?array $category, ?array $tag): array
    {
        $args = [
            'post_type' => Event_Post_Type::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            // Basic overlap meta query ensures we only pull events that could
            // intersect the requested window before expanding RRULEs.
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => Event_Meta_Repository::META_END,
                    'value' => $rangeStart->format('Y-m-d H:i:s'),
                    'compare' => '>=',
                    'type' => 'DATETIME',
                ],
                [
                    'key' => Event_Meta_Repository::META_START,
                    'value' => $rangeEnd->format('Y-m-d H:i:s'),
                    'compare' => '<=',
                    'type' => 'DATETIME',
                ],
            ],
        ];

        $tax_query = [];

        if ($category) {
            $tax_query[] = [
                'taxonomy' => 'church_event_category',
                'field' => $category['field'],
                'terms' => $category['value'],
            ];
        }

        if ($tag) {
            $tax_query[] = [
                'taxonomy' => 'church_event_tag',
                'field' => $tag['field'],
                'terms' => $tag['value'],
            ];
        }

        if ($tax_query) {
            $args['tax_query'] = $tax_query;
        }

        $query = new WP_Query($args);

        return $query->posts;
    }

    /**
     * @param array<int, \WP_Post> $posts
     * @return array<int, \WP_Post>
     */
    private function filter_posts_by_language(array $posts, ?string $language = null): array
    {
        if (! function_exists('pll_get_post_language')) {
            return $posts;
        }

        if ($language === 'default' || $language === 'none') {
            $language = null;
        }

        $current_language = $language;
        if (! $current_language && function_exists('pll_current_language')) {
            $current_language = pll_current_language('slug');
        }

        if (! $current_language) {
            return $posts;
        }

        $filtered = array_filter(
            $posts,
            static function ($post) use ($current_language): bool {
                $language = pll_get_post_language($post->ID, 'slug');

                if ($language === null || $language === '') {
                    return true;
                }

                return $language === $current_language;
            }
        );

        return array_values($filtered);
    }

    /**
     * @return array<int, array{id:int,name:string,slug:string}>
     */
    private function format_terms($terms): array
    {
        if (! $terms || is_wp_error($terms)) {
            return [];
        }

        return array_map(
            static fn($term): array => [
                'id' => (int) $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            ],
            $terms
        );
    }

    private function sanitize_taxonomy_param($value): ?array
    {
        if ($value === null || $value === '' || $value === 'all') {
            return null;
        }

        $sanitized = sanitize_text_field(wp_unslash((string) $value));

        if (is_numeric($sanitized)) {
            return [
                'field' => 'term_id',
                'value' => (int) $sanitized,
            ];
        }

        return [
            'field' => 'slug',
            'value' => $sanitized,
        ];
    }

    private function sanitize_limit($limit): ?int
    {
        if ($limit === null || $limit === '') {
            return null;
        }

        $limit = max(1, (int) $limit);

        return min(self::MAX_LIMIT, $limit);
    }

    /**
     * Validates and normalizes the requested start / end range.
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}|WP_Error
     */
    private function parse_range(WP_REST_Request $request)
    {
        $timezone = wp_timezone();

        $startParam = $request->get_param('start');
        $endParam = $request->get_param('end');

        if (! $startParam || ! $endParam) {
            $this->log_invalid_request('cec_missing_range', ['start' => $startParam, 'end' => $endParam]);
            return new WP_Error(
                'cec_missing_range',
                __('start and end parameters are required.', 'church-events-calendar'),
                ['status' => 400]
            );
        }

        try {
            $start = new DateTimeImmutable((string) $startParam, $timezone);
            $end = new DateTimeImmutable((string) $endParam, $timezone);
        } catch (\Exception $e) {
            $this->log_invalid_request('cec_invalid_range', ['start' => $startParam, 'end' => $endParam, 'error' => $e->getMessage()]);
            return new WP_Error(
                'cec_invalid_range',
                __('Invalid date format. Provide ISO8601 or Y-m-d.', 'church-events-calendar'),
                ['status' => 400]
            );
        }

        if ($end < $start) {
            $this->log_invalid_request('cec_invalid_range', ['reason' => 'end_before_start', 'start' => $startParam, 'end' => $endParam]);
            return new WP_Error(
                'cec_invalid_range',
                __('End date must be after start date.', 'church-events-calendar'),
                ['status' => 400]
            );
        }

        if ($start->diff($end)->days > self::MAX_RANGE_DAYS) {
            $this->log_invalid_request('cec_range_too_large', ['start' => $startParam, 'end' => $endParam]);
            return new WP_Error(
                'cec_range_too_large',
                __('Requested range is too large. Please query 12 months or less.', 'church-events-calendar'),
                ['status' => 400]
            );
        }

        return [$start, $end];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log_invalid_request(string $code, array $context): void
    {
        Log_Helper::log(
            'warning',
            'Events API request rejected: ' . $code,
            $context
        );
    }

    private function build_taxonomy_fragment(?array $tax): string
    {
        if ($tax === null) {
            return 'none';
        }

        return sprintf('%s:%s', $tax['field'], (string) $tax['value']);
    }

    private function get_locale_code(): string
    {
        if (function_exists('determine_locale')) {
            $locale = determine_locale();
            if ($locale) {
                return $locale;
            }
        }

        $locale = get_locale();

        return $locale ?: 'default';
    }

    private function sanitize_language($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $language = sanitize_text_field(wp_unslash((string) $value));

        if ($language === '' || $language === 'all' || $language === 'default' || $language === 'none') {
            return null;
        }

        return $language;
    }

    private function determine_language(?string $language): ?string
    {
        if ($language !== null && $language !== '') {
            return $language;
        }

        if (function_exists('pll_current_language')) {
            $current = (string) pll_current_language('slug');
            if ($current !== '') {
                return $current;
            }
        }

        return null;
    }

    private function resolve_permalink(\WP_Post $post, ?string $language): string
    {
        if (function_exists('pll_get_post')) {
            $target_language = $language;
            if (! $target_language && function_exists('pll_current_language')) {
                $target_language = pll_current_language('slug');
            }

            if ($target_language) {
                $translated_id = pll_get_post($post->ID, $target_language);
                if ($translated_id) {
                    $link = get_permalink($translated_id);
                    if ($link) {
                        return $link;
                    }
                }
            }
        }

        return get_permalink($post);
    }
}

