<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Application;

use Phil\NotificationsBundle\Application\Delivery\ChannelOutcome;
use Phil\NotificationsBundle\Application\Delivery\DeliveryMode;
use Phil\NotificationsBundle\Application\Delivery\DeliveryStatus;
use Phil\NotificationsBundle\Application\Delivery\SkipReason;
use Phil\NotificationsBundle\Application\Message\SendNotification;
use Phil\NotificationsBundle\Application\NotificationDispatcher;
use Phil\NotificationsBundle\Application\NotificationRenderer;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\NotificationsBundle\Application\Preferences\DigestFrequency;
use Phil\NotificationsBundle\Application\Preferences\QuietHours;
use Phil\NotificationsBundle\Application\Preferences\RecipientSettings;
use Phil\NotificationsBundle\Infrastructure\Memory\InMemoryNotificationRepository;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Directory;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Logs;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Preferences;
use Phil\NotificationsBundle\Tests\Unit\Fixture\RecordingNotifier;
use Phil\NotificationsBundle\Tests\Unit\Fixture\RecordingToasts;
use Phil\NotificationsBundle\Tests\Unit\Fixture\RecordingWebPush;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Subjects;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Notifier\Recipient\NoRecipient;
use Symfony\Component\Notifier\Recipient\Recipient;

#[CoversClass(NotificationDispatcher::class)]
final class DispatcherTest extends TestCase
{
    private InMemoryNotificationRepository $repository;

    private Preferences $preferences;

    private RecordingToasts $toasts;

    private RecordingNotifier $notifier;

    private RecordingWebPush $webPush;

