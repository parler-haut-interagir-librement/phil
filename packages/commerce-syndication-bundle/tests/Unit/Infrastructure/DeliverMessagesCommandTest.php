<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Phil\CommerceSyndicationBundle\Application\Courier;
use Phil\CommerceSyndicationBundle\Domain\Model\OutgoingMessage;
use Phil\CommerceSyndicationBundle\Infrastructure\Console\DeliverMessagesCommand;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\InMemorySyndication;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\Partners;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\RecordingTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(DeliverMessagesCommand::class)]
final class DeliverMessagesCommandTest extends TestCase
{
    public function testItDeliversWhatIsDueAndFlushes(): void
    {
        $store = new InMemorySyndication();
        $store->addPartner(Partners::reseller());

        for ($queued = 0; $queued < 3; ++$queued) {
            $store->queue(OutgoingMessage::to(Partners::reseller(), 'product.updated', [], new DateTimeImmutable('2026-11-03 09:00:00')));
        }

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('flush');
        $tester = new CommandTester(new DeliverMessagesCommand(new Courier($store, $store, new RecordingTransport(), Partners::ourKey()->toString(), 8), $entityManager, new MockClock('2026-11-03 10:00:00')));

        self::assertSame(Command::SUCCESS, $tester->execute(['--limit' => '2']));
        self::assertSame("2 message(s) delivered.\n", $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame("1 message(s) delivered.\n", $tester->getDisplay());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badLimits(): iterable
    {
        yield 'zero' => ['0'];
        yield 'a word' => ['all'];
        yield 'a leading character' => ['x5'];
        yield 'too many' => ['1000000'];
        yield 'a trailing newline' => ["5\n"];
    }

    #[DataProvider('badLimits')]
    public function testTheLimitIsAWholeNumber(string $limit): void
    {
        $store = new InMemorySyndication();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');
        $tester = new CommandTester(new DeliverMessagesCommand(new Courier($store, $store, new RecordingTransport(), Partners::ourKey()->toString(), 8), $entityManager, new MockClock()));

        self::assertSame(Command::INVALID, $tester->execute(['--limit' => $limit]));
        self::assertSame("The limit is a whole number from 1 to 999999.\n", $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->execute(['--limit' => '999999']));
    }
}
