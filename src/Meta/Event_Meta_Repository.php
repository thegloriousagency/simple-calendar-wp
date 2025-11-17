<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Meta;

use DateTimeImmutable;
use DateTimeZone;
use Glorious\ChurchEvents\Post_Types\Event_Post_Type;
use function array_intersect;
use function array_map;
use function array_values;
use function delete_post_meta;
use function function_exists;
use function get_post_meta;
use function sanitize_meta;
use function sanitize_text_field;
use function strtolower;
use function update_post_meta;
use function wp_timezone;

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
    public const META_RRULE = '_event_rrule';
    public const META_EXDATES = '_event_exdates';
    public const META_RDATES = '_event_rdates';

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
            self::META_RRULE => $this->sanitize_rrule(
                (string) get_post_meta($post_id, self::META_RRULE, true)
            ),
            self::META_EXDATES => $this->get_datetime_array($post_id, self::META_EXDATES),
            self::META_RDATES => $this->get_datetime_array($post_id, self::META_RDATES),
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
        $rrule = isset($data[self::META_RRULE])
            ? $this->sanitize_rrule((string) $data[self::META_RRULE])
            : '';
        $exdates = $this->sanitize_datetime_array($data[self::META_EXDATES] ?? []);
        $rdates = $this->sanitize_datetime_array($data[self::META_RDATES] ?? []);

        $this->persist_meta($post_id, self::META_START, $start);
        $this->persist_meta($post_id, self::META_END, $end);
        $this->persist_meta($post_id, self::META_LOCATION, $location);
        update_post_meta($post_id, self::META_ALL_DAY, $all_day ? '1' : '0');
        update_post_meta($post_id, self::META_IS_RECURRING, $recurring ? '1' : '0');
        update_post_meta($post_id, self::META_RECURRENCE_INTERVAL, $interval);
        update_post_meta($post_id, self::META_RECURRENCE_WEEKDAYS, $weekdays);
        $this->persist_meta($post_id, self::META_RRULE, $rrule);
        $this->persist_meta($post_id, self::META_EXDATES, $this->encode_datetime_array($exdates));
        $this->persist_meta($post_id, self::META_RDATES, $this->encode_datetime_array($rdates));
    }

    public function get_start(int $post_id): string
    {
        return isset($this->get_meta($post_id)[self::META_START])
            ? (string) $this->get_meta($post_id)[self::META_START]
            : '';
    }

    public function save_start(int $post_id, string $value): void
    {
        $this->persist_meta($post_id, self::META_START, $this->normalize_datetime($value));
    }

    public function get_end(int $post_id): string
    {
        return isset($this->get_meta($post_id)[self::META_END])
            ? (string) $this->get_meta($post_id)[self::META_END]
            : '';
    }

    public function save_end(int $post_id, string $value): void
    {
        $this->persist_meta($post_id, self::META_END, $this->normalize_datetime($value));
    }

    public function get_rrule(int $post_id): string
    {
        return $this->sanitize_rrule((string) get_post_meta($post_id, self::META_RRULE, true));
    }

    public function save_rrule(int $post_id, string $rrule): void
    {
        $this->persist_meta($post_id, self::META_RRULE, $this->sanitize_rrule($rrule));
    }

    /**
     * @return array<int, string>
     */
    public function get_exdates(int $post_id): array
    {
        return $this->get_datetime_array($post_id, self::META_EXDATES);
    }

    /**
     * @param array<int, string> $dates
     */
    public function save_exdates(int $post_id, array $dates): void
    {
        $normalized = $this->sanitize_datetime_array($dates);
        $this->persist_meta($post_id, self::META_EXDATES, $this->encode_datetime_array($normalized));
    }

    /**
     * @return array<int, string>
     */
    public function get_rdates(int $post_id): array
    {
        return $this->get_datetime_array($post_id, self::META_RDATES);
    }

    /**
     * @param array<int, string> $dates
     */
    public function save_rdates(int $post_id, array $dates): void
    {
        $normalized = $this->sanitize_datetime_array($dates);
        $this->persist_meta($post_id, self::META_RDATES, $this->encode_datetime_array($normalized));
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

    private function sanitize_rrule(string $rrule): string
    {
        $rrule = trim($rrule);
        $rrule = preg_replace('/^RRULE:/i', '', $rrule ?? '') ?? '';

        return sanitize_text_field($rrule);
    }

    /**
     * @return array<int, string>
     */
    private function get_datetime_array(int $post_id, string $meta_key): array
    {
        $raw = (string) get_post_meta($post_id, $meta_key, true);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        return $this->sanitize_datetime_array($decoded);
    }

    /**
     * @param array<int, string> $values
     * @return array<int, string>
     */
    private function sanitize_datetime_array(array $values): array
    {
        $sanitized = [];

        foreach ($values as $value) {
            $normalized = $this->normalize_datetime((string) $value);
            if ($normalized !== '') {
                $sanitized[] = $normalized;
            }
        }

        return $sanitized;
    }

    private function normalize_datetime(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $timezone = $this->get_timezone();

        try {
            $datetime = new DateTimeImmutable($value, $timezone);

            return $datetime->setTimezone($timezone)->format('Y-m-d H:i:s');
        } catch (\Exception $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
        }

        return '';
    }

    /**
     * @param array<int, string> $dates
     */
    private function encode_datetime_array(array $dates): string
    {
        return $dates === [] ? '' : wp_json_encode(array_values(array_unique($dates)));
    }

    private function get_timezone(): DateTimeZone
    {
        static $timezone;

        if ($timezone instanceof DateTimeZone) {
            return $timezone;
        }

        if (function_exists('wp_timezone')) {
            $candidate = wp_timezone();
            if ($candidate instanceof DateTimeZone) {
                return $timezone = $candidate;
            }
        }

        return $timezone = new DateTimeZone(date_default_timezone_get());
    }
}
