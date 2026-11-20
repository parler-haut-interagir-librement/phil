<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Application;

use LogicException;
use Phil\CircuitBreaker\CircuitBreaker;
use Phil\CircuitBreaker\CircuitBreakers;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\CircuitOverrides;
use Phil\CircuitBreaker\CircuitPolicy;
use Phil\CircuitBreaker\CircuitState;
use Phil\Idempotency\IdempotencyGuard;
use Phil\Idempotency\Store\CacheIdempotencyStore;
use Phil\NotificationsBundle\Application\Delivery\ChannelOutcome;
use Phil\NotificationsBundle\Application\Delivery\DeliveryMode;
use Phil\NotificationsBundle\Application\Delivery\DeliveryPolicy;
use Phil\NotificationsBundle\Application\Delivery\DeliveryResult;
use Phil\NotificationsBundle\Application\Delivery\DeliveryStatus;
use Phil\NotificationsBundle\Application\Delivery\NotificationDelivered;
use Phil\NotificationsBundle\Application\Delivery\RecipientRateLimit;
use Phil\NotificationsBundle\Application\Delivery\SkipReason;
use Phil\NotificationsBundle\Application\Exception\DeliveryFailed;
use Phil\NotificationsBundle\Application\Message\SendNotification;
use Phil\NotificationsBundle\Application\NotificationDispatcher;
use Phil\NotificationsBundle\Application\NotificationRenderer;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\NotificationsBundle\Application\Preferences\DigestFrequency;
use Phil\NotificationsBundle\Application\Preferences\QuietHours;
use Phil\NotificationsBundle\Application\Preferences\RecipientSettings;
use Phil\NotificationsBundle\Infrastructure\CircuitBreaker\ChannelBreakers;
use Phil\NotificationsBundle\Infrastructure\Idempotency\NotificationDedupe;
use Phil\NotificationsBundle\Infrastructure\Memory\InMemoryNotificationRepository;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Directory;
use Phil\NotificationsBundle\Tests\Unit\Fixture\InMemoryCircuits;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Logs;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Preferences;
use Phil\NotificationsBundle\Tests\Unit\Fixture\RecordingEvents;
use Phil\NotificationsBundle\Tests\Unit\Fixture\RecordingNotifier;
use Phil\NotificationsBundle\Tests\Unit\Fixture\RecordingToasts;
use Phil\NotificationsBundle\Tests\Unit\Fixture\RecordingWebPush;
use Phil\NotificationsBundle\Tests\Unit\Fixture\ScriptedChannels;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Subjects;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Notifier\Recipient\SmsRecipientInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * The `first_success` mode and what every delivery checks, against fake transports that accept, refuse or throw on
 * command, a mock clock, an in-memory cache for the window of duplicates and an in-memory limiter.
 */
#[CoversClass(NotificationDispatcher::class)]
final class FallbackTest extends TestCase
{
    private InMemoryNotificationRepository $repository;

    private Preferences $preferences;

    private RecordingToasts $toasts;

    private RecordingNotifier $notifier;

    private RecordingWebPush $webPush;

    private ScriptedChannels $channels;

    private Logs $logs;

    private RecordingEvents $events;

    private MockClock $clock;

    private Directory $directory;

    private InMemoryCircuits $circuits;

    protected function setUp(): void
    {
        $this->repository = new InMemoryNotificationRepository();
        $this->preferences = new Preferences();
        $this->toasts = new RecordingToasts();
        $this->notifier = new RecordingNotifier();
        $this->webPush = new RecordingWebPush();
        $this->channels = new ScriptedChannels(['sms' => ChannelOutcome::Accepted, 'email' => ChannelOutcome::Accepted]);
        $this->logs = new Logs();
        $this->events = new RecordingEvents();
        $this->clock = new MockClock('2026-11-08 12:00:00 UTC');
        $this->directory = new Directory(phones: ['42' => '+15550000042']);
        $this->circuits = new InMemoryCircuits();
    }

