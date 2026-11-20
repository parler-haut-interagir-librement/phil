<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Application;

use Phil\NotificationsBundle\Application\Delivery\ChannelOutcome;
use Phil\NotificationsBundle\Application\Delivery\DeliveryMode;
use Phil\NotificationsBundle\Application\Delivery\DeliveryPolicy;
use Phil\NotificationsBundle\Application\Exception\DeliveryFailed;
use Phil\NotificationsBundle\Application\Exception\UnknownAudience;
use Phil\NotificationsBundle\Application\Exception\UnknownNotificationType;
use Phil\NotificationsBundle\Application\ForwardingRemoteReceiver;
use Phil\NotificationsBundle\Application\Handler\SendNotificationHandler;
use Phil\NotificationsBundle\Application\Message\SendNotification;
use Phil\NotificationsBundle\Application\NotificationDispatcher;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\NotificationsBundle\Application\RemoteNotification;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Audience;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Directory;
use Phil\NotificationsBundle\Tests\Unit\Fixture\RecordingBus;
use Phil\NotificationsBundle\Tests\Unit\Fixture\RecordingToasts;
use Phil\NotificationsBundle\Tests\Unit\Fixture\ScriptedChannels;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SendNotificationHandler::class)]
#[CoversClass(Notifications::class)]
#[CoversClass(ForwardingRemoteReceiver::class)]
#[CoversClass(UnknownAudience::class)]
final class SendingTest extends TestCase
{
    public function testOneRecipientIsDeliveredAtOnceAndTheBroadcastPosted(): void
    {
        $kit = new Kit(new NotificationType('comment.replied', broadcast: ['chat/slack']));
        $bus = new RecordingBus();
        new SendNotificationHandler($kit->dispatcher(), [], $bus)(new SendNotification('comment.replied', ['42']));

        self::assertSame(1, $kit->repository->unreadCount('42'));
        self::assertSame([], $bus->messages);
        self::assertCount(1, $kit->notifier->sent);
    }

    public function testAMessageForOneWithoutBroadcastPostsNothing(): void
    {
        $kit = new Kit(new NotificationType('comment.replied', broadcast: ['chat/slack']));
        new SendNotificationHandler($kit->dispatcher())(new SendNotification('comment.replied', ['42', '43'])->for('42'));

        self::assertSame(1, $kit->repository->unreadCount('42'));
        self::assertSame([], $kit->notifier->sent);
    }

    public function testSeveralRecipientsAreSplitOnTheBus(): void
    {
        $kit = new Kit(new NotificationType('comment.replied', broadcast: ['chat/slack']));
        $bus = new RecordingBus();
        new SendNotificationHandler($kit->dispatcher(), [], $bus)(new SendNotification('comment.replied', ['42', '43'], null, 'comment', '9'));

        self::assertSame(0, $kit->repository->unreadCount('42'));
        self::assertEquals([
            new SendNotification('comment.replied', ['42'], null, 'comment', '9', broadcast: false),
            new SendNotification('comment.replied', ['43'], null, 'comment', '9', broadcast: false),
        ], $bus->messages);
        self::assertCount(1, $kit->notifier->sent);
    }

    public function testSeveralRecipientsAreDeliveredInTurnWithoutABus(): void
    {
        $kit = new Kit(new NotificationType('comment.replied'));
        new SendNotificationHandler($kit->dispatcher())(new SendNotification('comment.replied', ['42', '43']));

        self::assertSame(1, $kit->repository->unreadCount('42'));
        self::assertSame(1, $kit->repository->unreadCount('43'));
    }

    public function testWithoutABusARecipientNoChannelReachedDoesNotStopTheOthers(): void
    {
        $kit = new Kit(new NotificationType('comment.replied', ['site', 'email'], ['chat/slack'], digest: false, delivery: DeliveryMode::FirstSuccess));
        $channels = new ScriptedChannels(['email' => ChannelOutcome::Refused]);

        try {
            new SendNotificationHandler($kit->dispatcher(new DeliveryPolicy([$channels])))(new SendNotification('comment.replied', ['42', '41', '43']));
            self::fail('The failure is thrown once every recipient was served.');
        } catch (DeliveryFailed $deliveryFailed) {
            self::assertSame('42', $deliveryFailed->result->recipient);
        }

        // 41 and 43 have no address: skipped, not worth a retry. 42 was refused: the first failure is the one thrown.
        self::assertSame(['email'], $channels->calls);
        self::assertSame(1, $kit->repository->unreadCount('43'));
        self::assertSame([], $kit->notifier->sent, 'the broadcast waits for the retry');
    }

    public function testTheFirstFailureIsTheOneThrown(): void
    {
        $kit = new Kit(new NotificationType('comment.replied', ['email'], delivery: DeliveryMode::FirstSuccess));
        $channels = new ScriptedChannels(['email' => ChannelOutcome::Refused]);
        $kit->types = new NotificationTypes(new NotificationType('comment.replied', ['email'], delivery: DeliveryMode::FirstSuccess));

        try {
            new SendNotificationHandler(new NotificationDispatcher($kit->types, $kit->repository, $kit->preferences, $kit->renderer(), new Directory(['42' => 'a@example.test', '43' => 'b@example.test']), new RecordingToasts(), $kit->clock, policy: new DeliveryPolicy([$channels])))(new SendNotification('comment.replied', ['42', '43']));
            self::fail('A failure is thrown.');
        } catch (DeliveryFailed $deliveryFailed) {
            self::assertSame('42', $deliveryFailed->result->recipient);
        }

        self::assertSame(['email', 'email'], $channels->calls);
    }

