<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Functional;

use Override;
use Phil\NotificationsBundle\Application\NotificationDispatcher;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Application\OutgoingNotification;
use Phil\NotificationsBundle\Tests\Functional\Chat\ChatKernel;
use Phil\NotificationsBundle\Tests\Functional\Chat\ChatServers;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * A type broadcast to Slack and Discord, through Symfony Notifier's own bridges: one post to each channel, written
 * out from the type's templates.
 */
#[CoversClass(NotificationDispatcher::class)]
#[CoversClass(OutgoingNotification::class)]
final class ChatTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return ChatKernel::class;
    }

    public function testABroadcastIsPostedOnceToEachChatChannel(): void
    {
        $notifications = self::getContainer()->get(Notifications::class);
        self::assertInstanceOf(Notifications::class, $notifications);

        $notifications->send('release.published', []);

        $servers = self::getContainer()->get(ChatServers::class);
        self::assertInstanceOf(ChatServers::class, $servers);
        [$slack, $discord] = $servers->requests;

        self::assertSame('https://slack.com/api/chat.postMessage', $slack['url']);
        self::assertSame([
            'blocks' => [
                ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => 'A new release is out', 'verbatim' => false]],
                ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => 'The notes are on the site.', 'verbatim' => false]],
            ],
            'channel' => 'C0RELEASES',
            'text' => "A new release is out\n\nThe notes are on the site.",
        ], $slack['body']);

        self::assertSame('https://discord.com/api/webhooks/1234/webhook-token', $discord['url']);
        self::assertSame(['content' => "A new release is out\n\nThe notes are on the site."], $discord['body']);
        self::assertCount(2, $servers->requests);
    }

    public function testANotificationWithoutBroadcastPostsNothing(): void
    {
        $notifications = self::getContainer()->get(Notifications::class);
        self::assertInstanceOf(Notifications::class, $notifications);

        $notifications->send('release.drafted', '42');

        $servers = self::getContainer()->get(ChatServers::class);
        self::assertInstanceOf(ChatServers::class, $servers);
        self::assertSame([], $servers->requests);
    }
}
