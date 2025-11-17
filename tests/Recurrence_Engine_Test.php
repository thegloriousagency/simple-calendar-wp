<?php

declare(strict_types=1);

use DateTimeImmutable;
use Glorious\ChurchEvents\Recurrence\Recurrence_Engine;
use Glorious\ChurchEvents\Recurrence\Recurrence_Rule;
use PHPUnit\Framework\TestCase;

final class Recurrence_Engine_Test extends TestCase
{
    public function test_generates_occurrences_for_multiple_weekdays(): void
    {
        $rule = new Recurrence_Rule(
            new DateTimeImmutable('2024-01-01 09:00:00'),
            new DateTimeImmutable('2024-01-01 10:00:00'),
            ['tuesday', 'thursday'],
            1,
            new DateTimeImmutable('2024-01-31 23:59:59')
        );

        $engine = new Recurrence_Engine();
        $occurrences = $engine->get_occurrences(
            '2024-01-01 00:00:00',
            '2024-01-31 23:59:59',
            $rule
        );

        $this->assertCount(9, $occurrences);
        $this->assertSame('2024-01-02 09:00', $occurrences[0]['start']->format('Y-m-d H:i'));
        $this->assertSame('2024-01-30 09:00', $occurrences[array_key_last($occurrences)]['start']->format('Y-m-d H:i'));

        foreach ($occurrences as $occurrence) {
            $this->assertSame(
                '01:00',
                $occurrence['start']->diff($occurrence['end'])->format('%H:%I')
            );
        }
    }

    public function test_respects_range_window_and_interval(): void
    {
        $rule = new Recurrence_Rule(
            new DateTimeImmutable('2024-01-05 08:00:00'),
            new DateTimeImmutable('2024-01-05 09:00:00'),
            ['friday'],
            2,
            new DateTimeImmutable('2024-03-01 00:00:00')
        );

        $engine = new Recurrence_Engine();
        $occurrences = $engine->get_occurrences(
            '2024-01-15 00:00:00',
            '2024-02-20 23:59:59',
            $rule
        );

        $this->assertCount(3, $occurrences);
        $this->assertSame('2024-01-19 08:00', $occurrences[0]['start']->format('Y-m-d H:i'));
        $this->assertSame('2024-02-16 08:00', $occurrences[array_key_last($occurrences)]['start']->format('Y-m-d H:i'));

        foreach ($occurrences as $occurrence) {
            $this->assertSame(
                '01:00',
                $occurrence['start']->diff($occurrence['end'])->format('%H:%I')
            );
        }
    }
}