    public function testTheFirstChannelThatAcceptsEndsTheDelivery(): void
    {
        $result = $this->dispatcher($this->type())->deliver($this->message(), '42');

        self::assertSame(DeliveryStatus::Sent, $result->status);
        self::assertSame(DeliveryMode::FirstSuccess, $result->mode);
        self::assertSame(['site', 'sms'], $result->channels);
        self::assertSame('sms', $result->channel());
        self::assertSame([], $result->skipped);
        self::assertSame(['sms'], $this->channels->calls);
        self::assertSame([], $this->webPush->sent);
        self::assertSame([], $this->notifier->sent);
        $contact = $this->channels->sent[0][2];
        self::assertInstanceOf(SmsRecipientInterface::class, $contact);
        self::assertSame('+15550000042', $contact->getPhone());
        self::assertSame('Grace replied to comment 9', $this->channels->sent[0][1]->title);
        self::assertSame(1, $this->repository->unreadCount('42'));
        self::assertCount(1, $this->toasts->published);
    }

    public function testARefusalMakesWayForTheNextChannel(): void
    {
        $this->channels->answers['sms'] = ChannelOutcome::Refused;
        $result = $this->dispatcher($this->type())->deliver($this->message(), '42');

        self::assertSame(['site', 'web_push'], $result->channels);
        self::assertSame(['sms' => SkipReason::Refused], $result->skipped);
        self::assertSame(['sms'], $this->channels->calls);
        self::assertCount(1, $this->webPush->sent);
        self::assertSame('42', $this->webPush->sent[0][0]);
    }

    public function testTheLastChannelIsTriedWhenTheOthersRefuse(): void
    {
        $this->channels->answers['sms'] = ChannelOutcome::Refused;
        $this->webPush->outcome = ChannelOutcome::Refused;
        $result = $this->dispatcher($this->type())->deliver($this->message(), '42');

        self::assertSame(['site', 'email'], $result->channels);
        self::assertSame('email', $result->channel());
        self::assertSame(['sms' => SkipReason::Refused, 'web_push' => SkipReason::Refused], $result->skipped);
        self::assertSame(['sms', 'email'], $this->channels->calls);
    }

    public function testAChannelWithoutContactIsSkippedWithoutBeingCalled(): void
    {
        $this->directory = new Directory(['42' => 'ada@example.test']);
        $this->webPush->outcome = ChannelOutcome::Unreachable;
        $result = $this->dispatcher($this->type())->deliver($this->message(), '42');

        self::assertSame(['sms' => SkipReason::NoContact, 'web_push' => SkipReason::NoContact], $result->skipped);
        self::assertSame(['email'], $this->channels->calls);
        self::assertSame('email', $result->channel());
    }

    public function testARecipientTheDirectoryDoesNotKnowIsLookedUpOnce(): void
    {
        $this->directory = new Directory([]);
        $this->webPush->outcome = ChannelOutcome::Unreachable;
        $result = $this->dispatcher($this->type())->deliver($this->message(), '42');

        self::assertSame(DeliveryStatus::Failed, $result->status);
        self::assertSame(['site'], $result->channels);
        self::assertNull($result->channel());
        self::assertSame(['sms' => SkipReason::NoContact, 'web_push' => SkipReason::NoContact, 'email' => SkipReason::NoContact], $result->skipped);
        self::assertFalse($result->isRetryable());
        self::assertSame([], $this->channels->calls);
        self::assertSame(['warning: The notification "comment.replied" reached no channel (first_success); skipped: sms (no_contact), web_push (no_contact), email (no_contact).'], $this->logs->records);
    }

    public function testAChannelWithoutSenderIsUnavailable(): void
    {
        $this->channels = new ScriptedChannels(['email' => ChannelOutcome::Accepted]);
        $result = $this->dispatcher($this->type(), webPush: false)->deliver($this->message(), '42');

        self::assertSame(['sms' => SkipReason::Unavailable, 'web_push' => SkipReason::Unavailable], $result->skipped);
        self::assertSame('email', $result->channel());
    }