    public function testOneRecipientNoChannelReachedIsThrownBeforeTheBroadcast(): void
    {
        $kit = new Kit(new NotificationType('comment.replied', ['email'], ['chat/slack'], delivery: DeliveryMode::FirstSuccess));
        $channels = new ScriptedChannels(['email' => ChannelOutcome::Refused]);

        try {
            new SendNotificationHandler($kit->dispatcher(new DeliveryPolicy([$channels])))(new SendNotification('comment.replied', ['42']));
            self::fail('A failure is thrown.');
        } catch (DeliveryFailed) {
        }

        self::assertSame([], $kit->notifier->sent);
    }

    public function testAnAudienceIsResolvedByTheProviderThatSupportsIt(): void
    {
        $kit = new Kit(new NotificationType('event.created'));
        new SendNotificationHandler($kit->dispatcher(), [new Audience('event'), new Audience('segment'), new Audience('segment')])(new SendNotification('event.created', ['9'], 'segment:2'));

        self::assertSame(1, $kit->repository->unreadCount('1'));
        self::assertSame(1, $kit->repository->unreadCount('2'));
        self::assertSame(1, $kit->repository->unreadCount('9'));
        self::assertSame(0, $kit->repository->unreadCount('3'));
    }

    public function testAnAudienceOfOneIsStillResolved(): void
    {
        $kit = new Kit(new NotificationType('event.created'));
        $bus = new RecordingBus();
        new SendNotificationHandler($kit->dispatcher(), [new Audience()], $bus)(new SendNotification('event.created', [], 'segment:1'));

        self::assertEquals([new SendNotification('event.created', ['1'], broadcast: false)], $bus->messages);
    }

    public function testAnUnknownAudienceFails(): void
    {
        $kit = new Kit(new NotificationType('event.created'));

        $this->expectException(UnknownAudience::class);
        $this->expectExceptionMessageIsOrContains('No audience provider supports the audience "members:all".');

        new SendNotificationHandler($kit->dispatcher(), [new Audience()])(new SendNotification('event.created', [], 'members:all'));
    }

    public function testTheEntryPointGoesThroughTheBus(): void
    {
        $kit = new Kit(new NotificationType('comment.replied'));
        $bus = new RecordingBus();
        $notifications = new Notifications($kit->types, new SendNotificationHandler($kit->dispatcher()), $bus);
        $notifications->send('comment.replied', '42', new SubjectReference('comment', '9'), ['event' => 7], '17', 'events');
        $notifications->sendToAudience('comment.replied', 'segment:3', new SubjectReference('comment', '9'), ['event' => 7], '17', 'events');
        $notifications->send('comment.replied', ['42', '43']);
        $notifications->sendToAudience('comment.replied', 'segment:3');
        $notifications->send('comment.replied', '42', dedupeKey: 'reminder:1');
        $notifications->sendToAudience('comment.replied', 'segment:3', dedupeKey: 'reminder:2');

        self::assertEquals([
            new SendNotification('comment.replied', ['42'], null, 'comment', '9', ['event' => 7], '17', 'events'),
            new SendNotification('comment.replied', [], 'segment:3', 'comment', '9', ['event' => 7], '17', 'events'),
            new SendNotification('comment.replied', ['42', '43']),
            new SendNotification('comment.replied', [], 'segment:3'),
            new SendNotification('comment.replied', ['42'], dedupeKey: 'reminder:1'),
            new SendNotification('comment.replied', [], 'segment:3', dedupeKey: 'reminder:2'),
        ], $bus->messages);
        self::assertSame(0, $kit->repository->unreadCount('42'));
    }

    public function testTheEntryPointDeliversAtOnceWithoutABus(): void
    {
        $kit = new Kit(new NotificationType('comment.replied'));
        new Notifications($kit->types, new SendNotificationHandler($kit->dispatcher(), [new Audience()]))->send('comment.replied', '42');
        new Notifications($kit->types, new SendNotificationHandler($kit->dispatcher(), [new Audience()]))->sendToAudience('comment.replied', 'segment:1');

        self::assertSame(1, $kit->repository->unreadCount('42'));
        self::assertSame(1, $kit->repository->unreadCount('1'));
    }

    public function testTheEntryPointRefusesAnUndeclaredType(): void
    {
        $kit = new Kit();

        $this->expectException(UnknownNotificationType::class);

        new Notifications($kit->types, new SendNotificationHandler($kit->dispatcher()))->send('comment.replied', '42');
    }

    public function testTheEntryPointRefusesAnUndeclaredTypeForAnAudience(): void
    {
        $kit = new Kit();

        $this->expectException(UnknownNotificationType::class);

        new Notifications($kit->types, new SendNotificationHandler($kit->dispatcher()))->sendToAudience('comment.replied', 'segment:1');
    }

    public function testARemoteNotificationIsDeliveredUnderItsOrigin(): void
    {
        $kit = new Kit(new NotificationType('comment.replied'));
        $receiver = new ForwardingRemoteReceiver(new Notifications($kit->types, new SendNotificationHandler($kit->dispatcher())));
        $receiver->receive(RemoteNotification::fromPayload(['origin' => 'events', 'notification' => 'comment.replied', 'recipients' => ['42'], 'subject' => ['type' => 'comment', 'id' => '9'], 'data' => ['event' => 7], 'actor' => '17']));

        $kept = $kit->repository->latest('42', 1)[0];

        self::assertSame('events', $kept->origin);
        self::assertSame('comment:9', $kept->subject?->key());
        self::assertSame(['event' => 7], $kept->data->values);
        self::assertSame('17', $kept->actor);
    }
}
