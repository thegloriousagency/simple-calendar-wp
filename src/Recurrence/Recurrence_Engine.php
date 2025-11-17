<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Recurrence;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use Exception;
use function usort;

/**
 * Expands recurrence rules into concrete datetime instances.
 */
final class Recurrence_Engine
{
    /**
     * @return array<int, array{start: DateTimeImmutable, end: DateTimeImmutable}>
     *
     * @throws Exception
     */
    public function get_occurrences(string $range_start, string $range_end, Recurrence_Rule $rule): array
    {
        $rangeStart = new DateTimeImmutable($range_start);
        $rangeEnd = new DateTimeImmutable($range_end);

        if ($rangeEnd < $rangeStart) {
            return [];
        }

        $seriesEnd = $rule->get_series_end();
        if ($seriesEnd !== null && $seriesEnd < $rangeEnd) {
            $rangeEnd = $seriesEnd;
        }

        $occurrences = [];
        $interval = new DateInterval('P' . $rule->get_interval() . 'W');
        $duration = $rule->get_duration();

        foreach ($rule->get_weekdays() as $weekday) {
            $firstOccurrence = $this->first_occurrence_for_weekday($rule->get_start(), $weekday);

            if ($firstOccurrence < $rule->get_start()) {
                $firstOccurrence = $firstOccurrence->add(new DateInterval('P1W'));
            }

            if ($firstOccurrence > $rangeEnd) {
                continue;
            }

            $periodEnd = $rangeEnd->modify('+1 day');
            $period = new DatePeriod($firstOccurrence, $interval, $periodEnd);

            foreach ($period as $occurrenceStart) {
                if ($occurrenceStart < $rangeStart) {
                    continue;
                }

                if ($occurrenceStart > $rangeEnd) {
                    break;
                }

                $occurrenceEnd = $occurrenceStart->add($duration);

                $occurrences[] = [
                    'start' => $occurrenceStart,
                    'end' => $occurrenceEnd,
                ];
            }
        }

        usort(
            $occurrences,
            static fn(array $a, array $b): int => $a['start'] <=> $b['start']
        );

        return $occurrences;
    }

    private function first_occurrence_for_weekday(DateTimeImmutable $start, int $weekday): DateTimeImmutable
    {
        $currentWeekday = (int) $start->format('N');
        $diff = $weekday - $currentWeekday;

        if ($diff === 0) {
            return $start;
        }

        $modifier = ($diff > 0 ? '+' : '') . $diff . ' days';

        return $start->modify($modifier);
    }
}