    public function testAnOpenCircuitSkipsItsChannelUntilItsProbe(): void
    {
        $this->channels->answers['sms'] = ChannelOutcome::Refused;
        $breakers = $this->breakers('notifications.sms');
        $dispatcher = $this->dispatcher($this->type(dedupeTtl: 0), breakers: $breakers);

        $dispatcher->deliver($this->message(), '42');
        $dispatcher->deliver($this->message(), '42');

        $open = $dispatcher->deliver($this->message(), '42');

        self::assertSame(CircuitState::Open, $breakers->get(CircuitKey::of('notifications.sms'))->state());
        self::assertSame(['sms', 'sms'], $this->channels->calls);
        self::assertSame(SkipReason::CircuitOpen, $open->skipped['sms']);
        self::assertSame('web_push', $open->channel());

        $this->clock->sleep(31);
        $this->channels->answers['sms'] = ChannelOutcome::Accepted;
        $probe = $dispatcher->deliver($this->message(), '42');

        self::assertSame('sms', $probe->channel());
        self::assertSame(['sms', 'sms', 'sms'], $this->channels->calls);
        self::assertSame(CircuitState::Closed, $breakers->get(CircuitKey::of('notifications.sms'))->state());
    }

    public function testTheCircuitOfATransportGuardsItsChannel(): void
    {
        $this->channels = new ScriptedChannels(['chat/slack' => ChannelOutcome::Refused, 'email' => ChannelOutcome::Accepted]);
        $breakers = $this->breakers('notifications.chat.slack', 'notifications.email');
        $dispatcher = $this->dispatcher($this->type(['chat/slack', 'email'], dedupeTtl: 0), breakers: $breakers);

        $dispatcher->deliver($this->message(), '42');
        $dispatcher->deliver($this->message(), '42');

        self::assertSame(CircuitState::Open, $breakers->get(CircuitKey::of('notifications.chat.slack'))->state());
        self::assertSame(SkipReason::CircuitOpen, $dispatcher->deliver($this->message(), '42')->skipped['chat/slack']);
        self::assertSame(CircuitState::Closed, $breakers->get(CircuitKey::of('notifications.email'))->state());
    }

    public function testAnUnreachableRecipientSaysNothingOfTheProvider(): void
    {
        $this->webPush->outcome = ChannelOutcome::Unreachable;
        $this->channels = new ScriptedChannels(['email' => ChannelOutcome::Accepted]);
        $breakers = $this->breakers('notifications.web_push');
        $dispatcher = $this->dispatcher($this->type(['web_push', 'email'], dedupeTtl: 0), breakers: $breakers);

        foreach ([1, 2, 3] as $attempt) {
            self::assertSame(SkipReason::NoContact, $dispatcher->deliver($this->message(), '42')->skipped['web_push'], (string) $attempt);
        }

        self::assertSame(CircuitState::Closed, $breakers->get(CircuitKey::of('notifications.web_push'))->state());
    }

    public function testTheSameNotificationIsSentOncePerWindow(): void
    {
        $dispatcher = $this->dispatcher($this->type(), dedupe: true);

        $first = $dispatcher->deliver($this->message(), '42');
        $again = $dispatcher->deliver($this->message(), '42');
        $other = $dispatcher->deliver($this->message(subjectId: '10'), '42');

        self::assertSame(DeliveryStatus::Sent, $first->status);
        self::assertSame(DeliveryStatus::Duplicate, $again->status);
        self::assertSame([], $again->channels);
        self::assertSame(DeliveryStatus::Sent, $other->status);
        self::assertSame(['sms', 'sms'], $this->channels->calls);
        self::assertSame(2, $this->repository->unreadCount('42'));
        self::assertContains('info: The notification "comment.replied" ended duplicate (first_success) on none; skipped: none.', $this->logs->records);

        $this->clock->sleep(301);

        self::assertSame(DeliveryStatus::Sent, $dispatcher->deliver($this->message(), '42')->status);
        self::assertSame(['sms', 'sms', 'sms'], $this->channels->calls);
    }

    public function testTheKeyOfTheMessageMakesTwoNotificationsTheSame(): void
    {
        $dispatcher = $this->dispatcher($this->type(), dedupe: true);

        $dispatcher->deliver($this->message(dedupeKey: 'reminder.7'), '42');

        $other = $dispatcher->deliver($this->message(subjectId: '10', dedupeKey: 'reminder.7'), '42');
        $elsewhere = $dispatcher->deliver($this->message(dedupeKey: 'reminder.7'), '43');

        self::assertSame(DeliveryStatus::Duplicate, $other->status);
        self::assertSame('web_push', $elsewhere->channel());
        self::assertSame(['sms' => SkipReason::NoContact], $elsewhere->skipped);
        self::assertSame(['sms'], $this->channels->calls);
    }

