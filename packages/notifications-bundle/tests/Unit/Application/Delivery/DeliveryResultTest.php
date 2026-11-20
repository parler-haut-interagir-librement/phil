<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Application\Delivery;

use Phil\NotificationsBundle\Application\Delivery\DeliveryMode;
use Phil\NotificationsBundle\Application\Delivery\DeliveryResult;
use Phil\NotificationsBundle\Application\Delivery\DeliveryStatus;
use Phil\NotificationsBundle\Application\Delivery\NotificationDelivered;
use Phil\NotificationsBundle\Application\Delivery\SkipReason;
use Phil\NotificationsBundle\Application\Exception\DeliveryFailed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeliveryResult::class)]
#[CoversClass(SkipReason::class)]
#[CoversClass(DeliveryFailed::class)]
#[CoversClass(NotificationDelivered::class)]
final class DeliveryResultTest extends TestCase
{
    public function testTheChannelIsTheFirstOutsideTheCentre(): void
    {
        self::assertSame('sms', $this->delivery(DeliveryStatus::Sent, ['site', 'sms', 'email'])->channel());
        self::assertSame('email', $this->delivery(DeliveryStatus::Sent, ['email'])->channel());
        self::assertNull($this->delivery(DeliveryStatus::Sent, ['site'])->channel());
        self::assertNull($this->delivery(DeliveryStatus::Duplicate)->channel());
    }

    public function testOnlyAFailureWithATransientReasonIsRetryable(): void
    {
        self::assertTrue($this->delivery(DeliveryStatus::Failed, [], ['email' => SkipReason::NoContact, 'sms' => SkipReason::Refused])->isRetryable());
        self::assertTrue($this->delivery(DeliveryStatus::Failed, [], ['sms' => SkipReason::CircuitOpen])->isRetryable());
        self::assertFalse($this->delivery(DeliveryStatus::Failed, [], ['email' => SkipReason::NoContact, 'push' => SkipReason::Unavailable])->isRetryable());
        self::assertFalse($this->delivery(DeliveryStatus::Failed)->isRetryable());
        self::assertFalse($this->delivery(DeliveryStatus::Sent, ['site'], ['sms' => SkipReason::Refused])->isRetryable());
        self::assertFalse($this->delivery(DeliveryStatus::RateLimited, ['site'], ['sms' => SkipReason::Refused])->isRetryable());
    }

    public function testOnlyARefusalAndAnOpenCircuitAreTransient(): void
    {
        $transient = array_values(array_filter(SkipReason::cases(), static fn (SkipReason $reason): bool => $reason->isTransient()));

        self::assertSame([SkipReason::CircuitOpen, SkipReason::Refused], $transient);
    }

    public function testTheContextOfTheLogsLeavesTheRecipientOut(): void
    {
        self::assertSame(
            ['type' => 'comment.replied', 'mode' => 'first_success', 'status' => 'sent', 'channel' => 'email', 'channels' => 'site, email', 'skipped' => 'sms (refused), web_push (no_contact)'],
            $this->delivery(DeliveryStatus::Sent, ['site', 'email'], ['sms' => SkipReason::Refused, 'web_push' => SkipReason::NoContact])->context(),
        );
        self::assertSame(
            ['type' => 'comment.replied', 'mode' => 'first_success', 'status' => 'duplicate', 'channel' => 'none', 'channels' => 'none', 'skipped' => 'none'],
            $this->delivery(DeliveryStatus::Duplicate)->context(),
        );
    }

    public function testATotalFailureNamesTheChannelsAndKeepsTheResult(): void
    {
        $result = $this->delivery(DeliveryStatus::Failed, [], ['sms' => SkipReason::Refused]);
        $failure = DeliveryFailed::of($result);

        self::assertSame('The notification "comment.replied" reached none of its channels: sms (refused).', $failure->getMessage());
        self::assertSame($result, $failure->result);
        self::assertSame($result, new NotificationDelivered($result)->result);
    }

    /**
     * @param list<string>              $channels
     * @param array<string, SkipReason> $skipped
     */
    private function delivery(DeliveryStatus $status, array $channels = [], array $skipped = []): DeliveryResult
    {
        return new DeliveryResult('comment.replied', 'ada@example.test', DeliveryMode::FirstSuccess, $status, $channels, $skipped);
    }
}
