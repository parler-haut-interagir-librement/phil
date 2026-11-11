<?php

declare(strict_types=1);

namespace Phil\RecurrenceBundle\Tests\Unit\Infrastructure;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Phil\Recurrence\RecurrenceRule;
use Phil\RecurrenceBundle\Application\RuleDescription;
use Phil\RecurrenceBundle\Infrastructure\Twig\RecurrenceExtension;
use Phil\RecurrenceBundle\Tests\Unit\Fixtures\Translations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecurrenceExtension::class)]
final class RecurrenceExtensionTest extends TestCase
{
    /**
     * @param list<DateTimeImmutable> $occurrences
     *
     * @return list<string>
     */
    private function days(array $occurrences): array
    {
        return array_map(static fn (DateTimeImmutable $occurrence): string => $occurrence->format('m-d'), $occurrences);
    }

    public function testARuleIsTextAndOccurrences(): void
    {
        $extension = new RecurrenceExtension(new RuleDescription(Translations::translator()));
        $rule = RecurrenceRule::parse('FREQ=DAILY');
        $start = new DateTimeImmutable('2026-11-02 09:00');

        self::assertSame(['Tous les jours', '', 'Every day'], [$extension->text($rule, 'fr'), $extension->text(null), $extension->text($rule)]);
        self::assertCount(10, $extension->occurrences($start, $rule));
        self::assertSame(['11-04', '11-05'], $this->days($extension->occurrences($start, $rule, 2, new DateTimeImmutable('2026-11-04'))));
        self::assertSame(['11-02'], $this->days($extension->occurrences(new DateTime('2026-11-02 09:00'), null)));
    }

    public function testAnEndTimeIsReadInTheZoneGiven(): void
    {
        $extension = new RecurrenceExtension(new RuleDescription(Translations::translator()));
        $rule = RecurrenceRule::parse('FREQ=WEEKLY;UNTIL=20270101T045959Z');
        $toronto = new DateTimeZone('America/Toronto');

        self::assertSame(
            ['Every week, until 31 December 2026', 'Every week, until 31 December 2026', 'Every week, until 31 December 2026', 'Every week, until 1 January 2027'],
            [$extension->text($rule, 'en', $toronto), $extension->text($rule, 'en', 'America/Toronto'), $extension->text($rule, 'en', new DateTimeImmutable('2026-11-10 19:00', $toronto)), $extension->text($rule, 'en')],
        );
    }

    public function testARuleIsASchedule(): void
    {
        $extension = new RecurrenceExtension(new RuleDescription(Translations::translator()));

        self::assertSame(['@type' => 'Schedule', 'startDate' => '2026-11-10', 'startTime' => '19:30:00', 'repeatFrequency' => 'P1W', 'scheduleTimezone' => 'UTC'], $extension->schema(new DateTimeImmutable('2026-11-10 19:30 UTC'), RecurrenceRule::parse('FREQ=WEEKLY')));
        self::assertNull($extension->schema(new DateTimeImmutable(), null));
    }
}
