<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Override;
use Phil\CommerceStoredValueBundle\Application\StoreCredits;
use Phil\CommerceStoredValueBundle\Domain\Instrument\MovementKind;
use Phil\CommerceStoredValueBundle\Domain\Model\Instrument;
use Phil\CommerceStoredValueBundle\Domain\ValueObject\GiftCardCode;
use Phil\CommerceStoredValueBundle\Tests\Unit\Support\InstrumentsView;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Value created once on a real database: requests in separate processes racing to issue the gift cards of one order,
 * a request that counted the cards too early finding those of the first, the caller's transaction and entity manager
 * left usable by that refusal, and the unique constraints of the movements.
 */
abstract class ServerTestCase extends TestCase
{
    private const int RACERS = 6;

    private Server $server;

    /**
     * The URL of the database.
     */
    abstract protected function url(): string;

    #[Override]
    protected function setUp(): void
    {
        $this->server = Server::connect($this->url());
        $this->server->recreate();
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->server)) {
            $this->server->entityManager->getConnection()->close();
        }
    }

    public function testRequestsRacingToIssueTheCardsOfAnOrderIssueThemOnce(): void
    {
        $moment = \sprintf('%.6F', microtime(true) + 1.5);
        $processes = [];

        for ($racer = 0; $racer < self::RACERS; ++$racer) {
            $process = new Process([\PHP_BINARY, __DIR__.'/issue.php', $this->url(), 'A-1', $moment]);
            $process->setTimeout(60);
            $process->start();
            $processes[] = $process;
        }

        $answers = [];

        foreach ($processes as $process) {
            $process->wait();
            $answers[] = trim($process->getOutput()).trim($process->getErrorOutput());
        }

        sort($answers);
        self::assertSame([...array_fill(0, self::RACERS - 1, '0/3'), '3/3'], $answers);
        self::assertSame(3, $this->server->repository()->issuedFor('A-1'));
        self::assertSame(3, $this->rows('SELECT COUNT(*) FROM phil_commerce_stored_value_instrument'));
    }

    public function testARequestThatCountedTooEarlyFindsTheCardsOfTheFirst(): void
    {
        $first = $this->server->giftCards()->issueFor(Server::order('A-1'), $this->at());
        $other = Server::connect($this->url());

        try {
            $second = $other->giftCards($this->stale($other))->issueFor(Server::order('A-1'), $this->at());
        } finally {
            $other->entityManager->getConnection()->close();
        }

        self::assertCount(3, $first);
        self::assertSame([], $second->issued);
        self::assertSame(array_map(static fn (Instrument $card): string => $card->id, $first->cards), array_map(static fn (Instrument $card): string => $card->id, $second->cards));
        self::assertSame(3, $this->rows('SELECT COUNT(*) FROM phil_commerce_stored_value_instrument'));
    }

    public function testTheRefusalLeavesTheCallersTransactionAndEntityManagerUsable(): void
    {
        $this->server->giftCards()->issueFor(Server::order('A-1'), $this->at());
        $other = Server::connect($this->url());
        $connection = $other->entityManager->getConnection();

        try {
            $connection->beginTransaction();
            new StoreCredits($other->repository())->grant('ada', $this->cad(1_000), $this->at(), idempotencyKey: 'return-1');
            $second = $other->giftCards($this->stale($other))->issueFor(Server::order('A-1'), $this->at());
            $other->entityManager->flush();
            $connection->commit();
        } finally {
            $connection->close();
        }

        self::assertSame([[], 3], [$second->issued, \count($second->cards)]);
        $this->server->entityManager->clear();
        self::assertSame(1_000, new StoreCredits($this->server->repository())->balance('ada', Currency::of('CAD'))->minorUnits);
        self::assertSame(3, $this->rows('SELECT COUNT(*) FROM phil_commerce_stored_value_instrument WHERE code_hash IS NOT NULL'));
    }

    public function testIssuedCardsAreStoredAtOnceAndReadBack(): void
    {
        $issuance = $this->server->giftCards()->issueFor(Server::order('A-1'), $this->at());

        self::assertTrue($this->server->entityManager->contains($issuance->issued[0]->card), 'the application works on the cards its own manager holds');
        $this->server->entityManager->clear();
        $units = $this->server->entityManager->getConnection()->fetchFirstColumn('SELECT issued_unit FROM phil_commerce_stored_value_movement ORDER BY issued_unit');
        self::assertSame([0, 1, 2], array_map(static fn (mixed $unit): int => is_numeric($unit) ? (int) $unit : -1, $units));
        self::assertSame(15_000, array_sum(array_map(static fn (Instrument $card): int => $card->balance->minorUnits, $this->server->repository()->cardsIssuedFor('A-1'))));
    }

    public function testAGrantUnderAKeyIsRecordedOnce(): void
    {
        $credits = new StoreCredits($this->server->repository());
        $first = $credits->grant('ada', $this->cad(1_000), $this->at(), idempotencyKey: 'return-1');
        $this->server->entityManager->flush();
        $this->server->entityManager->clear();

        $replayed = $credits->grant('ada', $this->cad(1_000), $this->at(), idempotencyKey: 'return-1');
        $this->server->entityManager->flush();
        $this->server->entityManager->clear();

        self::assertSame($first->id, $replayed->id);
        self::assertSame(1_000, $credits->balance('ada', Currency::of('CAD'))->minorUnits);
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM phil_commerce_stored_value_movement'));
    }

    public function testTheDatabaseRefusesAKeyTwiceOnOneInstrument(): void
    {
        $credit = Instrument::storeCredit('ada', Currency::of('CAD'), $this->at());
        $repository = $this->server->repository();
        $repository->addInstrument($credit);
        $repository->addMovement($credit->credit(MovementKind::Granted, $this->cad(100), $this->at(), idempotencyKey: 'return-1'));
        $repository->addMovement($credit->credit(MovementKind::Granted, $this->cad(100), $this->at()));
        $repository->addMovement($credit->credit(MovementKind::Granted, $this->cad(100), $this->at()));

        $this->server->entityManager->flush();
        $repository->addMovement($credit->credit(MovementKind::Granted, $this->cad(100), $this->at(), idempotencyKey: 'return-1'));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->server->entityManager->flush();
    }

    public function testTheDatabaseRefusesARankTwiceForOneOrder(): void
    {
        $repository = $this->server->repository();

        foreach ([null, null, 0] as $rank) {
            [$card, $issued] = Instrument::giftCard(GiftCardCode::generate(), $this->cad(100), $this->at(), null, 'A-1', issuedUnit: $rank);
            $repository->addInstrument($card);
            $repository->addMovement($issued);
        }

        $this->server->entityManager->flush();
        [$card, $issued] = Instrument::giftCard(GiftCardCode::generate(), $this->cad(100), $this->at(), null, 'A-1', issuedUnit: 0);

        self::assertFalse($repository->addIssuedCards([[$card, $issued]]));
        self::assertSame(3, $this->rows('SELECT COUNT(*) FROM phil_commerce_stored_value_movement'));
        self::assertNull($repository->movementUnderKey($card->id, 'return-1'));
    }

    private function stale(Server $server): InstrumentsView
    {
        return new class($server->repository()) extends InstrumentsView {
            #[Override]
            public function issuedFor(string $orderNumber): int
            {
                return 0;
            }
        };
    }

    private function rows(string $sql): int
    {
        $count = $this->server->entityManager->getConnection()->fetchOne($sql);
        self::assertIsNumeric($count);

        return (int) $count;
    }

    private function at(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-20 10:05:00');
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