    private Logs $logs;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->repository = new InMemoryNotificationRepository();
        $this->preferences = new Preferences();
        $this->toasts = new RecordingToasts();
        $this->notifier = new RecordingNotifier();
        $this->webPush = new RecordingWebPush();
        $this->logs = new Logs();
        $this->clock = new MockClock('2026-11-08 12:00:00 UTC');
    }

    public function testTheCentreKeepsItAndShowsAToast(): void
    {
        $this->dispatcher(new NotificationType('comment.replied'))->deliver($this->message(), '42');
        $kept = $this->repository->latest('42', 10);

        self::assertCount(1, $kept);
        self::assertSame('comment.replied', $kept[0]->type);
        self::assertSame('42', $kept[0]->recipient);
        self::assertSame('17', $kept[0]->actor);
        self::assertSame('comment:9', $kept[0]->subject?->key());
        self::assertSame(['event' => 7], $kept[0]->data->values);
        self::assertSame('events', $kept[0]->origin);
        self::assertSame('2026-11-08 12:00:00', $kept[0]->createdAt->format('Y-m-d H:i:s'));
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7/', $kept[0]->id);
        self::assertCount(1, $this->toasts->published);
        self::assertSame($kept[0], $this->toasts->published[0][0]->notification);
        self::assertSame('Grace replied to comment 9', $this->toasts->published[0][0]->title);
        self::assertSame(1, $this->toasts->published[0][1]);
        self::assertSame([], $this->notifier->sent);
    }

    public function testSimilarNotificationsCollapseWhenTheTypeSaysSo(): void
    {
        $dispatcher = $this->dispatcher(new NotificationType('comment.replied', collapse: true));
        $dispatcher->deliver($this->message(), '42');
        $dispatcher->deliver($this->message(), '42');

        self::assertCount(1, $this->repository->latest('42', 10));
        self::assertSame(2, $this->repository->latest('42', 10)[0]->count);
        self::assertSame('2 replies on event 7', $this->toasts->published[1][0]->body);

        $other = $this->dispatcher(new NotificationType('comment.replied'));
        $other->deliver($this->message(), '42');
        self::assertCount(2, $this->repository->latest('42', 10));
    }

    public function testATypeWithoutToast(): void
    {
        $this->dispatcher(new NotificationType('comment.replied', toast: false))->deliver($this->message(), '42');

        self::assertSame(1, $this->repository->unreadCount('42'));
        self::assertSame([], $this->toasts->published);
    }

    public function testEmailGoesThroughNotifierToTheAddressOfTheDirectory(): void
    {
        $this->dispatcher(new NotificationType('comment.replied', ['email', 'sms'], importance: 'high'))->deliver($this->message(), '42');

        self::assertSame(0, $this->repository->unreadCount('42'));
        self::assertSame([], $this->toasts->published);
        self::assertCount(1, $this->notifier->sent);
        [$notification, $recipients] = $this->notifier->sent[0];
        self::assertSame('Grace replied to comment 9', $notification->getSubject());
        self::assertSame('1 replies on event 7', $notification->getContent());
        self::assertSame('high', $notification->getImportance());
        self::assertSame(['email', 'sms'], $notification->getChannels(new NoRecipient()));
        self::assertInstanceOf(Recipient::class, $recipients[0]);
        self::assertSame('ada@example.test', $recipients[0]->getEmail());
    }

    public function testEmailIsLeftToTheDigestOfThoseWhoChoseOne(): void
    {
        $this->preferences->settings['42'] = new RecipientSettings(DigestFrequency::Daily);
        $this->dispatcher(new NotificationType('comment.replied', ['site', 'email']))->deliver($this->message(), '42');

        self::assertSame(1, $this->repository->unreadCount('42'));
        self::assertSame([], $this->notifier->sent);
    }

    public function testEmailIsSentWhenTheTypeSkipsTheDigest(): void
    {
        $this->preferences->settings['42'] = new RecipientSettings(DigestFrequency::Daily);
        $this->dispatcher(new NotificationType('comment.replied', ['site', 'email'], digest: false))->deliver($this->message(), '42');

        self::assertCount(1, $this->notifier->sent);
        self::assertSame(['email'], $this->notifier->sent[0][0]->getChannels(new NoRecipient()));
    }

    public function testEmailIsSentWhenTheCentreDoesNotKeepIt(): void
    {
        $this->preferences->settings['42'] = new RecipientSettings(DigestFrequency::Daily);
        $this->dispatcher(new NotificationType('comment.replied', ['email']))->deliver($this->message(), '42');

        self::assertCount(1, $this->notifier->sent);
    }

    public function testATurnedOffChannelIsSkipped(): void
    {
        $this->preferences->settings['42'] = new RecipientSettings(optOuts: ['comment.replied:site', '*:sms']);
        $this->dispatcher(new NotificationType('comment.replied', ['site', 'email', 'sms']))->deliver($this->message(), '42');

        self::assertSame(0, $this->repository->unreadCount('42'));
        self::assertSame(['email'], $this->notifier->sent[0][0]->getChannels(new NoRecipient()));
    }

    public function testQuietHoursHoldBackToastsAndInterruptions(): void
    {
        $this->preferences->settings['42'] = new RecipientSettings(quietHours: QuietHours::of('11:00', '13:00'));
        $this->dispatcher(new NotificationType('comment.replied', ['site', 'email', 'sms', 'web_push']))->deliver($this->message(), '42');

        self::assertSame(1, $this->repository->unreadCount('42'));
        self::assertSame([], $this->toasts->published);
        self::assertSame([], $this->webPush->sent);
        self::assertSame(['email'], $this->notifier->sent[0][0]->getChannels(new NoRecipient()));
    }

    public function testOutsideQuietHoursEverythingGoes(): void
    {
        $this->preferences->settings['42'] = new RecipientSettings(quietHours: QuietHours::of('13:00', '14:00'));
        $this->dispatcher(new NotificationType('comment.replied', ['site', 'sms', 'web_push']))->deliver($this->message(), '42');

        self::assertCount(1, $this->toasts->published);
        self::assertCount(1, $this->webPush->sent);
        self::assertSame(['sms'], $this->notifier->sent[0][0]->getChannels(new NoRecipient()));
    }

    public function testQuietHoursAloneSendNothingElse(): void
    {
        $this->preferences->settings['42'] = new RecipientSettings(quietHours: QuietHours::of('11:00', '13:00'));
        $this->dispatcher(new NotificationType('comment.replied', ['web_push']))->deliver($this->message(), '42');

        self::assertSame([], $this->webPush->sent);
        self::assertSame([], $this->notifier->sent);
    }

    public function testWebPushGoesToTheSenderWithTheKeptNotification(): void
    {
        $this->dispatcher(new NotificationType('comment.replied', ['site', 'web_push']))->deliver($this->message(), '42');

        self::assertCount(1, $this->webPush->sent);
        self::assertSame('42', $this->webPush->sent[0][0]);
        self::assertSame($this->repository->latest('42', 1)[0], $this->webPush->sent[0][1]->notification);
        self::assertSame([], $this->notifier->sent);
    }

    public function testWebPushWithoutSenderIsSkippedAndLogged(): void
    {
        $result = $this->dispatcher(new NotificationType('comment.replied', ['web_push']), webPush: false)->deliver($this->message(), '42');

        self::assertSame(DeliveryStatus::Sent, $result->status);
        self::assertSame([], $result->channels);
        self::assertSame(['web_push' => SkipReason::Unavailable], $result->skipped);
        self::assertSame(['info: The notification "comment.replied" ended sent (fan_out) on none; skipped: web_push (unavailable).'], $this->logs->records);
    }

    public function testARefusedOrUnreachableWebPushIsSkipped(): void
    {
        $this->webPush->outcome = ChannelOutcome::Refused;
        $refused = $this->dispatcher(new NotificationType('comment.replied', ['site', 'web_push']))->deliver($this->message(), '42');
        $this->webPush->outcome = ChannelOutcome::Unreachable;
        $unreachable = $this->dispatcher(new NotificationType('comment.replied', ['site', 'web_push']))->deliver($this->message(), '42');

        self::assertSame(['web_push' => SkipReason::Refused], $refused->skipped);
        self::assertSame(['web_push' => SkipReason::NoContact], $unreachable->skipped);
        self::assertSame(['site'], $unreachable->channels);
        self::assertSame(DeliveryStatus::Sent, $refused->status);
    }

    public function testARecipientWithoutAddressIsSkippedWithoutBeingNamedInTheLogs(): void
    {
        $result = $this->dispatcher(new NotificationType('comment.replied', ['email', 'sms']))->deliver($this->message(), '43');

        self::assertSame([], $this->notifier->sent);
        self::assertSame(['email' => SkipReason::NoContact, 'sms' => SkipReason::NoContact], $result->skipped);
        self::assertSame('43', $result->recipient);
        self::assertSame(['info: The notification "comment.replied" ended sent (fan_out) on none; skipped: email (no_contact), sms (no_contact).'], $this->logs->records);
    }

    public function testWithoutNotifierTheChannelsAreUnavailable(): void
    {
        $result = $this->dispatcher(new NotificationType('comment.replied', ['email', 'sms']), notifier: false)->deliver($this->message(), '42');

        self::assertSame(['email' => SkipReason::Unavailable, 'sms' => SkipReason::Unavailable], $result->skipped);
        self::assertSame(['info: The notification "comment.replied" ended sent (fan_out) on none; skipped: email (unavailable), sms (unavailable).'], $this->logs->records);
    }

    public function testTheBroadcastWithoutNotifierIsLogged(): void
    {
        $this->dispatcher(new NotificationType('comment.replied', [], ['chat/discord']), notifier: false)->broadcast($this->message());

        self::assertSame(['warning: The notification "comment.replied" was not sent on chat/discord: Symfony Notifier is not installed.'], $this->logs->records);
    }

    public function testTheResultNamesWhatTookIt(): void
    {
        $this->preferences->settings['42'] = new RecipientSettings(DigestFrequency::Daily, optOuts: ['*:sms']);
        $result = $this->dispatcher(new NotificationType('comment.replied', ['site', 'email', 'sms', 'chat/slack']))->deliver($this->message(), '42');

        self::assertSame(DeliveryMode::FanOut, $result->mode);
        self::assertSame(['site', 'email', 'chat/slack'], $result->channels);
        self::assertSame(['sms' => SkipReason::OptedOut], $result->skipped);
        self::assertSame('email', $result->channel());
        self::assertSame(['chat/slack'], $this->notifier->sent[0][0]->getChannels(new NoRecipient()));
    }

    public function testItIsWrittenInTheRecipientsLocale(): void
    {
        $this->preferences->settings['42'] = new RecipientSettings(locale: 'fr');
        $this->dispatcher(new NotificationType('comment.replied', ['site', 'email']))->deliver($this->message(), '42');

        self::assertSame('Grace a répondu à comment 9 (fr)', $this->toasts->published[0][0]->title);
        self::assertSame('Grace a répondu à comment 9 (fr)', $this->notifier->sent[0][0]->getSubject());
    }

    public function testNothingIsWrittenOutWhenOnlyTheCentreKeepsIt(): void
    {
        $subjects = new Subjects();
        $this->dispatcher(new NotificationType('comment.replied', toast: false), subjects: $subjects)->deliver($this->message(), '42');

        self::assertSame(1, $this->repository->unreadCount('42'));
        self::assertSame(0, $subjects->calls);
    }

    public function testACollapsedNotificationIsSentAsTheOneKept(): void
    {
        $dispatcher = $this->dispatcher(new NotificationType('comment.replied', ['site', 'email'], collapse: true, digest: false));
        $dispatcher->deliver($this->message(), '42');
        $dispatcher->deliver($this->message(), '42');

        self::assertSame('2 replies on event 7', $this->notifier->sent[1][0]->getContent());
    }

    public function testTheNotifierChannelsKeepTheirOrderWhenWebPushComesFirst(): void
    {
        $result = $this->dispatcher(new NotificationType('comment.replied', ['web_push', 'email', 'sms']))->deliver($this->message(), '42');

        self::assertSame(['web_push', 'email', 'sms'], $result->channels);
        self::assertSame(['email', 'sms'], $this->notifier->sent[0][0]->getChannels(new NoRecipient()));
    }

    public function testTheBroadcastWithoutNotifierNorLoggerIsLeftQuietly(): void
    {
        $types = new NotificationTypes(new NotificationType('comment.replied', [], ['chat/discord']));
        $dispatcher = new NotificationDispatcher($types, $this->repository, $this->preferences, new NotificationRenderer(Translator::make(), $types, new Directory()), new Directory(), $this->toasts, $this->clock);
        $dispatcher->broadcast($this->message());

        self::assertSame([], $this->notifier->sent);
        self::assertSame([], $this->logs->records);
    }

    public function testWithoutLoggerWhatIsNotSentIsSkippedQuietly(): void
    {
        $types = new NotificationTypes(new NotificationType('comment.replied', ['email', 'web_push']));
        $dispatcher = new NotificationDispatcher($types, $this->repository, $this->preferences, new NotificationRenderer(Translator::make(), $types, new Directory()), new Directory(), $this->toasts, $this->clock);
        $dispatcher->deliver($this->message(), '42');
        $dispatcher->deliver($this->message(), '43');

        self::assertSame([], $this->notifier->sent);
        self::assertSame([], $this->logs->records);
    }

    public function testTheBroadcastChannelsArePostedToOnce(): void
    {
        $this->dispatcher(new NotificationType('comment.replied', [], ['chat/discord'], importance: 'low'))->broadcast($this->message());

        self::assertCount(1, $this->notifier->sent);
        [$notification, $recipients] = $this->notifier->sent[0];
        self::assertSame(['chat/discord'], $notification->getChannels(new NoRecipient()));
        self::assertSame('Grace replied to comment 9', $notification->getSubject());
        self::assertSame('1 replies on event 7', $notification->getContent());
        self::assertSame('low', $notification->getImportance());
        self::assertInstanceOf(NoRecipient::class, $recipients[0]);
        self::assertSame(0, $this->repository->unreadCount('broadcast'));
    }

    public function testATypeWithoutBroadcastPostsNothing(): void
    {
        $this->dispatcher(new NotificationType('comment.replied'))->broadcast($this->message());

        self::assertSame([], $this->notifier->sent);
    }

    private function dispatcher(NotificationType $type, bool $notifier = true, bool $webPush = true, ?Subjects $subjects = null): NotificationDispatcher
    {
        $types = new NotificationTypes($type);

        return new NotificationDispatcher(
            $types,
            $this->repository,
            $this->preferences,
            new NotificationRenderer(Translator::make(), $types, new Directory(), [$subjects ?? new Subjects()]),
            new Directory(),
            $this->toasts,
            $this->clock,
            $notifier ? $this->notifier : null,
            $webPush ? $this->webPush : null,
            $this->logs,
        );
    }

    private function message(): SendNotification
    {
        return new SendNotification('comment.replied', ['42'], null, 'comment', '9', ['event' => 7], '17', 'events');
    }
}
