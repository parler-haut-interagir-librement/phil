<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Tests\Unit\Messenger;

use Phil\AccessLinks\AccessLinks;
use Phil\AccessLinks\InMemoryInvitationStore;
use Phil\AccessLinks\LinkFormat;
use Phil\AccessLinks\Messenger\ReminderDispatcher;
use Phil\AccessLinks\Messenger\SendInvitationReminder;
use Phil\AccessLinks\Messenger\SendInvitationReminderHandler;
use Phil\AccessLinks\ReminderSchedule;
use Phil\AccessLinks\Tests\Unit\Fixture\RecordingSender;
use Phil\AccessLinks\TokenHasher;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

#[CoversClass(ReminderDispatcher::class)]
#[CoversClass(SendInvitationReminder::class)]
#[CoversClass(SendInvitationReminderHandler::class)]
final class ReminderMessagesTest extends TestCase
{
    public function testOneHundredMessagesAreDispatchedByDefault(): void
    {
        $clock = new MockClock('2026-11-13 10:00:00', 'UTC');
        $links = new AccessLinks(new InMemoryInvitationStore(), new TokenHasher(str_repeat('k', 32)), new LinkFormat(Url::fromString('https://events.example/rsvp')), $clock, new ReminderSchedule(['P1D']));
        $dispatcher = new ReminderDispatcher($links, new MessageBus());

        for ($i = 0; $i < 101; ++$i) {
            $links->invite('poll-1', 'member-'.$i, $clock->now()->modify('+5 days'));
        }

        $clock->sleep(86_400);

        self::assertSame(100, $dispatcher->dispatchDue());
    }

    public function testDueRemindersLeaveAsMessagesOfIdentifiersAndAreSentOnce(): void
    {
        $clock = new MockClock('2026-11-13 10:00:00', 'UTC');
        $links = new AccessLinks(new InMemoryInvitationStore(), new TokenHasher(str_repeat('k', 32)), new LinkFormat(Url::fromString('https://events.example/rsvp')), $clock, new ReminderSchedule(['P1D']));
        $sender = new RecordingSender();
        $handler = new SendInvitationReminderHandler($links, $sender);
        $messages = [];
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([SendInvitationReminder::class => [
            static function (SendInvitationReminder $message) use ($handler, &$messages): bool {
                $messages[] = $message;

                return $handler($message);
            },
        ]]))]);
        $dispatcher = new ReminderDispatcher($links, $bus);

        $first = $links->invite('poll-1', 'member-7', $clock->now()->modify('+5 days'));
        $links->invite('poll-1', 'member-8', $clock->now()->modify('+5 days'));

        self::assertSame(0, $dispatcher->dispatchDue());
        $clock->sleep(86_400);
        self::assertSame(1, $dispatcher->dispatchDue(1));
        self::assertSame(1, $dispatcher->dispatchDue());
        self::assertSame(0, $dispatcher->dispatchDue());

        self::assertCount(2, $messages);
        self::assertSame(['invitationId' => $messages[0]->invitationId], get_object_vars($messages[0]));
        self::assertCount(2, $sender->sent);
        self::assertFalse($handler(new SendInvitationReminder($first->invitation->id)));
        self::assertFalse($handler(new SendInvitationReminder('unknown')));
        self::assertCount(2, $sender->sent);
    }
}
