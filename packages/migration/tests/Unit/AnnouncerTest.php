<?php

declare(strict_types=1);

namespace Phil\Migration\Tests\Unit;

use Phil\Migration\Announcer;
use Phil\Migration\Channel;
use Phil\Migration\ChatterPublisher;
use Phil\Migration\Release;
use Phil\Migration\Tests\Unit\Fixtures\RecordingLogger;
use Phil\Migration\Tests\Unit\Fixtures\RecordingPublisher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;

#[CoversClass(Announcer::class)]
#[CoversClass(ChatterPublisher::class)]
final class AnnouncerTest extends TestCase
{
    public function testEveryChannelIsToldAndAFailingOneIsLogged(): void
    {
        $publisher = new RecordingPublisher(['down']);
        $logger = new RecordingLogger();
        $release = new Release([['fr' => str_repeat('a', 80)], ['fr' => str_repeat('b', 80)]]);

        new Announcer([new Channel('down', 'fr', maxLength: 100), new Channel('alice', 'fr', maxLength: 100), new Channel('bob', 'en')], $publisher, $logger)->announce($release);

        self::assertSame([['alice', str_repeat('a', 80)], ['alice', str_repeat('b', 80)]], $publisher->published);
        self::assertSame(['error The release could not be announced on {transport}: {reason} down down is down.'], $logger->logged);

        new Announcer([new Channel('down', 'fr')], $publisher)->announce($release);
        self::assertCount(2, $publisher->published);
    }

    public function testTheChatterSendsOnTheTransportNamed(): void
    {
        $chatter = new class implements ChatterInterface {
            /** @var list<ChatMessage> */
            public array $sent = [];

            public function send(MessageInterface $message): ?SentMessage
            {
                if ($message instanceof ChatMessage) {
                    $this->sent[] = $message;
                }

                return null;
            }

            public function supports(MessageInterface $message): bool
            {
                return true;
            }

            public function __toString(): string
            {
                return 'recording';
            }
        };

        new ChatterPublisher($chatter)->publish('discord_alice', '# Nouvelle version');

        self::assertCount(1, $chatter->sent);
        self::assertSame(['discord_alice', '# Nouvelle version'], [$chatter->sent[0]->getTransport(), $chatter->sent[0]->getSubject()]);
    }
}
