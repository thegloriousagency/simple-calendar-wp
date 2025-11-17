<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Calendar;

use DateInterval;
use DateTimeImmutable;
use Glorious\ChurchEvents\Meta\Event_Meta_Repository;
use Glorious\ChurchEvents\Post_Types\Event_Post_Type;
use Glorious\ChurchEvents\Recurrence\Recurrence_Engine;
use Glorious\ChurchEvents\Recurrence\Recurrence_Rule;
use WP_Post;
use WP_Query;
use function current_time;
use function sanitize_text_field;
use function usort;
use function wp_timezone;

/**
 * Performs event queries and expands recurrence data.
 */
final class Calendar_Query
{
    private Event_Meta_Repository $meta_repository;
    private Recurrence_Engine $recurrence_engine;

    public function __construct(
        ?Event_Meta_Repository $meta_repository = null,
        ?Recurrence_Engine $recurrence_engine = null
    ) {
        $this->meta_repository = $meta_repository ?? new Event_Meta_Repository();
        $this->recurrence_engine = $recurrence_engine ?? new Recurrence_Engine();
    }

    /**
     * @param array<string, string|int|null> $args
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function get_month_events(array $args = []): array
    {
        $timezone = wp_timezone();
        $year = isset($args['year']) ? (int) $args['year'] : (int) current_time('Y');
        $month = isset($args['month']) ? (int) $args['month'] : (int) current_time('n');
        $start = new DateTimeImmutable(sprintf('%d-%02d-01 00:00:00', $year, $month), $timezone);
        $end = $start->modify('last day of this month')->setTime(23, 59, 59);

        $query_args = [
            'post_type' => Event_Post_Type::POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => $this->build_overlap_meta_query($start, $end),
        ];

        $tax_query = $this->build_tax_query($args);
        if (! empty($tax_query)) {
            $query_args['tax_query'] = $tax_query;
        }

        $query = new WP_Query($query_args);

        $days = [];

        /** @var WP_Post $post */
        foreach ($query->posts as $post) {
            $events = $this->expand_event($post, $start, $end);

            foreach ($events as $event) {
                $dayKey = $event['start']->format('Y-m-d');
                $days[$dayKey][] = $event;
            }
        }

        foreach ($days as &$events) {
            usort(
                $events,
                static fn(array $a, array $b): int => $a['start'] <=> $b['start']
            );
        }
        unset($events);

        return $days;
    }

    /**
     * @param array<string, string|int|null> $args
     * @return array<int, array{post: WP_Post, start: DateTimeImmutable, end: DateTimeImmutable, meta: array<string, mixed>}>
     */
    public function get_upcoming_events(int $limit = 5, array $args = []): array
    {
        $timezone = wp_timezone();
        $now = new DateTimeImmutable('now', $timezone);
        $range_days = isset($args['range_days']) ? max(1, (int) $args['range_days']) : 60;
        $range_end = $now->add(new DateInterval(sprintf('P%dD', $range_days)));

        $query_args = [
            'post_type' => Event_Post_Type::POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_key' => Event_Meta_Repository::META_START,
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => $this->build_overlap_meta_query($now, $range_end),
        ];

        $tax_query = $this->build_tax_query($args);
        if (! empty($tax_query)) {
            $query_args['tax_query'] = $tax_query;
        }

        $query = new WP_Query($query_args);

        $occurrences = [];

        /** @var WP_Post $post */
        foreach ($query->posts as $post) {
            $events = $this->expand_event($post, $now, $range_end);

            foreach ($events as $event) {
                if ($event['start'] < $now) {
                    continue;
                }

                $occurrences[] = $event;
            }
        }

        usort(
            $occurrences,
            static fn(array $a, array $b): int => $a['start'] <=> $b['start']
        );

        return array_slice($occurrences, 0, max(1, $limit));
    }

    /**
     * Expand a single event into concrete occurrences within the requested range.
     *
     * Events are first filtered via WP_Query; only the resulting posts have their RRULE
     * expanded, ensuring we do not iterate over unrelated events.
     *
     * @return array<int, array{post: WP_Post, start: DateTimeImmutable, end: DateTimeImmutable, meta: array<string, mixed>}>
     */
    private function expand_event(WP_Post $post, DateTimeImmutable $range_start, DateTimeImmutable $range_end): array
    {
        $meta = $this->meta_repository->get_meta($post->ID);

        if (empty($meta[Event_Meta_Repository::META_START]) || empty($meta[Event_Meta_Repository::META_END])) {
            return [];
        }

        $rule = Recurrence_Rule::fromMeta($post->ID, $this->meta_repository);
        $occurrences = $this->recurrence_engine->expand($rule, $range_start, $range_end);

        $events = [];

        foreach ($occurrences as $occurrence) {
            $events[] = [
                'post' => $post,
                'start' => $occurrence['start'],
                'end' => $occurrence['end'] ?? $occurrence['start'],
                'meta' => $meta,
            ];
        }

        return $events;
    }

    /**
     * @param array<string, string|int|null> $args
     * @return array<int, array<string, string>>
     */
    private function build_tax_query(array $args): array
    {
        $tax_query = [];

        if (! empty($args['category'])) {
            $tax_query[] = [
                'taxonomy' => 'church_event_category',
                'field' => 'slug',
                'terms' => sanitize_text_field((string) $args['category']),
            ];
        }

        if (! empty($args['tag'])) {
            $tax_query[] = [
                'taxonomy' => 'church_event_tag',
                'field' => 'slug',
                'terms' => sanitize_text_field((string) $args['tag']),
            ];
        }

        return $tax_query;
    }

    private function build_overlap_meta_query(DateTimeImmutable $range_start, DateTimeImmutable $range_end): array
    {
        return [
            'relation' => 'AND',
            [
                'key' => Event_Meta_Repository::META_START,
                'value' => $range_end->format('Y-m-d H:i:s'),
                'compare' => '<=',
                'type' => 'DATETIME',
            ],
            [
                'key' => Event_Meta_Repository::META_END,
                'value' => $range_start->format('Y-m-d H:i:s'),
                'compare' => '>=',
                'type' => 'DATETIME',
            ],
        ];
    }
}
