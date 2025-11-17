<?php

declare(strict_types=1);

use DateTimeImmutable;
use DateTimeZone;
use Glorious\ChurchEvents\Recurrence\Recurrence_Engine;
use Glorious\ChurchEvents\Recurrence\Recurrence_Rule;
use PHPUnit\Framework\TestCase;

final class Recurrence_Engine_Test extends TestCase
{
    public function test_single_occurrence_without_rrule(): void
    {
        $tz = new DateTimeZone('UTC');
        $rule = new Recurrence_Rule(
            new DateTimeImmutable('2024-01-01 09:00:00', $tz),
            new DateTimeImmutable('2024-01-01 10:00:00', $tz),
            '',
            [],
            [],
            false
        );

        $engine = new Recurrence_Engine();
        $rangeStart = new DateTimeImmutable('2023-12-31 00:00:00', $tz);
        $rangeEnd = new DateTimeImmutable('2024-01-31 23:59:59', $tz);

        $occurrences = $engine->expand($rule, $rangeStart, $rangeEnd);

        $this->assertCount(1, $occurrences);
        $this->assertSame('2024-01-01 09:00', $occurrences[0]['start']->format('Y-m-d H:i'));
        $this->assertSame('2024-01-01 10:00', $occurrences[0]['end']?->format('Y-m-d H:i'));
    }

    public function test_rrule_with_exdates_and_rdates(): void
    {
        $tz = new DateTimeZone('UTC');
        $rule = new Recurrence_Rule(
            new DateTimeImmutable('2024-01-01 09:00:00', $tz),
            new DateTimeImmutable('2024-01-01 10:00:00', $tz),
            'FREQ=WEEKLY;COUNT=4',
            [new DateTimeImmutable('2024-01-15 09:00:00', $tz)],
            [new DateTimeImmutable('2024-01-20 09:00:00', $tz)],
            false
        );

        $engine = new Recurrence_Engine();
        $rangeStart = new DateTimeImmutable('2024-01-01 00:00:00', $tz);
        $rangeEnd = new DateTimeImmutable('2024-01-31 23:59:59', $tz);

        $occurrences = $engine->expand($rule, $rangeStart, $rangeEnd);

        $this->assertSame(
            ['2024-01-01', '2024-01-08', '2024-01-20', '2024-01-22'],
            array_map(static fn(array $occurrence): string => $occurrence['start']->format('Y-m-d'), $occurrences)
        );
        $this->assertSame('10:00', $occurrences[0]['end']?->format('H:i'));
    }
}
