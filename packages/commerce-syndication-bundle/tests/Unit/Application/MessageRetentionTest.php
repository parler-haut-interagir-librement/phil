<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceSyndicationBundle\Application\MessageRetention;
use Phil\CommerceSyndicationBundle\Domain\Model\OutgoingMessage;
use Phil\CommerceSyndicationBundle\Domain\Model\ReceivedMessage;
use Phil\CommerceSyndicationBundle\Infrastructure\Console\PurgeMessagesCommand;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\InMemorySyndication;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\Partners;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(MessageRetention::class)]
#[CoversClass(PurgeMessagesCommand::class)]
final class MessageRetentionTest extends TestCase
{
    public function testWhatEndedBeforeTheRetentionIsDeleted(): void
    {
        $store = new InMemorySyndication();
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $delivered = $this->message($store, $at->modify('-91 days'));
        $delivered->delivered($at->modify('-91 days'));

        $givenUp = $this->message($store, $at->modify('-120 days'));
        $givenUp->refused($at->modify('-90 days -1 second'), 'HTTP 400.');

        $recent = $this->message($store, $at->modify('-120 days'));
        $recent->delivered($at->modify('-90 days'));

        $waiting = $this->message($store, $at->modify('-200 days'));
        $store->markReceived(ReceivedMessage::of('msg_1', Partners::supplier(), 'product.updated', $at->modify('-91 days')));
        $store->markReceived(ReceivedMessage::of('msg_2', Partners::supplier(), 'product.updated', $at->modify('-90 days')));

        self::assertSame([2, 1], new MessageRetention($store, 90)->purge($at));

        self::assertSame([$recent, $waiting], $store->outbox);
        self::assertSame(['supplier msg_2'], array_keys($store->received));
    }

    public function testTheCommandPurgesAndSaysHowMuch(): void
    {
        $store = new InMemorySyndication();
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $this->message($store, $at->modify('-10 days'))->delivered($at->modify('-10 days'));
        $tester = new CommandTester(new PurgeMessagesCommand(new MessageRetention($store, 7), new MockClock($at)));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame("1 outgoing message(s) and 0 received id(s) deleted.\n", $tester->getDisplay());
    }

    public function testMessagesAreKeptADayOrMore(): void
    {
        self::assertSame([0, 0], new MessageRetention(new InMemorySyndication(), 1)->purge(new DateTimeImmutable()));

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('Messages are kept one day or more; 0 is refused.');

        new MessageRetention(new InMemorySyndication(), 0);
    }

    private function message(InMemorySyndication $store, DateTimeImmutable $at): OutgoingMessage
    {
        $message = OutgoingMessage::to(Partners::reseller(), 'product.updated', [], $at);
        $store->queue($message);

        return $message;
    }
}
