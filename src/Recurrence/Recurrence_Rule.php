<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Recurrence;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use function array_unique;
use function in_array;
use function is_int;
use function is_string;
use function strtolower;

/**
 * Value object describing a weekly recurrence rule.
 */
final class Recurrence_Rule
{
    private DateTimeImmutable $start;
    private DateTimeImmutable $end;
    private ?DateTimeImmutable $series_end;
    private int $interval_weeks;
    /** @var int[] */
    private array $weekdays;

    /**
     * @param array<int, int|string> $weekdays
     */
    public function __construct(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        array $weekdays,
        int $interval_weeks = 1,
        ?DateTimeImmutable $series_end = null
    ) {
        if ($end <= $start) {
            throw new InvalidArgumentException('End date must be greater than start date.');
        }

        $this->start = $start;
        $this->end = $end;
        $this->series_end = $series_end;
        $this->interval_weeks = max(1, $interval_weeks);
        $this->weekdays = $this->normalize_weekdays($weekdays);
    }

    public function get_start(): DateTimeImmutable
    {
        return $this->start;
    }

    public function get_end(): DateTimeImmutable
    {
        return $this->end;
    }

    public function get_duration(): DateInterval
    {
        return $this->end->diff($this->start);
    }

    public function get_interval(): int
    {
        return $this->interval_weeks;
    }

    /**
     * @return int[]
     */
    public function get_weekdays(): array
    {
        return $this->weekdays;
    }

    public function get_series_end(): ?DateTimeImmutable
    {
        return $this->series_end;
    }

    /**
     * @param array<int, int|string> $weekdays
     * @return int[]
     */
    private function normalize_weekdays(array $weekdays): array
    {
        $map = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7,
        ];

        $normalized = [];

        foreach ($weekdays as $day) {
            if (is_int($day) && $day >= 1 && $day <= 7) {
                $normalized[] = $day;
                continue;
            }

            if (is_string($day)) {
                $key = strtolower($day);
                if (isset($map[$key])) {
                    $normalized[] = $map[$key];
                }
            }
        }

        if (empty($normalized)) {
            $normalized[] = (int) $this->start->format('N');
        }

        $normalized = array_unique($normalized);

        return array_values($normalized);
    }
}
