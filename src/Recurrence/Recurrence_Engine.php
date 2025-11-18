<?php

declare(strict_types=1);

namespace Glorious\ChurchEvents\Recurrence;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Glorious\ChurchEvents\Support\Log_Helper;
use Recurr\Rule as RecurrRule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\Constraint\BetweenConstraint;
use Throwable;
use function array_values;
use function usort;
use const DATE_ATOM;

/**
 * Expands recurrence rules into concrete datetime instances.
 */
final class Recurrence_Engine
{
    /**
     * Expand a recurrence rule into concrete instances within a range.
     *
     * @param Recurrence_Rule   $rule       Normalized recurrence rule.
     * @param DateTimeImmutable $rangeStart Inclusive lower bound.
     * @param DateTimeImmutable $rangeEnd   Inclusive upper bound.
     *
     * @return array<int, array{start: DateTimeImmutable, end: ?DateTimeImmutable}>
     */
    public function expand(Recurrence_Rule $rule, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd): array
    {
        if ($rangeEnd < $rangeStart) {
            return [];
        }

        // Normalize all calculations to the event's timezone so Recurr and our custom logic
        // operate against a single, predictable offset.
        $timezone = $rule->getStart()->getTimezone();
        $rangeStart = $this->alignToTimezone($rangeStart, $timezone);
        $rangeEnd = $this->alignToTimezone($rangeEnd, $timezone);

        $duration = $rule->getDuration();
        $occurrenceMap = $rule->hasRrule()
            ? $this->generateOccurrences($rule, $rangeStart, $rangeEnd, $duration)
            : [];

        if ($occurrenceMap === []) {
            $occurrenceMap = $this->maybeAddSingleOccurrence($rule, $rangeStart, $rangeEnd, $duration);
        }

        $occurrenceMap = $this->mergeRdates($occurrenceMap, $rule->getRdates(), $rangeStart, $rangeEnd, $duration, $timezone);
        $occurrenceMap = $this->applyExdates($occurrenceMap, $rule->getExdates(), $timezone);

        $occurrences = array_values($occurrenceMap);

        usort(
            $occurrences,
            static fn(array $a, array $b): int => $a['start'] <=> $b['start']
        );

        return $occurrences;
    }

    /**
     * @return array<string, array{start: DateTimeImmutable, end: ?DateTimeImmutable}>
     */
    private function generateOccurrences(
        Recurrence_Rule $rule,
        DateTimeImmutable $rangeStart,
        DateTimeImmutable $rangeEnd,
        ?DateInterval $duration
    ): array {
        $map = [];

        try {
            $recurrRule = new RecurrRule(
                $rule->getRrule(),
                $rule->getStart(),
                $rule->getEnd(),
                $rule->getStart()->getTimezone()->getName()
            );

            $transformer = new ArrayTransformer();
            $constraint = new BetweenConstraint($rangeStart, $rangeEnd, true);
            $collection = $transformer->transform($recurrRule, $constraint);

            foreach ($collection as $recurrence) {
                $start = $this->toImmutable($recurrence->getStart());

                if (! $this->isWithinRange($start, $rangeStart, $rangeEnd)) {
                    continue;
                }

                $end = null;

                if ($rule->getEnd() !== null) {
                    $endDate = $recurrence->getEnd();
                    if ($endDate instanceof \DateTimeInterface) {
                        $end = $this->toImmutable($endDate);
                    } elseif ($duration !== null) {
                        $end = $start->add($duration);
                    }
                } elseif ($duration !== null) {
                    $end = $start->add($duration);
                }

                $map[$this->formatKey($start)] = [
                    'start' => $start,
                    'end' => $end,
                ];
            }
        } catch (Throwable $exception) {
            Log_Helper::log(
                'error',
                'Recurrence expansion failed.',
                [
                    'rrule' => $rule->getRrule(),
                    'range_start' => $rangeStart->format(DATE_ATOM),
                    'range_end' => $rangeEnd->format(DATE_ATOM),
                    'message' => $exception->getMessage(),
                ]
            );

            return $this->maybeAddSingleOccurrence($rule, $rangeStart, $rangeEnd, $duration);
        }

        return $map;
    }

    /**
     * @param array<string, array{start: DateTimeImmutable, end: ?DateTimeImmutable}> $occurrences
     * @param DateTimeImmutable[] $rdates
     * @return array<string, array{start: DateTimeImmutable, end: ?DateTimeImmutable}>
     */
    private function mergeRdates(
        array $occurrences,
        array $rdates,
        DateTimeImmutable $rangeStart,
        DateTimeImmutable $rangeEnd,
        ?DateInterval $duration,
        DateTimeZone $timezone
    ): array {
        foreach ($rdates as $rdate) {
            $rdate = $this->alignToTimezone($rdate, $timezone);

            if (! $this->isWithinRange($rdate, $rangeStart, $rangeEnd)) {
                continue;
            }

            $key = $this->formatKey($rdate);

            if (! isset($occurrences[$key])) {
                $occurrences[$key] = [
                    'start' => $rdate,
                    'end' => $duration ? $rdate->add($duration) : null,
                ];
            }
        }

        return $occurrences;
    }

    /**
     * @param array<string, array{start: DateTimeImmutable, end: ?DateTimeImmutable}> $occurrences
     * @param DateTimeImmutable[] $exdates
     * @return array<string, array{start: DateTimeImmutable, end: ?DateTimeImmutable}>
     */
    private function applyExdates(array $occurrences, array $exdates, DateTimeZone $timezone): array
    {
        foreach ($exdates as $exdate) {
            $key = $this->formatKey($this->alignToTimezone($exdate, $timezone));
            unset($occurrences[$key]);
        }

        return $occurrences;
    }

    /**
     * @return array<string, array{start: DateTimeImmutable, end: ?DateTimeImmutable}>
     */
    private function maybeAddSingleOccurrence(
        Recurrence_Rule $rule,
        DateTimeImmutable $rangeStart,
        DateTimeImmutable $rangeEnd,
        ?DateInterval $duration
    ): array {
        $start = $rule->getStart();

        if (! $this->isWithinRange($start, $rangeStart, $rangeEnd)) {
            return [];
        }

        $key = $this->formatKey($start);

        return [
            $key => [
                'start' => $start,
                'end' => $duration ? $start->add($duration) : null,
            ],
        ];
    }

    private function isWithinRange(DateTimeImmutable $date, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd): bool
    {
        return $date >= $rangeStart && $date <= $rangeEnd;
    }

    private function toImmutable(\DateTimeInterface $date): DateTimeImmutable
    {
        return $date instanceof DateTimeImmutable
            ? $date
            : DateTimeImmutable::createFromInterface($date);
    }

    private function alignToTimezone(DateTimeImmutable $date, \DateTimeZone $timezone): DateTimeImmutable
    {
        return $date->setTimezone($timezone);
    }

    private function formatKey(DateTimeImmutable $date): string
    {
        // Canonical key used for EXDATE/RDATE comparisons. Includes offset to avoid DST ambiguity.
        return $date->format('Y-m-d H:i:sP');
    }
}
