<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Recurrence;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Glorious\ChurchEvents\Meta\Event_Meta_Repository;
use function date_default_timezone_get;
use function function_exists;
use function wp_timezone;

/**
 * Value object describing an RRULE-driven recurrence definition.
 */
final class Recurrence_Rule
{
    /**
     * @param DateTimeImmutable[] $exdates
     * @param DateTimeImmutable[] $rdates
     */
    public function __construct(
        private DateTimeImmutable $start,
        private ?DateTimeImmutable $end,
        private string $rrule,
        private array $exdates = [],
        private array $rdates = [],
        private bool $allDay = false
    ) {
    }

    public static function fromMeta(int $post_id, Event_Meta_Repository $repository): self
    {
        $meta = $repository->get_meta($post_id);
        $timezone = self::resolveTimezone();

        $start = self::createDateTime($meta[Event_Meta_Repository::META_START] ?? '', $timezone) ?? self::fallbackStart($timezone);
        $end = self::createDateTime($meta[Event_Meta_Repository::META_END] ?? '', $timezone);
        $rrule = (string) ($meta[Event_Meta_Repository::META_RRULE] ?? '');
        $exdates = self::createDateCollection($meta[Event_Meta_Repository::META_EXDATES] ?? [], $timezone);
        $rdates = self::createDateCollection($meta[Event_Meta_Repository::META_RDATES] ?? [], $timezone);
        $allDay = ! empty($meta[Event_Meta_Repository::META_ALL_DAY]);

        return new self($start, $end, $rrule, $exdates, $rdates, $allDay);
    }

    public function getStart(): DateTimeImmutable
    {
        return $this->start;
    }

    public function getEnd(): ?DateTimeImmutable
    {
        return $this->end;
    }

    public function getRrule(): string
    {
        return $this->rrule;
    }

    /**
     * @return DateTimeImmutable[]
     */
    public function getExdates(): array
    {
        return $this->exdates;
    }

    /**
     * @return DateTimeImmutable[]
     */
    public function getRdates(): array
    {
        return $this->rdates;
    }

    public function getDuration(): ?DateInterval
    {
        if ($this->end === null) {
            return $this->allDay ? new DateInterval('P1D') : null;
        }

        return $this->end->diff($this->start);
    }

    public function hasRrule(): bool
    {
        return $this->rrule !== '';
    }

    public function isAllDay(): bool
    {
        return $this->allDay;
    }

    private static function createDateTime(?string $value, DateTimeZone $timezone): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value, $timezone);
        } catch (\Exception) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
        }

        return null;
    }

    /**
     * @param array<int, string> $values
     * @return DateTimeImmutable[]
     */
    private static function createDateCollection(array $values, DateTimeZone $timezone): array
    {
        $collection = [];

        foreach ($values as $value) {
            $datetime = self::createDateTime($value, $timezone);
            if ($datetime instanceof DateTimeImmutable) {
                $collection[] = $datetime;
            }
        }

        return $collection;
    }

    private static function fallbackStart(DateTimeZone $timezone): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $timezone);
    }

    private static function resolveTimezone(): DateTimeZone
    {
        if (function_exists('wp_timezone')) {
            $tz = wp_timezone();
            if ($tz instanceof DateTimeZone) {
                return $tz;
            }
        }

        return new DateTimeZone(date_default_timezone_get());
    }
}
