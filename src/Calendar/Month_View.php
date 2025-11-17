<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Calendar;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;

/**
 * Transforms events into a structured matrix for month calendars.
 */
final class Month_View
{
    /**
     * @param array<string, array<int, array<string, mixed>>> $events_by_day
     */
    public function render(array $events_by_day, DateTimeImmutable $month): array
    {
        $startOfMonth = $month->modify('first day of this month');
        $endOfMonth = $month->modify('last day of this month');
        $startOfCalendar = $startOfMonth->modify('monday this week');
        $endOfCalendar = $endOfMonth->modify('sunday this week');

        $period = new DatePeriod(
            $startOfCalendar,
            new DateInterval('P1D'),
            $endOfCalendar->modify('+1 day')
        );

        $weeks = [];
        $week = [];

        foreach ($period as $date) {
            $dayKey = $date->format('Y-m-d');
            $week[] = [
                'date' => $date,
                'is_current_month' => $date->format('m') === $month->format('m'),
                'events' => $events_by_day[$dayKey] ?? [],
            ];

            if ($date->format('N') === '7') {
                $weeks[] = $week;
                $week = [];
            }
        }

        if (! empty($week)) {
            $weeks[] = $week;
        }

        return $weeks;
    }
}
