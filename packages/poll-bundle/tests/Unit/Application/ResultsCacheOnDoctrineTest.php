<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use FilesystemIterator;
use Override;
use Phil\Poll\BallotKind;
use Phil\Poll\Contract\CountingMethod;
use Phil\Poll\CountingMethods;
use Phil\Poll\NetPromoter;
use Phil\Poll\Rating;
use Phil\Poll\Result;
use Phil\PollBundle\Application\Exception\NotFound;
use Phil\PollBundle\Application\PollDraft;
use Phil\PollBundle\Application\Polls;
use Phil\PollBundle\Domain\Counting\Election;
use Phil\PollBundle\Domain\Counting\MeekStv;
use Phil\PollBundle\Domain\Model\Participation;
use Phil\PollBundle\Domain\Model\Poll;
use Phil\PollBundle\Domain\Model\Vote;
use Phil\PollBundle\Domain\ValueObject\PollOption;
use Phil\PollBundle\Infrastructure\Doctrine\DoctrinePollRepository;
use Phil\PollBundle\Infrastructure\Doctrine\DoctrineVoteRepository;
use Phil\PollBundle\Tests\Unit\Fixture\Policy;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * The counts kept in a real pool, over the Doctrine stores on SQLite: every action that changes a poll moves its
 * counts to a new key, read from the table even while the entity manager holds the poll, a deleted poll's counts are
 * removed, a count lives as long as the configuration says, and what is kept of an anonymous poll names no voter and
 * no receipt.
 */
#[CoversClass(Polls::class)]
final class ResultsCacheOnDoctrineTest extends TestCase
{
    private const string VOTER = 'voter-7f3a9c';

    private EntityManager $entityManager;

