<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Meta;

use Glorious\ChurchEvents\Post_Types\Event_Post_Type;
use function array_intersect;
use function array_map;
use function array_values;
use function delete_post_meta;
use function get_post_meta;
use function sanitize_meta;
use function sanitize_text_field;
use function strtolower;
use function update_post_meta;

/**
 * Provides typed access to event meta keys.
 */
final class Event_Meta_Repository
{
    public const META_START = '_event_start';
    public const META_END = '_event_end';
    public const META_ALL_DAY = '_event_all_day';
    public const META_LOCATION = '_event_location';
    public const META_IS_RECURRING = '_event_is_recurring';
    public const META_RECURRENCE_WEEKDAYS = '_event_recurrence_weekdays';
    public const META_RECURRENCE_INTERVAL = '_event_recurrence_interval';

    /**
     * @return array<string, mixed>
     */
    public function get_meta(int $post_id): array
    {
        return [
            self::META_START => sanitize_text_field((string) get_post_meta($post_id, self::META_START, true)),
            self::META_END => sanitize_text_field((string) get_post_meta($post_id, self::META_END, true)),
            self::META_ALL_DAY => (bool) get_post_meta($post_id, self::META_ALL_DAY, true),
            self::META_LOCATION => sanitize_text_field((string) get_post_meta($post_id, self::META_LOCATION, true)),
            self::META_IS_RECURRING => (bool) get_post_meta($post_id, self::META_IS_RECURRING, true),
            self::META_RECURRENCE_WEEKDAYS => $this->sanitize_weekdays(
                (array) get_post_meta($post_id, self::META_RECURRENCE_WEEKDAYS, true)
            ),
            self::META_RECURRENCE_INTERVAL => max(
                1,
                (int) get_post_meta($post_id, self::META_RECURRENCE_INTERVAL, true)
            ),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save_meta(int $post_id, array $data): void
    {
        $start = isset($data[self::META_START])
            ? sanitize_meta(self::META_START, (string) $data[self::META_START], Event_Post_Type::POST_TYPE)
            : '';
        $end = isset($data[self::META_END])
            ? sanitize_meta(self::META_END, (string) $data[self::META_END], Event_Post_Type::POST_TYPE)
            : '';
        $location = isset($data[self::META_LOCATION])
            ? sanitize_text_field((string) $data[self::META_LOCATION])
            : '';
        $all_day = ! empty($data[self::META_ALL_DAY]);
        $recurring = ! empty($data[self::META_IS_RECURRING]);
        $interval = isset($data[self::META_RECURRENCE_INTERVAL])
            ? max(1, (int) $data[self::META_RECURRENCE_INTERVAL])
            : 1;
        $weekdays = $this->sanitize_weekdays(
            $data[self::META_RECURRENCE_WEEKDAYS] ?? []
        );

        $this->persist_meta($post_id, self::META_START, $start);
        $this->persist_meta($post_id, self::META_END, $end);
        $this->persist_meta($post_id, self::META_LOCATION, $location);
        update_post_meta($post_id, self::META_ALL_DAY, $all_day ? '1' : '0');
        update_post_meta($post_id, self::META_IS_RECURRING, $recurring ? '1' : '0');
        update_post_meta($post_id, self::META_RECURRENCE_INTERVAL, $interval);
        update_post_meta($post_id, self::META_RECURRENCE_WEEKDAYS, $weekdays);
    }

    private function persist_meta(int $post_id, string $key, string $value): void
    {
        if ($value !== '') {
            update_post_meta($post_id, $key, $value);
            return;
        }

        delete_post_meta($post_id, $key);
    }

    /**
     * @param array<int, string> $weekdays
     * @return array<int, string>
     */
    private function sanitize_weekdays(array $weekdays): array
    {
        $allowed = [
            'monday',
            'tuesday',
            'wednesday',
            'thursday',
            'friday',
            'saturday',
            'sunday',
        ];

        $sanitized = array_map(
            static fn(string $day): string => sanitize_text_field(strtolower($day)),
            $weekdays
        );

        return array_values(array_intersect($sanitized, $allowed));
    }
}
