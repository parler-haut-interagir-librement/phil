<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\WaitlistBundle\Domain\Model\Entry;
use Phil\WaitlistBundle\Domain\Model\Waitlist;
use Phil\WaitlistBundle\Domain\Status\EntryStatus;
use Phil\WaitlistBundle\Domain\Status\WaitlistMode;
use Phil\WaitlistBundle\Infrastructure\Doctrine\DoctrineEntryRepository;
use Phil\WaitlistBundle\Infrastructure\Doctrine\DoctrineWaitlistRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The lists and entries on SQLite in memory, their tables created from the mapping.
 */
#[CoversClass(DoctrineWaitlistRepository::class)]
#[CoversClass(DoctrineEntryRepository::class)]
#[CoversClass(Waitlist::class)]
#[CoversClass(Entry::class)]
final class DoctrineRepositoriesTest extends TestCase
{
    private EntityManager $entityManager;

    private DoctrineWaitlistRepository $waitlists;

    private DoctrineEntryRepository $entries;

    protected function setUp(): void
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('PRAGMA foreign_keys = ON');

        $this->entityManager = new EntityManager($connection, $configuration);
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
        $this->waitlists = new DoctrineWaitlistRepository($this->entityManager);
        $this->entries = new DoctrineEntryRepository($this->entityManager);
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment.' UTC');
    }

    private function waitlist(string $subject = 'S-1'): Waitlist
    {
        $waitlist = Waitlist::open('session', $subject, WaitlistMode::Queue, 3_600, $this->at('2026-11-01 08:00'));
        $this->waitlists->save($waitlist);

        return $waitlist;
    }

    private function join(Waitlist $waitlist, string $participant, string $moment, int $places = 1): Entry
    {
        $entry = Entry::join($waitlist, $participant, $places, $this->at($moment));
        $this->entries->save($entry);

        return $entry;
    }

    public function testAListIsKeptAndFoundBySubject(): void
    {
        $waitlist = $this->waitlist();
        $waitlist->hold(3);
        $waitlist->close($this->at('2026-11-02 08:00'));

        $this->waitlists->save($waitlist);
        $this->waitlist('S-2');
        $this->entityManager->clear();

        $found = $this->waitlists->ofSubject('session', 'S-1');
        self::assertInstanceOf(Waitlist::class, $found);
        self::assertSame([$waitlist->id, 3, WaitlistMode::Queue, 3_600], [$found->id, $found->heldPlaces(), $found->mode(), $found->offerWindow()]);
        self::assertEquals($this->at('2026-11-02 08:00'), $found->closedAt());
        self::assertSame($found, $this->waitlists->get($waitlist->id));
        self::assertNull($this->waitlists->ofSubject('session', 'S-3'));
        self::assertNull($this->waitlists->ofSubject('event', 'S-1'));
        self::assertNull($this->waitlists->get('missing'));
    }

    public function testEntriesAreReadByRank(): void
    {
        $waitlist = $this->waitlist();
        $other = $this->waitlist('S-2');
        $second = $this->join($waitlist, 'account-2', '2026-11-01 09:00:00.000002');
        $first = $this->join($waitlist, 'account-1', '2026-11-01 09:00:00.000001');
        $third = $this->join($waitlist, 'account-3', '2026-11-01 10:00', 3);
        $this->join($other, 'account-1', '2026-11-01 07:00');
        $third->offer($this->at('2026-11-01 11:00'), $this->at('2026-11-01 12:00'));
        $this->entries->save($third);
        $this->entityManager->clear();

        self::assertSame([$first->id, $second->id], $this->ids($this->entries->waiting($this->reload($waitlist), 10)));
        self::assertSame([$first->id], $this->ids($this->entries->waiting($this->reload($waitlist), 1)));
        self::assertSame([$first->id, $second->id, $third->id], $this->ids($this->entries->ofWaitlist($this->reload($waitlist), 10)));
        self::assertSame([$third->id], $this->ids($this->entries->ofWaitlist($this->reload($waitlist), 10, EntryStatus::Offered)));
        self::assertSame([0, 1], [$this->entries->ahead($this->entry($first)), $this->entries->ahead($this->entry($second))]);
        self::assertSame([2, 3, 0], [$this->entries->places($this->reload($waitlist), EntryStatus::Waiting), $this->entries->places($this->reload($waitlist), EntryStatus::Offered), $this->entries->places($this->reload($waitlist), EntryStatus::Lapsed)]);
    }

    public function testALiveEntryIsFoundByParticipant(): void
    {
        $waitlist = $this->waitlist();
        $left = $this->join($waitlist, 'account-1', '2026-11-01 09:00');
        $left->leave($this->at('2026-11-01 09:30'));

        $this->entries->save($left);

        self::assertNull($this->entries->live($waitlist, 'account-1'));

        $again = $this->join($waitlist, 'account-1', '2026-11-01 10:00');
        $again->offer($this->at('2026-11-01 10:00'), $this->at('2026-11-01 11:00'));

        $this->entries->save($again);

        self::assertSame($again, $this->entries->live($waitlist, 'account-1'));
        self::assertNull($this->entries->live($this->waitlist('S-2'), 'account-1'));
        self::assertNull($this->entries->live($waitlist, 'account-2'));
    }

    public function testDrawnEntriesRankBeforeQueuedOnes(): void
    {
        $waitlist = Waitlist::open('convention', 'C-1', WaitlistMode::Draw, 3_600, $this->at('2026-11-01 08:00'));
        $this->waitlists->save($waitlist);
        $early = $this->join($waitlist, 'account-1', '2026-11-01 09:00');
        $late = $this->join($waitlist, 'account-2', '2026-11-01 10:00');

        self::assertSame([$early->id], $this->ids($this->entries->queued($waitlist, 1)));
        self::assertSame([$early->id, $late->id], $this->ids($this->entries->queued($waitlist, 10)));

        $late->rankBy('seed-2026');
        $this->entries->save($late);

        self::assertSame([$early->id], $this->ids($this->entries->queued($waitlist, 10)));
        self::assertSame([$late->id, $early->id], $this->ids($this->entries->waiting($waitlist, 10)));
        self::assertSame(1, $this->entries->ahead($early));
    }

    public function testExpiredOffersAreFoundByDeadline(): void
    {
        $waitlist = $this->waitlist();
        $other = $this->waitlist('S-2');
        $later = $this->offered($waitlist, 'account-1', '2026-11-01 12:00');
        $sooner = $this->offered($waitlist, 'account-2', '2026-11-01 11:00');
        $this->offered($waitlist, 'account-3', '2026-11-01 14:00');
        $this->offered($other, 'account-1', '2026-11-01 11:30');
        $this->join($waitlist, 'account-4', '2026-11-01 08:00');

        $now = new DateTimeImmutable('2026-11-01 08:00 America/Toronto');
        self::assertSame([$sooner->id, $later->id], $this->ids($this->entries->expired($waitlist, $now, 10)));
        self::assertSame([$sooner->id], $this->ids($this->entries->expired($waitlist, $now, 1)));
        self::assertSame([$sooner->id], $this->ids($this->entries->expired($waitlist, $this->at('2026-11-01 11:00'), 10)));
        self::assertSame([], $this->entries->expired($waitlist, $this->at('2026-11-01 10:59:59'), 10));

        $lists = $this->entries->waitlistsWithExpiredOffers($now, 10);
        sort($lists);
        $expected = [$waitlist->id, $other->id];
        sort($expected);
        self::assertSame($expected, $lists);
        self::assertCount(1, $this->entries->waitlistsWithExpiredOffers($now, 1));
        self::assertSame([], $this->entries->waitlistsWithExpiredOffers($this->at('2026-11-01 10:00'), 10));
    }

    public function testTheReadsUnderTheLockSeeWhatOthersSaved(): void
    {
        $waitlist = $this->waitlist();
        $entry = $this->join($waitlist, 'account-1', '2026-11-01 09:00');
        $this->entityManager->getConnection()->executeStatement('UPDATE '.Entry::TABLE." SET status = 'left' WHERE id = ?", [$entry->id]);

        self::assertSame([], $this->entries->waiting($waitlist, 10));

        $this->entityManager->getConnection()->executeStatement('UPDATE '.Entry::TABLE." SET status = 'waiting' WHERE id = ?", [$entry->id]);
        self::assertSame($entry, $this->entries->live($waitlist, 'account-1'));
        $this->entityManager->getConnection()->executeStatement('UPDATE '.Entry::TABLE." SET status = 'left' WHERE id = ?", [$entry->id]);
        $this->entries->refresh($entry);
        self::assertSame(EntryStatus::Left, $entry->status());
        $this->entityManager->getConnection()->executeStatement('UPDATE '.Entry::TABLE." SET status = 'waiting' WHERE id = ?", [$entry->id]);
        self::assertSame([$entry], $this->entries->queued($waitlist, 10));
        self::assertSame(EntryStatus::Waiting, $entry->status());
        $this->entityManager->getConnection()->executeStatement('UPDATE '.Entry::TABLE." SET status = 'offered', offer_expires_at = '2026-11-01 10:00:00' WHERE id = ?", [$entry->id]);
        self::assertSame([$entry], $this->entries->expired($waitlist, $this->at('2026-11-01 11:00'), 10));
        self::assertSame(EntryStatus::Offered, $entry->status());
    }

    public function testTheQueriesRefreshWhatTheyReturn(): void
    {
        $waitlist = $this->waitlist();
        $entry = $this->join($waitlist, 'account-1', '2026-11-01 09:00');
        $connection = $this->entityManager->getConnection();
        $set = static fn (string $columns): int|string => $connection->executeStatement('UPDATE '.Entry::TABLE.' SET '.$columns.' WHERE id = ?', [$entry->id]);

        $set('places = 2');
        self::assertSame($entry, $this->entries->live($waitlist, 'account-1'));
        self::assertSame(2, $entry->places());

        $set('places = 3');
        self::assertSame([$entry], $this->entries->queued($waitlist, 10));
        self::assertSame(3, $entry->places());

        $set('places = 4');
        self::assertSame([$entry], $this->entries->ofWaitlist($waitlist, 10));
        self::assertSame(4, $entry->places());

        $set("places = 5, status = 'offered', offer_expires_at = '2026-11-01 10:00:00'");
        self::assertSame([$entry], $this->entries->expired($waitlist, $this->at('2026-11-01 11:00'), 10));
        self::assertSame(5, $entry->places());
    }

    public function testAParticipantListedTwiceStillHasOneLiveEntryFound(): void
    {
        $waitlist = $this->waitlist();
        $this->join($waitlist, 'account-1', '2026-11-01 09:00');
        $this->join($waitlist, 'account-1', '2026-11-01 09:01');

        self::assertInstanceOf(Entry::class, $this->entries->live($waitlist, 'account-1'));
    }

    public function testTheMappingFixesTheColumnsOfTheKeys(): void
    {
        $entry = $this->entityManager->getClassMetadata(Entry::class);
        $waitlist = $this->entityManager->getClassMetadata(Waitlist::class);

        self::assertSame([36, true], [$entry->fieldMappings['id']->length, $entry->fieldMappings['id']->options['fixed'] ?? false]);
        self::assertSame([36, true], [$waitlist->fieldMappings['id']->length, $waitlist->fieldMappings['id']->options['fixed'] ?? false]);
        self::assertSame(16, $waitlist->fieldMappings['mode']->length);
        self::assertFalse($entry->associationMappings['waitlist']->joinColumns[0]->nullable ?? true);
    }

    public function testTheLockCommitsTheWorkAndReadsTheListAgain(): void
    {
        $waitlist = $this->waitlist();
        $this->entityManager->getConnection()->executeStatement('UPDATE '.Waitlist::TABLE.' SET held_places = 5 WHERE id = ?', [$waitlist->id]);

        $held = $this->waitlists->exclusively($waitlist, function () use ($waitlist): int {
            self::assertTrue($this->entityManager->getConnection()->isTransactionActive());
            $waitlist->take(2);
            $this->waitlists->save($waitlist);

            return $waitlist->heldPlaces();
        });

        self::assertSame(3, $held);
        self::assertFalse($this->entityManager->getConnection()->isTransactionActive());
        self::assertSame(['held_places' => 3, 'claims' => 1], $this->row($waitlist));
    }

    public function testARefusalRollsTheWorkBack(): void
    {
        $waitlist = $this->waitlist();

        try {
            $this->waitlists->exclusively($waitlist, function () use ($waitlist): never {
                $waitlist->hold(4);
                $this->waitlists->save($waitlist);

                throw new RuntimeException('Refused.');
            });
        } catch (RuntimeException $runtimeException) {
            self::assertSame('Refused.', $runtimeException->getMessage());
        }

        self::assertFalse($this->entityManager->getConnection()->isTransactionActive());
        self::assertTrue($this->entityManager->isOpen());
        self::assertSame(['held_places' => 0, 'claims' => 0], $this->row($waitlist));
    }

    public function testTheSubjectIsUnique(): void
    {
        $this->waitlist();
        $this->expectException(UniqueConstraintViolationException::class);

        $this->waitlist();
    }

    private function offered(Waitlist $waitlist, string $participant, string $deadline): Entry
    {
        $entry = $this->join($waitlist, $participant, '2026-11-01 08:00');
        $entry->offer($this->at('2026-11-01 08:00'), $this->at($deadline));

        $this->entries->save($entry);

        return $entry;
    }

    private function reload(Waitlist $waitlist): Waitlist
    {
        $found = $this->waitlists->get($waitlist->id);
        self::assertInstanceOf(Waitlist::class, $found);

        return $found;
    }

    private function entry(Entry $entry): Entry
    {
        $found = $this->entries->get($entry->id);
        self::assertInstanceOf(Entry::class, $found);

        return $found;
    }

    /**
     * @return array{held_places: int, claims: int}
     */
    private function row(Waitlist $waitlist): array
    {
        /** @var array{held_places: int|string, claims: int|string} $row */
        $row = $this->entityManager->getConnection()->fetchAssociative('SELECT held_places, claims FROM '.Waitlist::TABLE.' WHERE id = ?', [$waitlist->id]);

        return ['held_places' => (int) $row['held_places'], 'claims' => (int) $row['claims']];
    }

    /**
     * @param list<Entry> $entries
     *
     * @return list<string>
     */
    private function ids(array $entries): array
    {
        return array_map(static fn (Entry $entry): string => $entry->id, $entries);
    }
}