    public function testTheWindowOfATypeWinsAndZeroTurnsItOff(): void
    {
        $short = $this->dispatcher($this->type(dedupeTtl: 10), dedupe: true);
        $short->deliver($this->message(), '42');

        $this->clock->sleep(11);

        self::assertSame(DeliveryStatus::Sent, $short->deliver($this->message(), '42')->status);

        $off = $this->dispatcher(new NotificationType('comment.replied', ['site', 'sms'], delivery: DeliveryMode::FirstSuccess, dedupeTtl: 0), dedupe: true);

        self::assertSame(DeliveryStatus::Sent, $off->deliver($this->message(), '42')->status);
        self::assertSame(DeliveryStatus::Sent, $off->deliver($this->message(), '42')->status);
        self::assertSame(4, $this->repository->unreadCount('42'));
    }

    public function testATotalFailureIsThrownAfterItsEventAndRetriedWithoutASecondEntryInTheCentre(): void
    {
        $this->channels->answers = ['sms' => ChannelOutcome::Refused, 'email' => ChannelOutcome::Refused];
        $this->webPush->outcome = ChannelOutcome::Refused;
        $dispatcher = $this->dispatcher($this->type(), dedupe: true);

        try {
            $dispatcher->deliver($this->message(), '42');
            self::fail('A total failure is thrown.');
        } catch (DeliveryFailed $deliveryFailed) {
            self::assertSame(DeliveryStatus::Failed, $deliveryFailed->result->status);
            self::assertTrue($deliveryFailed->result->isRetryable());
            self::assertSame('The notification "comment.replied" reached none of its channels: sms (refused), web_push (refused), email (refused).', $deliveryFailed->getMessage());
            self::assertStringNotContainsString('42', $deliveryFailed->getMessage());
        }

        self::assertCount(1, $this->events->events);
        $event = $this->events->events[0];
        self::assertInstanceOf(NotificationDelivered::class, $event);
        self::assertSame(DeliveryStatus::Failed, $event->result->status);

        $this->channels->answers['email'] = ChannelOutcome::Accepted;
        $retry = $dispatcher->deliver($this->message(), '42');

        self::assertSame('email', $retry->channel());
        self::assertSame(['site', 'email'], $retry->channels);
        self::assertSame(1, $this->repository->unreadCount('42'));
        self::assertCount(1, $this->toasts->published);
        self::assertSame(DeliveryStatus::Duplicate, $dispatcher->deliver($this->message(), '42')->status);
    }

    public function testAFailureWithoutWindowIsThrownAsWell(): void
    {
        $this->channels->answers = ['sms' => ChannelOutcome::Refused];
        $this->expectException(DeliveryFailed::class);

        $this->dispatcher($this->type(['sms'], dedupeTtl: 0))->deliver($this->message(), '42');
    }

    public function testAnOpenCircuitAloneIsWorthARetry(): void
    {
        $breakers = $this->breakers('notifications.sms');
        $breaker = $breakers->get(CircuitKey::of('notifications.sms'));
        $breaker->recordFailure();
        $breaker->recordFailure();

        try {
            $this->dispatcher($this->type(['sms']), breakers: $breakers)->deliver($this->message(), '42');
            self::fail('An open circuit is worth a retry.');
        } catch (DeliveryFailed $deliveryFailed) {
            self::assertSame(['sms' => SkipReason::CircuitOpen], $deliveryFailed->result->skipped);
        }

        self::assertSame([], $this->channels->calls);
    }

    public function testAnyOtherExceptionGoesUpAndFreesTheWindow(): void
    {
        $this->channels->answers['sms'] = new LogicException('A bug of the application.');
        $dispatcher = $this->dispatcher($this->type(), dedupe: true);

        try {
            $dispatcher->deliver($this->message(), '42');
            self::fail('The exception goes up.');
        } catch (LogicException $logicException) {
            self::assertSame('A bug of the application.', $logicException->getMessage());
        }

        self::assertSame([], $this->webPush->sent);
        self::assertSame([], $this->events->events);
        $this->channels->answers['sms'] = ChannelOutcome::Accepted;

        self::assertSame('sms', $dispatcher->deliver($this->message(), '42')->channel());
        self::assertSame(1, $this->repository->unreadCount('42'));
    }

