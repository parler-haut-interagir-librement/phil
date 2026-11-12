<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Infrastructure\Scheduler;

use DateTimeImmutable;
use DateTimeZone;
use Phil\AnalyticsBundle\Application\Message\ApplyCounterRetention;
use Phil\AnalyticsBundle\Application\Message\CheckBrokenFlows;
use Phil\AnalyticsBundle\Application\Message\CheckTrafficAlerts;
use Phil\AnalyticsBundle\Application\Message\SendDueReports;
use Phil\AnalyticsBundle\Infrastructure\Scheduler\AnalyticsSchedule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;

#[CoversClass(AnalyticsSchedule::class)]
final class AnalyticsScheduleTest extends TestCase
{
    public function testEachEnabledJobRunsOnceADayAtTheConfiguredTime(): void
    {
        $messages = $this->messages(new AnalyticsSchedule('05:30', true, true, true, true));

        self::assertSame([ApplyCounterRetention::class, SendDueReports::class, CheckBrokenFlows::class, CheckTrafficAlerts::class], array_keys($messages));

        foreach ($messages as $trigger) {
            self::assertStringContainsString('every 1 day', $trigger);
        }

        $next = new AnalyticsSchedule('05:30', true, false, false, false)->getSchedule()->getRecurringMessages()[0]->getTrigger()->getNextRunDate(new DateTimeImmutable('2026-11-11 06:00:00 UTC'));

        self::assertNotNull($next);
        self::assertSame('2026-11-12 05:30', $next->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i'));
    }

    public function testADisabledJobIsLeftOut(): void
    {
        self::assertSame([SendDueReports::class], array_keys($this->messages(new AnalyticsSchedule('05:00', false, true, false, false))));
        self::assertSame([], $this->messages(new AnalyticsSchedule('05:00', false, false, false, false)));
        self::assertSame([CheckTrafficAlerts::class], array_keys($this->messages(new AnalyticsSchedule('05:00', false, false, false, true))));
    }

    /**
     * @return array<class-string, string> the trigger of each message, by class
     */
    private function messages(AnalyticsSchedule $schedule): array
    {
        $messages = [];

        foreach ($schedule->getSchedule()->getRecurringMessages() as $recurring) {
            self::assertInstanceOf(RecurringMessage::class, $recurring);
            $context = new MessageContext('t', 'id', $recurring->getTrigger(), new DateTimeImmutable());

            foreach ($recurring->getMessages($context) as $message) {
                $messages[$message::class] = (string) $recurring->getTrigger();
            }
        }

        return $messages;
    }
}