    private MockClock $clock;

    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        new SchemaTool($this->entityManager)->createSchema([$this->entityManager->getClassMetadata(Poll::class), $this->entityManager->getClassMetadata(Vote::class), $this->entityManager->getClassMetadata(Participation::class)]);
        $this->clock = new MockClock('2026-11-08 10:00:00 UTC');
        $this->directory = sys_get_temp_dir().'/phil-poll-bundle-cache-'.bin2hex(random_bytes(6));
    }

    #[Override]
    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $this->assertInstanceOf(SplFileInfo::class, $file);
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->directory);
    }

    public function testEveryChangeOfAPollMovesItsCountToANewRevision(): void
    {
        $cache = new FilesystemAdapter('polls', 0, $this->directory);
        $polls = $this->polls($cache);
        $boss = ResourceReference::of('user', 'boss');
        $bob = ResourceReference::of('user', 'bob');
        $poll = $polls->create(new PollDraft('Q', [PollOption::of('a', 'A'), PollOption::of('b', 'B')], abstention: true), $boss);
        $key = static fn (int $revision): string => 'phil_poll.results.'.$poll->id.'.'.$revision;

        self::assertSame(0, $polls->results($poll->id)->ballots);
        self::assertTrue($cache->hasItem($key(0)));

        $polls->cast($poll->id, $bob, ['a']);
        self::assertSame(['a'], $polls->results($poll->id)->winners());
        self::assertTrue($cache->hasItem($key(1)));

        $polls->change($poll->id, $bob, ['b']);
        self::assertSame(['b'], $polls->results($poll->id)->winners());
        self::assertTrue($cache->hasItem($key(2)));

        $polls->withdraw($poll->id, $bob);
        self::assertSame(0, $polls->results($poll->id)->ballots);
        self::assertTrue($cache->hasItem($key(3)));

        $polls->cast($poll->id, $bob, []);
        self::assertSame(1, $polls->results($poll->id)->abstentions);
        self::assertTrue($cache->hasItem($key(4)));

        $polls->reschedule($poll->id, $boss, null, new DateTimeImmutable('2026-11-09 00:00:00 UTC'));
        self::assertSame(1, $polls->results($poll->id)->ballots);
        self::assertTrue($cache->hasItem($key(5)));

        $polls->close($poll->id, $boss);
        self::assertSame(1, $polls->results($poll->id)->ballots);
        self::assertTrue($cache->hasItem($key(6)));
        self::assertFalse($cache->hasItem($key(7)));

        $polls->delete($poll->id, $boss);
        self::assertFalse($cache->hasItem($key(6)), 'The counts of a deleted poll are removed with it.');

        $this->expectException(NotFound::class);
        $polls->results($poll->id);
    }

    public function testTheRevisionIsReadFromTheTableWhileTheEntityManagerHoldsThePoll(): void
    {
        $polls = $this->polls(new ArrayAdapter());
        $poll = $polls->create(new PollDraft('Q', [PollOption::of('a', 'A'), PollOption::of('b', 'B')]), ResourceReference::of('user', 'boss'));

        foreach (['bob', 'cid', 'dee'] as $voter) {
            $polls->cast($poll->id, ResourceReference::of('user', $voter), ['a']);
            self::assertSame(['a'], $polls->results($poll->id)->winners());
        }

        $polls->change($poll->id, ResourceReference::of('user', 'bob'), ['b']);
        $polls->change($poll->id, ResourceReference::of('user', 'cid'), ['b']);

        self::assertSame(['b'], $polls->results($poll->id)->winners());
        self::assertSame($poll, $polls->get($poll->id), 'The poll is still the one the entity manager holds.');
    }

    public function testAnElectionIsRemovedWithItsPoll(): void
    {
        $cache = new FilesystemAdapter('polls', 0, $this->directory);
        $polls = $this->polls($cache);
        $boss = ResourceReference::of('user', 'boss');
        $poll = $polls->create(new PollDraft('Q', [PollOption::of('a', 'A'), PollOption::of('b', 'B'), PollOption::of('c', 'C')], BallotKind::Ranked, counting: MeekStv::NAME, seats: 2), $boss);
        $polls->cast($poll->id, ResourceReference::of('user', 'bob'), ['a', 'b']);

        self::assertSame(['a', 'b'], $polls->election($poll->id)->elected);
        self::assertTrue($cache->hasItem('phil_poll.election.'.$poll->id.'.1'));

        $polls->delete($poll->id, $boss);
        self::assertFalse($cache->hasItem('phil_poll.election.'.$poll->id.'.1'));
    }

    public function testACountLivesAsLongAsConfigured(): void
    {
        $cache = new ArrayAdapter(clock: $this->clock);
        $polls = $this->polls($cache, 30);
        $poll = $polls->create(new PollDraft('Q', [PollOption::of('a', 'A'), PollOption::of('b', 'B')]), ResourceReference::of('user', 'boss'));
        $key = 'phil_poll.results.'.$poll->id.'.0';
        $polls->results($poll->id);

        $this->clock->sleep(29);
        self::assertTrue($cache->getItem($key)->isHit());
        $this->clock->sleep(1);
        self::assertFalse($cache->getItem($key)->isHit());
        $polls->results($poll->id);
        self::assertTrue($cache->getItem($key)->isHit());
    }

    public function testTheCountsOfAScaleAreKeptWithTheirRatingAndTheirGroups(): void
    {
        $cache = new FilesystemAdapter('polls', 0, $this->directory);
        $polls = $this->polls($cache);
        $poll = $polls->create(new PollDraft('Q', PollDraft::marks(0, 10), BallotKind::Scale, counting: 'nps', anonymous: true), ResourceReference::of('user', 'boss'));
        $polls->cast($poll->id, ResourceReference::of('user', 'bob'), ['10']);
        $polls->cast($poll->id, ResourceReference::of('user', 'cid'), ['3']);

        $fresh = $polls->results($poll->id);
        $kept = $cache->getItem('phil_poll.results.'.$poll->id.'.2')->get();

        self::assertEquals($fresh, $kept);
        self::assertEquals(new Rating(2, 13), $fresh->rating);
        self::assertEquals(new NetPromoter(1, 0, 1), $fresh->netPromoter);
    }

    public function testWhatIsKeptOfAnAnonymousPollNamesNoVoterAndNoReceipt(): void
    {
        $cache = new FilesystemAdapter('polls', 0, $this->directory);
        $polls = $this->polls($cache);
        $voter = ResourceReference::of('user', self::VOTER);
        $single = $polls->create(new PollDraft('Q', [PollOption::of('a', 'A'), PollOption::of('b', 'B')], anonymous: true), ResourceReference::of('user', 'boss'));
        $ranked = $polls->create(new PollDraft('Q', [PollOption::of('a', 'A'), PollOption::of('b', 'B'), PollOption::of('c', 'C')], BallotKind::Ranked, counting: MeekStv::NAME, anonymous: true, seats: 2), ResourceReference::of('user', 'boss'));
        $receipts = [
            (string) $polls->cast($single->id, $voter, ['a'])->receipt,
            (string) $polls->cast($ranked->id, $voter, ['c', 'a'])->receipt,
        ];
        $polls->results($single->id);
        $polls->election($ranked->id);
        $polls->changeWithReceipt($single->id, $voter, $receipts[0], ['b']);
        $polls->results($single->id);

        $kept = '';

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            $this->assertInstanceOf(SplFileInfo::class, $file);
            $kept .= (string) file_get_contents($file->getPathname());
        }

        self::assertStringContainsString(Result::class, $kept, 'The counts are in the pool.');
        self::assertStringContainsString(Election::class, $kept);
        self::assertStringNotContainsString(self::VOTER, $kept);

        foreach ($receipts as $receipt) {
            self::assertStringNotContainsString($receipt, $kept);
            self::assertStringNotContainsString(hash('sha256', $receipt), $kept);
        }
    }

    private function polls(CacheInterface $cache, int $lifetime = 3600): Polls
    {
        return new Polls(new DoctrinePollRepository($this->entityManager), new DoctrineVoteRepository($this->entityManager), $this->clock, new Policy(), new CountingMethods([...$this->defaults(), new MeekStv()]), null, $cache, $lifetime);
    }

    /**
     * @return list<CountingMethod>
     */
    private function defaults(): array
    {
        $methods = CountingMethods::defaults();

        return array_map($methods->get(...), ['choices', 'instant_runoff', 'borda', 'schulze', 'scale', 'nps']);
    }
}