    public function testTheRateLimitLeavesTheNotificationInTheCentre(): void
    {
        $dispatcher = $this->dispatcher($this->type(), dedupe: true, limit: 1);

        $first = $dispatcher->deliver($this->message(), '42');
        $limited = $dispatcher->deliver($this->message(subjectId: '10'), '42');

        self::assertSame(DeliveryStatus::Sent, $first->status);
        self::assertSame(DeliveryStatus::RateLimited, $limited->status);
        self::assertSame(['site'], $limited->channels);
        self::assertSame(['sms'], $this->channels->calls);
        self::assertSame(2, $this->repository->unreadCount('42'));
        self::assertFalse($limited->isRetryable());
        self::assertContains('info: The notification "comment.replied" ended rate_limited (first_success) on site; skipped: none.', $this->logs->records);

        // The refusal frees the window: once the limit allows it, the same notification goes out, the centre untouched.
        self::assertSame(DeliveryStatus::RateLimited, $dispatcher->deliver($this->message(subjectId: '10'), '42')->status);
        self::assertSame(2, $this->repository->unreadCount('42'));
    }

    public function testAnUrgentTypePassesTheLimit(): void
    {
        $dispatcher = $this->dispatcher(new NotificationType('comment.replied', ['site', 'sms'], importance: 'urgent', delivery: DeliveryMode::FirstSuccess), limit: 1);

        $dispatcher->deliver($this->message(), '42');

        $urgent = $dispatcher->deliver($this->message(subjectId: '10'), '42');

        self::assertSame(DeliveryStatus::Sent, $urgent->status);
        self::assertSame(['sms', 'sms'], $this->channels->calls);
    }

    public function testTheCentreAloneSpendsNoToken(): void
    {
        $dispatcher = $this->dispatcher(new NotificationType('comment.replied', ['site', 'sms'], delivery: DeliveryMode::FirstSuccess), limit: 1);
        $this->preferences->settings['42'] = new RecipientSettings(optOuts: ['*:sms']);
        $dispatcher->deliver($this->message(), '42');
        $this->preferences->settings['42'] = new RecipientSettings();

        self::assertSame(DeliveryStatus::Sent, $dispatcher->deliver($this->message(subjectId: '10'), '42')->status);
    }

    public function testTheLimitHoldsInTheFanOutModeToo(): void
    {
        $dispatcher = $this->dispatcher(new NotificationType('comment.replied', ['site', 'email']), limit: 1);

        $dispatcher->deliver($this->message(), '42');

        $limited = $dispatcher->deliver($this->message(subjectId: '10'), '42');

        self::assertSame(DeliveryStatus::RateLimited, $limited->status);
        self::assertSame(DeliveryMode::FanOut, $limited->mode);
        self::assertCount(1, $this->notifier->sent);
    }

    public function testAnEmailLeftToTheDigestCountsAsAccepted(): void
    {
        $this->preferences->settings['42'] = new RecipientSettings(DigestFrequency::Daily);
        $dispatcher = $this->dispatcher($this->type(['email']), limit: 1);
        $result = $dispatcher->deliver($this->message(), '42');

        self::assertSame(['site', 'email'], $result->channels);
        self::assertSame('email', $result->channel());
        self::assertSame([], $this->channels->calls);

        // An email left to the digest spends no token.
        $this->preferences->settings['42'] = new RecipientSettings();

        self::assertSame(DeliveryStatus::Sent, $dispatcher->deliver($this->message(subjectId: '10'), '42')->status);
        self::assertSame(['email'], $this->channels->calls);
    }

    public function testTheDigestTakesItsTurnInTheOrder(): void
    {
        $this->preferences->settings['42'] = new RecipientSettings(DigestFrequency::Daily);
        $result = $this->dispatcher($this->type(['sms', 'email']))->deliver($this->message(), '42');

        self::assertSame('sms', $result->channel());
        self::assertSame(['sms'], $this->channels->calls);
    }

    public function testOptOutsAndQuietHoursAreSkippedWithTheirReason(): void
    {
        $this->preferences->settings['42'] = new RecipientSettings(DigestFrequency::Never, QuietHours::of('11:00', '13:00', 'UTC'), ['comment.replied:email']);
        $result = $this->dispatcher($this->type())->deliver($this->message(), '42');

        self::assertSame(DeliveryStatus::Sent, $result->status);
        self::assertSame(['site'], $result->channels);
        self::assertSame(['email' => SkipReason::OptedOut, 'sms' => SkipReason::QuietHours, 'web_push' => SkipReason::QuietHours], $result->skipped);
        self::assertSame([], $this->toasts->published);
        self::assertSame([], $this->channels->calls);
    }

