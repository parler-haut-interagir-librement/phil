<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Infrastructure;

use Phil\MentionsBundle\Application\Event\MemberMentioned;
use Phil\MentionsBundle\Infrastructure\Notifications\NotifyMentionedMember;
use Phil\MentionsBundle\Tests\Unit\Fixture\Bus;
use Phil\MentionsBundle\Tests\Unit\Fixture\Preferences;
use Phil\NotificationsBundle\Application\Handler\SendNotificationHandler;
use Phil\NotificationsBundle\Application\Message\SendNotification;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(NotifyMentionedMember::class)]
final class NotifyMentionedMemberTest extends TestCase
{
    public function testTheMemberIsNotifiedWithIdentifiersOnly(): void
    {
        $bus = new Bus();
        $listener = new NotifyMentionedMember($this->notifications($bus), new Preferences(['silent']));

        $listener(new MemberMentioned(ResourceReference::of('comment', '7'), ResourceReference::of('user', '1'), ResourceReference::of('user', '9'), ResourceReference::of('page', '3'), 'user:1'));
        $listener(new MemberMentioned(ResourceReference::of('page', '3'), ResourceReference::of('user', '2'), ResourceReference::of('user', '9'), ResourceReference::of('page', '3'), 'b-2'));
        $listener(new MemberMentioned(ResourceReference::of('page', '3'), ResourceReference::of('user', '4'), ResourceReference::of('user', '9'), ResourceReference::of('page', '3'), 'silent'));

        self::assertEquals([
            new SendNotification('member.mentioned', ['user:1'], null, 'comment', '7', ['context' => 'page:3'], 'user:9'),
            new SendNotification('member.mentioned', ['b-2'], null, 'page', '3', [], 'user:9'),
        ], $bus->messages);
    }

    public function testTheTypeOfTheConfiguration(): void
    {
        $bus = new Bus();
        new NotifyMentionedMember($this->notifications($bus), new Preferences(), 'mention')(new MemberMentioned(ResourceReference::of('page', '3'), ResourceReference::of('user', '2'), ResourceReference::of('user', '9'), ResourceReference::of('page', '3'), 'b-2'));

        self::assertSame(['mention'], array_map(static fn (object $message): string => $message instanceof SendNotification ? $message->type : '', $bus->messages));
    }

    private function notifications(Bus $bus): Notifications
    {
        // The bus takes every message, so the handler that would deliver it at once is never called.
        $handler = new ReflectionClass(SendNotificationHandler::class)->newInstanceWithoutConstructor();

        return new Notifications(new NotificationTypes(new NotificationType('member.mentioned'), new NotificationType('mention')), $handler, $bus);
    }
}
