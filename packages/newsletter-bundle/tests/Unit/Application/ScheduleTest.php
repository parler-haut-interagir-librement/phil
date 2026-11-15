<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\NewsletterBundle\Application\Handler\NewsletterSchedule;
use Phil\NewsletterBundle\Application\Message\DispatchDueCampaigns;
use Phil\NewsletterBundle\Application\Message\FetchBounces;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;

#[CoversClass(NewsletterSchedule::class)]
#[CoversClass(FetchBounces::class)]
final class ScheduleTest extends TestCase
{
    public function testCampaignsAreStartedEveryMinuteAndTheMailboxReadWhenThereIsOne(): void
    {
        self::assertSame([['every 1 minute', DispatchDueCampaigns::class]], $this->describe(new NewsletterSchedule()));
        self::assertSame([['every 2 minutes', DispatchDueCampaigns::class], ['every 10 minutes', FetchBounces::class]], $this->describe(new NewsletterSchedule(true, '2 minutes', '10 minutes')));
        self::assertSame(100, new FetchBounces()->limit);
    }

    /**
     * @return list<array{string, class-string}>
     */
    private function describe(NewsletterSchedule $schedule): array
    {
        return array_values(array_map(static function (RecurringMessage $message): array {
            $trigger = $message->getTrigger();
            $generated = iterator_to_array($message->getMessages(new MessageContext('newsletter', 'id', $trigger, new DateTimeImmutable())), false);

            return [(string) $trigger, $generated[0]::class];
        }, $schedule->getSchedule()->getRecurringMessages()));
    }
}