    public function testOnlyTheCentreIsSent(): void
    {
        $result = $this->dispatcher(new NotificationType('comment.replied', delivery: DeliveryMode::FirstSuccess), limit: 1)->deliver($this->message(), '42');

        self::assertSame(DeliveryStatus::Sent, $result->status);
        self::assertSame(['site'], $result->channels);
        self::assertSame(['info: The notification "comment.replied" ended sent (first_success) on site; skipped: none.'], $this->logs->records);
    }

    public function testTheBroadcastStaysOutsideTheFallback(): void
    {
        $this->dispatcher(new NotificationType('comment.replied', ['sms'], ['chat/discord'], delivery: DeliveryMode::FirstSuccess))->broadcast($this->message());

        self::assertCount(1, $this->notifier->sent);
        self::assertSame([], $this->channels->calls);
    }

    public function testEachDeliveryIsAnEventWithTheRecipientAndALogWithout(): void
    {
        $result = $this->dispatcher($this->type())->deliver($this->message(), '42');

        self::assertCount(1, $this->events->events);
        $event = $this->events->events[0];
        self::assertInstanceOf(NotificationDelivered::class, $event);
        self::assertSame($result, $event->result);
        self::assertSame('42', $event->result->recipient);
        self::assertSame(['info: The notification "comment.replied" ended sent (first_success) on site, sms; skipped: none.'], $this->logs->records);
    }

    public function testWithoutEventsNorLogsTheResultIsReturned(): void
    {
        $types = new NotificationTypes($this->type());
        $dispatcher = new NotificationDispatcher($types, $this->repository, $this->preferences, new NotificationRenderer(Translator::make(), $types, new Directory()), $this->directory, $this->toasts, $this->clock, policy: new DeliveryPolicy([$this->channels]));

        self::assertInstanceOf(DeliveryResult::class, $dispatcher->deliver($this->message(), '42'));
    }

    /**
     * @param list<string> $channels
     */
    private function type(array $channels = ['sms', 'web_push', 'email'], ?int $dedupeTtl = null): NotificationType
    {
        return new NotificationType('comment.replied', ['site', ...$channels], delivery: DeliveryMode::FirstSuccess, dedupeTtl: $dedupeTtl);
    }

    private function breakers(string ...$keys): CircuitBreakers
    {
        return new CircuitBreakers(array_map(fn (string $key): CircuitBreaker => new CircuitBreaker(CircuitKey::of($key), CircuitPolicy::of(50, 2, 60, 30), $this->circuits, new CircuitOverrides(), $this->clock), array_values($keys)));
    }

    private function dispatcher(NotificationType $type, bool $webPush = true, ?CircuitBreakers $breakers = null, bool $dedupe = false, ?int $limit = null): NotificationDispatcher
    {
        $types = new NotificationTypes($type);
        $window = $dedupe ? new NotificationDedupe(new IdempotencyGuard(new CacheIdempotencyStore(new ArrayAdapter(clock: $this->clock), new LockFactory(new InMemoryStore()), $this->clock), $this->clock)) : null;
        $limiter = null === $limit ? null : new RecipientRateLimit(new RateLimiterFactory(['id' => 'notifications', 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => '1 hour'], new InMemoryStorage()));

        return new NotificationDispatcher(
            $types,
            $this->repository,
            $this->preferences,
            new NotificationRenderer(Translator::make(), $types, new Directory(), [new Subjects()]),
            $this->directory,
            $this->toasts,
            $this->clock,
            $this->notifier,
            $webPush ? $this->webPush : null,
            $this->logs,
            new DeliveryPolicy([$this->channels], $window, $limiter, $breakers instanceof CircuitBreakers ? new ChannelBreakers($breakers) : null),
            $this->events,
        );
    }

    private function message(string $subjectId = '9', ?string $dedupeKey = null): SendNotification
    {
        return new SendNotification('comment.replied', ['42'], null, 'comment', $subjectId, ['event' => 7], '17', 'events', true, $dedupeKey);
    }
}
