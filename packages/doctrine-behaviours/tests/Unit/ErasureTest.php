<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\ORM\Cache;
use Doctrine\ORM\Decorator\EntityManagerDecorator;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use JsonException;
use LogicException;
use Override;
use Phil\DoctrineBehaviours\AllowVersionRestore;
use Phil\DoctrineBehaviours\BlameEraser;
use Phil\DoctrineBehaviours\BlameListener;
use Phil\DoctrineBehaviours\BlameSuspension;
use Phil\DoctrineBehaviours\Contract\Blameable;
use Phil\DoctrineBehaviours\Contract\Ownable;
use Phil\DoctrineBehaviours\Contract\PersonalFields;
use Phil\DoctrineBehaviours\Contract\Versionable;
use Phil\DoctrineBehaviours\Exception\InvalidVersionedState;
use Phil\DoctrineBehaviours\Exception\VersionErased;
use Phil\DoctrineBehaviours\FixedOwner;
use Phil\DoctrineBehaviours\Forget;
use Phil\DoctrineBehaviours\ListedPersonalFields;
use Phil\DoctrineBehaviours\Managers;
use Phil\DoctrineBehaviours\MappedColumns;
use Phil\DoctrineBehaviours\NoPersonalFields;
use Phil\DoctrineBehaviours\OwnerEraser;
use Phil\DoctrineBehaviours\OwnerId;
use Phil\DoctrineBehaviours\PersonalFieldPolicy;
use Phil\DoctrineBehaviours\SoftDeletedFilter;
use Phil\DoctrineBehaviours\SoftDeletes;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\CountingMiddleware;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Database;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Article;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Bike;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Contact;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Document;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Ledger;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Manual;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Post;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Profile;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Report;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Skate;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Tracked;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\MutableBlame;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Registry;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\ReversingCipher;
use Phil\DoctrineBehaviours\Version\RecordVersion;
use Phil\DoctrineBehaviours\VersionEraser;
use Phil\DoctrineBehaviours\Versions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;

/**
 * An erasure leaves nothing behind: the blame and the owner in every entity manager, once per connection and in one
 * transaction each, with no stale entity or cache entry; the personal fields of the frozen versions, kept out, sealed
 * or redacted, and never put back; no name written by a flush during the erasure; a soft-deletable row deleted for
 * good when asked.
 */
#[CoversClass(BlameEraser::class)]
#[CoversClass(OwnerEraser::class)]
#[CoversClass(Managers::class)]
#[CoversClass(MappedColumns::class)]
#[CoversClass(Forget::class)]
#[CoversClass(BlameSuspension::class)]
#[CoversClass(BlameListener::class)]
#[CoversClass(SoftDeletes::class)]
#[CoversClass(Versions::class)]
#[CoversClass(VersionEraser::class)]
#[CoversClass(VersionErased::class)]
#[CoversClass(InvalidVersionedState::class)]
#[CoversClass(ListedPersonalFields::class)]
#[CoversClass(NoPersonalFields::class)]
#[CoversClass(PersonalFieldPolicy::class)]
#[CoversClass(RecordVersion::class)]
final class ErasureTest extends TestCase
{
    private const array PERSONAL = [Profile::class => ['name', 'email']];

    private MockClock $clock;

    private MutableBlame $blame;

    private EntityManager $manager;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-11-19 08:00', new DateTimeZone('America/Toronto')));
        $this->blame = new MutableBlame('ada');
        $this->manager = Database::entityManager($this->clock, $this->blame, false, new FixedOwner(new OwnerId('ada')));
    }

    // Every manager, once per connection

    public function testTheErasersReachEveryManagerOncePerConnection(): void
    {
        $sharing = new EntityManager($this->manager->getConnection(), $this->manager->getConfiguration());
        $other = Database::entityManager($this->clock, $this->blame, false, new FixedOwner(new OwnerId('ada')));
        $this->manager->persist(new Article('a', 'Titre'));
        $this->manager->persist(new Bike('b'));
        $this->manager->flush();

        $other->persist(new Article('x', 'Titre'));
        $other->persist(new Article('y', 'Titre'));
        $other->persist(new Bike('z'));
        $other->flush();

        $registry = new Registry(['default' => $this->manager, 'sharing' => $sharing, 'other' => $other]);

        $blame = new BlameEraser($registry);
        self::assertSame(['article' => 3], $blame->find('ada'), 'one count per connection, the shared one once');
        self::assertCount(1, array_keys($blame->tables(), 'article', true));
        self::assertSame(6, $blame->replace('ada', 'former'), 'both columns of three rows, none twice');
        self::assertSame([], $blame->find('ada'));
        self::assertSame(['article' => 3], $blame->find('former'));

        $owner = new OwnerEraser($registry);
        self::assertSame(['bike' => 2], $owner->find(new OwnerId('ada')));
        self::assertSame(2, $owner->replace(new OwnerId('ada')));
        self::assertSame([], $owner->find(new OwnerId('ada')));
        self::assertCount(1, array_keys($owner->tables(), 'bike', true));
        self::assertSame(['default', 'sharing', 'other'], array_keys($registry->getManagers()));
    }

    public function testAConnectionIsWrittenInOneTransaction(): void
    {
        $other = Database::entityManager($this->clock, $this->blame, false, new FixedOwner(new OwnerId('ada')));
        $this->manager->persist(new Article('a', 'Titre'));
        $this->manager->flush();

        $other->persist(new Article('x', 'Titre'));
        $other->flush();
        $other->getConnection()->executeStatement('DROP TABLE post');

        try {
            new BlameEraser(new Registry(['default' => $this->manager, 'other' => $other]))->replace('ada');
            self::fail('Replaced.');
        } catch (TableNotFoundException) {
        }

        self::assertNull($this->manager->getConnection()->fetchOne("SELECT created_by FROM article WHERE id = 'a'"), 'the first connection committed');
        self::assertSame('ada', $other->getConnection()->fetchOne("SELECT created_by FROM article WHERE id = 'x'"), 'the second rolled back, its earlier table too');
        self::assertFalse($other->getConnection()->isTransactionActive());
    }

    public function testAnOwnerIsReplacedInOneTransactionPerConnection(): void
    {
        $this->manager->persist(new Bike('b'));
        $this->manager->flush();
        $this->manager->getConnection()->executeStatement('DROP TABLE zz_aircraft');

        try {
            new OwnerEraser($this->manager)->replace(new OwnerId('ada'));
            self::fail('Replaced.');
        } catch (TableNotFoundException) {
        }

        self::assertSame('ada', $this->manager->getConnection()->fetchOne("SELECT owner_id FROM bike WHERE id = 'b'"));
    }

    public function testTheManagedEntitiesThatHoldTheOldValueAreDetached(): void
    {
        $sharing = new EntityManager($this->manager->getConnection(), $this->manager->getConfiguration());
        $mine = new Article('a', 'Titre');
        $theirs = new Article('b', 'Titre');
        $theirs->blameCreated('grace');

        $bike = new Bike('c');
        $this->manager->persist($mine);
        $this->manager->persist($bike);

        $this->blame->who = 'grace';
        $this->manager->persist($theirs);
        $this->manager->flush();

        $alsoMine = $sharing->find(Article::class, 'a');
        self::assertInstanceOf(Article::class, $alsoMine);

        new BlameEraser(new Registry(['default' => $this->manager, 'sharing' => $sharing]))->replace('ada', 'former');

        self::assertFalse($this->manager->contains($mine));
        self::assertFalse($sharing->contains($alsoMine), 'in every manager on the connection');
        self::assertTrue($this->manager->contains($theirs));
        self::assertTrue($this->manager->contains($bike));
        self::assertSame('former', $this->manager->find(Article::class, 'a')?->createdBy());

        new OwnerEraser($this->manager)->replace(new OwnerId('ada'), new OwnerId('former'));

        self::assertFalse($this->manager->contains($bike));
        self::assertTrue($this->manager->contains($theirs));
        self::assertSame('former', $this->manager->find(Bike::class, 'c')?->ownerId()?->value);
    }

    public function testTheCacheRegionsOfTheChangedClassesAreEvicted(): void
    {
        $evicted = [];
        $cache = self::createStub(Cache::class);
        $cache->method('evictEntityRegion')->willReturnCallback(static function (string $class) use (&$evicted): void {
            $evicted[] = $class;
        });
        $cached = new class($this->manager, $cache) extends EntityManagerDecorator {
            public function __construct(EntityManagerInterface $wrapped, private readonly Cache $cache)
            {
                parent::__construct($wrapped);
            }

            #[Override]
            public function getCache(): Cache
            {
                return $this->cache;
            }
        };

        new BlameEraser($cached)->replace('ada');
        self::assertContains(Article::class, $evicted);
        self::assertNotContains(Bike::class, $evicted);

        $evicted = [];
        new OwnerEraser($cached)->replace(new OwnerId('ada'));
        self::assertContains(Bike::class, $evicted);
        self::assertNotContains(Article::class, $evicted);
    }

    public function testAClassMappedOnATableOfItsOwnIsListedThere(): void
    {
        $tables = MappedColumns::of($this->manager, Profile::class, ['name']);

        self::assertSame(['profile' => ['columns' => ['name'], 'classes' => [Profile::class]]], $tables);
    }

    // The blame suspended during an erasure

    public function testNoNameIsWrittenWhileTheBlameIsSuspended(): void
    {
        $suspension = new BlameSuspension();
        $manager = $this->managerWithSuspension($suspension);
        $article = new Article('a', 'Titre');
        $manager->persist($article);
        $manager->flush();
        self::assertSame('ada', $article->updatedBy());

        $returned = $suspension->during(static function () use ($manager, $article, $suspension): string {
            self::assertTrue($suspension->isSuspended());
            $manager->getConnection()->executeStatement("UPDATE article SET updated_by = NULL WHERE id = 'a'");
            $article->blameUpdated('erased');
            $manager->flush();
            $article->title = 'Changé';
            $fresh = new Article('b', 'Titre');
            $manager->persist($fresh);
            $manager->flush();
            self::assertNull($fresh->createdBy());

            return 'done';
        });

        self::assertSame('done', $returned);
        self::assertFalse($suspension->isSuspended());
        self::assertSame('erased', $manager->getConnection()->fetchOne("SELECT updated_by FROM article WHERE id = 'a'"));

        $article->title = 'Encore';
        $manager->flush();
        self::assertSame('ada', $article->updatedBy(), 'written again once the erasure ends');
    }

    public function testASuspensionEndsWhenItsWorkThrowsAndNests(): void
    {
        $suspension = new BlameSuspension();

        try {
            $suspension->during(static function () use ($suspension): never {
                $suspension->during(static fn (): null => null);
                self::assertTrue($suspension->isSuspended(), 'still within the outer span');

                throw new RuntimeException('failed');
            });
        } catch (RuntimeException) {
        }

        self::assertFalse($suspension->isSuspended());

        $suspension->during(static function () use ($suspension): void {
            $suspension->reset();
            self::assertFalse($suspension->isSuspended());
        });
        self::assertFalse($suspension->isSuspended(), 'never below zero');
        self::assertTrue($suspension->during($suspension->isSuspended(...)));
    }

    // Soft delete

    public function testAFirstRemovalKeepsTheWholeRowAndPurgeDeletesIt(): void
    {
        $card = new Article('c', 'Titre');
        $this->manager->persist($card);
        $this->manager->flush();

        $before = $this->manager->getConnection()->fetchAssociative("SELECT * FROM article WHERE id = 'c'");

        $this->manager->remove($card);
        $this->manager->flush();

        $after = $this->manager->getConnection()->fetchAssociative("SELECT * FROM article WHERE id = 'c'");
        self::assertIsArray($before);
        self::assertIsArray($after);
        self::assertNotNull($after['deleted_at']);
        unset($before['deleted_at'], $after['deleted_at']);
        self::assertSame($before, $after, 'every other column as it was');

        new SoftDeletes($this->manager, $this->clock)->purge($card);
        self::assertFalse($this->manager->getConnection()->fetchOne("SELECT id FROM article WHERE id = 'c'"));
    }

    public function testPurgeDeletesARowNeverSoftDeletedThroughItsManager(): void
    {
        $card = new Article('c', 'Titre');
        $other = new Article('d', 'Titre');
        $this->manager->persist($card);
        $this->manager->persist($other);
        $this->manager->flush();

        new SoftDeletes(new Registry(['default' => $this->manager]), $this->clock)->purge($card);

        self::assertSame(['d'], $this->manager->getConnection()->fetchFirstColumn('SELECT id FROM article'));
        self::assertNull($other->deletedAt());
        self::assertSame('2026-11-19T08:00:00-05:00', $card->deletedAt()?->format('c'));
    }

    public function testPurgeTakesAManagerOfTheClass(): void
    {
        $registry = self::createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('No entity manager manages '.Article::class.'.');
        new SoftDeletes($registry, $this->clock)->purge(new Article('c', 'Titre'));
    }

    // Versions: what a version keeps of the personal fields

    public function testAVersionLeavesThePersonalFieldsOutByDefault(): void
    {
        $versions = new Versions($this->manager, $this->clock, new AllowVersionRestore(), new ListedPersonalFields(self::PERSONAL));
        $profile = new Profile('p', 'Ada', 'ada@example.org', 'Mathématicienne');
        $versions->freeze($profile);

        self::assertSame(['bio' => 'Mathématicienne'], $this->rawState('p', 1));

        $profile->bio = 'Analyste';
        $profile->name = 'Ada L.';

        $this->manager->flush();
        $versions->restore($profile, 1);

        self::assertSame(['Ada L.', 'ada@example.org', 'Mathématicienne'], [$profile->name, $profile->email, $profile->bio], 'the record keeps its personal values');
    }

    public function testAVersionKeepsThePersonalFieldsWhenAsked(): void
    {
        $versions = new Versions($this->manager, $this->clock, new AllowVersionRestore(), new ListedPersonalFields(self::PERSONAL), PersonalFieldPolicy::Keep);
        $versions->freeze(new Profile('p', 'Ada', 'ada@example.org', 'Bio'));

        self::assertSame(['name' => 'Ada', 'email' => 'ada@example.org', 'bio' => 'Bio'], $this->rawState('p', 1));
    }

    public function testAVersionSealsThePersonalFieldsAndOpensThemGoingBack(): void
    {
        $versions = new Versions($this->manager, $this->clock, new AllowVersionRestore(), new ListedPersonalFields(self::PERSONAL), PersonalFieldPolicy::Encrypt, new ReversingCipher());
        $profile = new Profile('p', 'Ada', 'ada@example.org', 'Bio');
        $versions->freeze($profile);

        $state = $this->rawState('p', 1);
        self::assertSame('Bio', $state['bio']);
        self::assertSame(['@sealed' => strrev('phil_record_version|profile|p|1|name#"Ada"')], $state['name']);
        self::assertStringNotContainsString('ada@example.org', (string) json_encode($state));

        $profile->name = 'Grace';
        $profile->email = 'grace@example.org';

        $this->manager->flush();
        $versions->restore($profile, 1);
        self::assertSame(['Ada', 'ada@example.org'], [$profile->name, $profile->email]);

        $keeping = new Versions($this->manager, $this->clock, new AllowVersionRestore(), new ListedPersonalFields(self::PERSONAL), PersonalFieldPolicy::Keep);

        try {
            $keeping->restore($profile, 1);
            self::fail('Opened.');
        } catch (InvalidVersionedState $invalidVersionedState) {
            self::assertSame('A version of '.Profile::class.' holds "name" sealed, and no VersionCipher was given to open it.', $invalidVersionedState->getMessage());
        }
    }

    public function testSealingTakesACipher(): void
    {
        $this->expectException(InvalidVersionedState::class);
        $this->expectExceptionMessageIsOrContains('PersonalFieldPolicy::Encrypt seals the personal fields of the versions with a VersionCipher, and none was given.');
        new Versions($this->manager, $this->clock, new AllowVersionRestore(), new NoPersonalFields(), PersonalFieldPolicy::Encrypt);
    }

    public function testNoPersonalFieldsKeepsTheWholeState(): void
    {
        $versions = new Versions($this->manager, $this->clock, new AllowVersionRestore());
        $versions->freeze(new Profile('p', 'Ada', 'ada@example.org', 'Bio'));

        self::assertSame(['name' => 'Ada', 'email' => 'ada@example.org', 'bio' => 'Bio'], $this->rawState('p', 1));
        self::assertSame([], new NoPersonalFields()->of(Profile::class));
        self::assertSame(['name', 'email'], new ListedPersonalFields([...self::PERSONAL, Versionable::class => ['email']])->of(Profile::class));
        self::assertSame([], new ListedPersonalFields(self::PERSONAL)->of(Manual::class));
    }

    // Versions: redacted by an erasure

    public function testTheVersionsOfAPersonAreRedactedInTheRawRow(): void
    {
        $versions = $this->keepingVersions();
        $owned = new Profile('owned', 'Ada', 'ada@example.org', 'Bio');
        $versions->freeze($owned);
        $owned->bio = 'Deux';
        $versions->freeze($owned);
        $this->blame->who = 'grace';
        $blamedOnly = new Profile('blamed', 'Grace', 'grace@example.org', 'Autre');
        $blamedOnly->claimOwner(new OwnerId('grace'));

        $this->manager->persist($blamedOnly);
        $this->manager->flush();

        $this->manager->getConnection()->executeStatement("UPDATE profile SET updated_by = 'ada' WHERE id = 'blamed'");
        $versions->freeze($blamedOnly);
        $frozenByAda = new Profile('frozen', 'Eve', 'eve@example.org', 'Encore');
        $frozenByAda->claimOwner(new OwnerId('eve'));

        $this->manager->persist($frozenByAda);
        $this->manager->flush();

        $this->blame->who = 'ada';
        $versions->freeze($frozenByAda);
        $this->blame->who = 'grace';
        $untouched = new Profile('untouched', 'Bob', 'bob@example.org', 'Rien');
        $untouched->claimOwner(new OwnerId('bob'));

        $versions->freeze($untouched);
        $loaded = $versions->get($owned, 1);
        self::assertInstanceOf(RecordVersion::class, $loaded);

        $eraser = $this->eraser();
        self::assertSame(4, $eraser->find('ada'));
        self::assertSame(['phil_record_version'], $eraser->tables());
        $this->clock->modify('+1 hour');
        self::assertSame(4, $eraser->redact('ada'));

        foreach ([['owned', 1, 'Bio'], ['owned', 2, 'Deux'], ['blamed', 1, 'Autre'], ['frozen', 1, 'Encore']] as [$id, $number, $bio]) {
            self::assertSame(['bio' => $bio], $this->rawState($id, $number), $id);
            self::assertSame('2026-11-19 14:00:00', $this->manager->getConnection()->fetchOne('SELECT redacted_at FROM phil_record_version WHERE subject_id = ? AND version_number = ?', [$id, $number]));
        }

        self::assertSame(['name' => 'Bob', 'email' => 'bob@example.org', 'bio' => 'Rien'], $this->rawState('untouched', 1));
        self::assertNull($this->manager->getConnection()->fetchOne("SELECT redacted_at FROM phil_record_version WHERE subject_id = 'untouched'"));
        self::assertFalse($this->manager->contains($loaded), 'a redacted version loaded before is detached');
        $reread = $versions->get($owned, 1);
        self::assertInstanceOf(RecordVersion::class, $reread);
        self::assertTrue($reread->isRedacted());
        self::assertSame('2026-11-19T14:00:00+00:00', $reread->redactedAt()?->format('c'));
        self::assertSame(0, $eraser->find('ada'));
        self::assertSame(0, $eraser->redact('ada'), 'a second run finds nothing left');
    }

    public function testGoingBackToARedactedVersionIsRefused(): void
    {
        $versions = $this->keepingVersions();
        $profile = new Profile('p', 'Ada', 'ada@example.org', 'Bio');
        $versions->freeze($profile);
        $this->eraser()->redact('ada');
        $profile->bio = 'Après';
        $this->manager->flush();

        try {
            $versions->restore($profile, 1);
            self::fail('Restored.');
        } catch (VersionErased $versionErased) {
            self::assertSame('Version 1 of '.Profile::class.' "p" was redacted by an erasure: going back to it is refused.', $versionErased->getMessage());
        }

        self::assertSame('Après', $profile->bio);
    }

    public function testGoingBackToAVersionOlderThanTheErasureIsRefused(): void
    {
        $versions = $this->keepingVersions();
        $profile = new Profile('p', 'Ada', 'ada@example.org', 'Bio');
        $versions->freeze($profile);
        $profile->bio = 'Deux';
        $versions->freeze($profile);
        $profile->bio = 'Trois';
        $versions->freeze($profile);
        $this->manager->getConnection()->executeStatement("UPDATE phil_record_version SET redacted_at = '2026-11-19 13:00:00' WHERE version_number = 2");
        $this->manager->clear();
        $profile = $this->manager->find(Profile::class, 'p');
        self::assertInstanceOf(Profile::class, $profile);

        try {
            $versions->restore($profile, 1);
            self::fail('Restored.');
        } catch (VersionErased $versionErased) {
            self::assertSame(Profile::class.' "p" was erased after version 1: going back to it is refused.', $versionErased->getMessage());
        }

        $versions->restore($profile, 3);
        self::assertSame('Trois', $profile->bio, 'a version after the erasure goes back');
    }

    public function testOneRecordIsRedactedWhateverItsFields(): void
    {
        $versions = $this->keepingVersions();
        $manual = new Manual('m', 'Un');
        $versions->freeze($manual);
        $manual->title = 'Deux';
        $versions->freeze($manual);
        $versions->freeze(new Manual('n', 'Autre'));

        self::assertSame(2, $this->eraser()->redactRecord($manual));
        self::assertSame(0, $this->eraser()->redactRecord($manual));
        self::assertSame(['title' => 'Un', 'body' => ''], $this->rawState('m', 1), 'no personal field to take out');
        self::assertEquals(2, $this->manager->getConnection()->fetchOne("SELECT COUNT(*) FROM phil_record_version WHERE subject_id = 'm' AND redacted_at IS NOT NULL"));
        self::assertNull($this->manager->getConnection()->fetchOne("SELECT redacted_at FROM phil_record_version WHERE subject_id = 'n'"));

        $this->expectException(VersionErased::class);
        $versions->restore($manual, 1);
    }

    public function testTheVersionsWrittenBeforeLoseTheirPlainPersonalFields(): void
    {
        $keeping = $this->keepingVersions();
        $keeping->freeze(new Profile('p', 'Ada', 'ada@example.org', 'Bio'));
        $keeping->freeze(new Profile('q', 'Bob', 'bob@example.org', 'Rien'));

        $sealing = new Versions($this->manager, $this->clock, new AllowVersionRestore(), new ListedPersonalFields(self::PERSONAL), PersonalFieldPolicy::Encrypt, new ReversingCipher());
        $sealing->freeze(new Profile('s', 'Eve', 'eve@example.org', 'Scellé'));

        $keeping->freeze(new Manual('m', 'Un'));
        $sealed = $this->rawState('s', 1);

        self::assertSame(2, $this->eraser()->stripPersonalFields(true));
        self::assertSame(['name' => 'Ada', 'email' => 'ada@example.org', 'bio' => 'Bio'], $this->rawState('p', 1), 'a dry run changes nothing');
        self::assertSame(2, $this->eraser()->stripPersonalFields());
        self::assertSame(['bio' => 'Bio'], $this->rawState('p', 1));
        self::assertSame(['bio' => 'Rien'], $this->rawState('q', 1));
        self::assertSame($sealed, $this->rawState('s', 1), 'a sealed value stays');
        self::assertSame(['title' => 'Un', 'body' => ''], $this->rawState('m', 1));
        self::assertEquals(0, $this->manager->getConnection()->fetchOne('SELECT COUNT(*) FROM phil_record_version WHERE redacted_at IS NOT NULL'));
        self::assertSame(0, $this->eraser()->stripPersonalFields());
    }

    public function testStrippingGoesThroughEveryBatch(): void
    {
        $connection = $this->manager->getConnection();
        $connection->transactional(static function () use ($connection): void {
            for ($i = 0; $i < 501; ++$i) {
                $connection->insert('phil_record_version', ['subject_table' => 'profile', 'subject_id' => 'p'.$i, 'version_number' => 1, 'state' => '{"name":"Ada","email":"ada@example.org","bio":"Bio"}', 'frozen_at' => '2026-11-19 13:00:00']);
            }
        });

        self::assertSame(501, $this->eraser()->stripPersonalFields());
        self::assertSame(['bio' => 'Bio'], $this->rawState('p500', 1));
    }

    public function testAManagerWithoutVersionsHasNoStore(): void
    {
        $eraser = new VersionEraser(new Registry(['default' => $this->manager, 'sharing' => new EntityManager($this->manager->getConnection(), $this->manager->getConfiguration())]), new ListedPersonalFields(self::PERSONAL), $this->clock);
        $this->keepingVersions()->freeze(new Profile('p', 'Ada', 'ada@example.org', 'Bio'));

        self::assertSame(1, $eraser->find('ada'), 'the table once on the shared connection');
        self::assertSame(['phil_record_version'], $eraser->tables());
        self::assertSame(0, new VersionEraser($this->manager, new NoPersonalFields(), $this->clock)->find('ada'));
        self::assertSame(0, new VersionEraser($this->manager, new NoPersonalFields(), $this->clock)->stripPersonalFields());
    }

    // Orders, shapes and links the erasers keep

    public function testASuspensionStaysWhileAnOuterOneRunsAndResetEndsEvery(): void
    {
        $suspension = new BlameSuspension();
        $inside = $suspension->during(static function () use ($suspension): bool {
            $suspension->during(static fn (): null => null);

            return $suspension->isSuspended();
        });
        self::assertTrue($inside, 'the inner span ended, the outer one did not');
        self::assertFalse($suspension->isSuspended());

        $suspension->reset();
        self::assertTrue($suspension->during(static fn (): bool => $suspension->isSuspended()), 'a span begun after a reset suspends');
    }

    public function testListedPersonalFieldsAreAListWhateverTheRepeats(): void
    {
        $fields = new ListedPersonalFields([Profile::class => ['email', 'name'], Versionable::class => ['email', 'bio']]);

        self::assertSame(['email', 'name', 'bio'], $fields->of(Profile::class));
    }

    public function testTheManagersAreTheEntityManagersOfTheRegistryAsAList(): void
    {
        $sharing = new EntityManager($this->manager->getConnection(), $this->manager->getConfiguration());
        $registry = new Registry(['plain' => self::createStub(ObjectManager::class), 'default' => $this->manager, 'sharing' => $sharing]);

        self::assertSame([$this->manager, $sharing], Managers::all($registry));
        self::assertSame([$this->manager], Managers::all($this->manager));
    }

    public function testTheTablesOfTwoManagersOnOneConnectionKeepTheClassesOfBoth(): void
    {
        $sharing = new EntityManager($this->manager->getConnection(), $this->manager->getConfiguration());
        $other = Database::entityManager($this->clock, $this->blame);
        $groups = Managers::tables(new Registry(['default' => $this->manager, 'sharing' => $sharing, 'other' => $other]), Blameable::class, ['createdBy', 'updatedBy']);

        self::assertSame([0, 1], array_keys($groups), 'one group per connection, as a list');
        self::assertSame([[$this->manager, Article::class], [$sharing, Article::class]], $groups[0]['tables']['article']['classes']);
        self::assertSame([[$other, Article::class]], $groups[1]['tables']['article']['classes']);

        $ofArticle = array_values(array_filter(Managers::classes($groups[0]), static fn (array $mapped): bool => Article::class === $mapped[1]));
        self::assertSame([[$this->manager, Article::class], [$sharing, Article::class]], $ofArticle, 'every class of a table, not the first');
    }

    public function testTheTableNamesAreSortedAndEachOnce(): void
    {
        $connection = $this->manager->getConnection();
        $first = ['connection' => $connection, 'managers' => [], 'tables' => ['b' => ['columns' => [], 'classes' => []], 'a' => ['columns' => [], 'classes' => []]]];
        $second = ['connection' => $connection, 'managers' => [], 'tables' => ['a' => ['columns' => [], 'classes' => []], 'c' => ['columns' => [], 'classes' => []]]];

        self::assertSame(['a', 'b', 'c'], Managers::tableNames([$first, $second]));
    }

    public function testTheFindsAreSortedByTableAcrossConnections(): void
    {
        $other = Database::entityManager($this->clock, $this->blame, false, new FixedOwner(new OwnerId('ada')));
        $this->manager->persist(new Post('p'));
        $this->manager->persist(new Skate('s'));
        $this->manager->persist(new Profile('q', 'Q', 'q@example.org', 'Q'));
        $this->manager->flush();

        $other->persist(new Article('a', 'Titre'));
        $other->persist(new Bike('k'));
        $other->flush();

        $registry = new Registry(['default' => $this->manager, 'other' => $other]);

        self::assertSame(['article' => 1, 'post' => 1, 'profile' => 1], new BlameEraser($registry)->find('ada'));
        self::assertSame(['bike' => 1, 'profile' => 1], new OwnerEraser($registry)->find(new OwnerId('ada')));
    }

    public function testTheMappedTablesAreSortedWhateverTheOrderOfTheClasses(): void
    {
        $tables = array_keys(MappedColumns::of($this->manager, Ownable::class, [Ownable::OWNER_FIELD]));
        $sorted = $tables;
        sort($sorted);

        self::assertContains('zz_aircraft', $tables);
        self::assertSame($sorted, $tables);
    }

    public function testTheVersionsOfEveryConnectionAreFoundAndRedacted(): void
    {
        $sharing = new EntityManager($this->manager->getConnection(), $this->manager->getConfiguration());
        $other = Database::entityManager($this->clock, $this->blame, false, new FixedOwner(new OwnerId('ada')));
        $keeping = $this->keepingVersions();
        $keeping->freeze(new Profile('p1', 'Ada', 'ada@example.org', 'Un'));
        $keeping->freeze(new Profile('p2', 'Ada', 'ada@example.org', 'Deux'));
        new Versions($other, $this->clock, new AllowVersionRestore(), new ListedPersonalFields(self::PERSONAL), PersonalFieldPolicy::Keep)->freeze(new Profile('p3', 'Ada', 'ada@example.org', 'Trois'));

        $eraser = new VersionEraser(new Registry(['default' => $this->manager, 'sharing' => $sharing, 'other' => $other]), new ListedPersonalFields(self::PERSONAL), $this->clock);

        self::assertSame(3, $eraser->find('ada'), 'the shared connection once, then the other');
        self::assertSame(3, $eraser->redact('ada'));
        self::assertSame(0, $eraser->find('ada'));
        self::assertSame('{"bio":"Trois"}', $other->getConnection()->fetchOne('SELECT state FROM phil_record_version'));
    }

    public function testTheTablesOfTheVersionsAreSortedEachOnceWhateverTheConnection(): void
    {
        $events = new EventManager();
        $events->addEventListener([Events::loadClassMetadata], new class {
            public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
            {
                if (RecordVersion::class === $event->getClassMetadata()->getName()) {
                    $event->getClassMetadata()->setPrimaryTable(['name' => 'aaa_phil_record_version']);
                }
            }
        });
        $prefixed = new EntityManager($this->manager->getConnection(), $this->manager->getConfiguration(), $events);
        $other = Database::entityManager($this->clock, $this->blame);

        $eraser = new VersionEraser(new Registry(['default' => $this->manager, 'prefixed' => $prefixed, 'other' => $other]), new ListedPersonalFields(self::PERSONAL), $this->clock);

        self::assertSame(['aaa_phil_record_version', 'phil_record_version'], $eraser->tables(), 'one store per connection and table, in order');
    }

    public function testAStoreWithNothingPersonalIsNotReadAndTheNextOneStillRuns(): void
    {
        $queries = new CountingMiddleware();
        $versionsOnly = Database::versionsOnly($queries);
        $this->keepingVersions()->freeze(new Profile('p', 'Ada', 'ada@example.org', 'Bio'));
        $eraser = new VersionEraser(new Registry(['versions' => $versionsOnly, 'default' => $this->manager]), new ListedPersonalFields(self::PERSONAL), $this->clock);

        self::assertSame(1, $eraser->find('ada'));
        self::assertSame([], $queries->selects(), 'a store with no personal field is not read');
        self::assertSame(1, $eraser->stripPersonalFields());
        self::assertSame([], $queries->selects());
        self::assertSame(['bio' => 'Bio'], $this->rawState('p', 1));
    }

    public function testTheVersionsOfTheRecordsABlameNamesAreFoundByEitherColumn(): void
    {
        $versions = $this->keepingVersions();
        $this->blame->who = 'ada';
        $created = new Profile('created', 'A', 'a@example.org', 'Un');
        $created->claimOwner(new OwnerId('x'));

        $this->manager->persist($created);
        $this->manager->flush();

        $this->blame->who = 'grace';
        $updated = new Profile('updated', 'B', 'b@example.org', 'Deux');
        $updated->claimOwner(new OwnerId('x'));

        $this->manager->persist($updated);
        $this->manager->flush();
        $this->manager->getConnection()->executeStatement("UPDATE profile SET updated_by = 'ada' WHERE id = 'updated'");
        $untouched = new Profile('untouched', 'C', 'c@example.org', 'Trois');
        $untouched->claimOwner(new OwnerId('x'));

        $this->manager->persist($untouched);
        $this->manager->flush();

        foreach ([$created, $updated, $untouched] as $profile) {
            $versions->freeze($profile);
        }

        self::assertSame(2, $this->eraser()->find('ada'));
    }

    public function testTheVersionsOfEveryLinkAreFoundTogether(): void
    {
        $versions = $this->keepingVersions();
        $this->blame->who = 'ada';
        $frozenByAda = new Profile('by-ada', 'A', 'a@example.org', 'Un');
        $frozenByAda->claimOwner(new OwnerId('x'));

        $this->manager->persist($frozenByAda);
        $this->manager->flush();

        $this->blame->who = 'grace';
        $owned = new Profile('owned', 'B', 'b@example.org', 'Deux');
        $blamed = new Profile('blamed', 'C', 'c@example.org', 'Trois');
        $blamed->claimOwner(new OwnerId('x'));

        $this->manager->persist($owned);
        $this->manager->persist($blamed);
        $this->manager->flush();
        $this->manager->getConnection()->executeStatement("UPDATE profile SET created_by = 'grace', updated_by = 'grace' WHERE id = 'by-ada'");
        $this->manager->getConnection()->executeStatement("UPDATE profile SET updated_by = 'ada' WHERE id = 'blamed'");
        $this->blame->who = 'ada';
        $versions->freeze($frozenByAda);
        $this->blame->who = 'grace';
        $versions->freeze($owned);
        $versions->freeze($blamed);

        self::assertSame(3, $this->eraser()->find('ada'), 'frozen by the person, owned by them, blamed to them');
        self::assertSame(3, $this->eraser()->redact('ada'));
    }

    public function testNoLinkIsMadeToAnOwnerOrABlameTheMappingDoesNotHold(): void
    {
        $this->blame->who = 'grace';
        $this->keepingVersions()->freeze(new Ledger('l', 'Note'));
        $this->manager->getConnection()->insert('phil_record_version', ['subject_table' => 'article', 'subject_id' => 'a', 'version_number' => 1, 'state' => '{"title":"Titre"}', 'frozen_at' => '2026-11-19 13:00:00', 'created_by' => 'ada']);
        $eraser = new VersionEraser($this->manager, new ListedPersonalFields([Versionable::class => ['note'], Blameable::class => ['title']]), $this->clock);

        self::assertSame(0, $eraser->find('ada'), 'a class owned and blamed by hand has no field to follow, and a class that is not versionable has no versions');
    }

    public function testTheFieldsOfTheClassesOfOneTableAreMergedAndAMappedSuperclassHasNoTable(): void
    {
        $this->blame->who = 'ada';
        $this->keepingVersions()->freeze(new Report('r'));
        $personal = new class implements PersonalFields {
            public function of(string $class): array
            {
                return match ($class) {
                    Document::class => ['id'],
                    Report::class => ['x'],
                    Tracked::class => ['note'],
                    default => [],
                };
            }
        };
        $eraser = new VersionEraser($this->manager, $personal, $this->clock);

        self::assertSame(1, $eraser->find('ada'));
        self::assertSame(1, $eraser->redact('ada'));
        self::assertSame([], $this->rawState('r', 1), 'the fields of the parent and of the child are taken out');
    }

    public function testTheLinksFindTheVersionsOfRecordsWhoseIdentifierIsANumber(): void
    {
        $this->manager->persist($contact = new Contact('514'));
        $this->manager->flush();

        $this->blame->who = 'grace';
        $this->keepingVersions()->freeze($contact);
        $eraser = new VersionEraser($this->manager, new ListedPersonalFields([Contact::class => ['phone']]), $this->clock);

        self::assertSame(1, $eraser->find('ada'), 'the owner of a numbered contact');
        self::assertSame(1, $eraser->redact('ada'));
        self::assertSame(0, $eraser->find('ada'));
        self::assertSame('[]', $this->manager->getConnection()->fetchOne("SELECT state FROM phil_record_version WHERE subject_table = 'contact'"));
    }

    public function testRedactingTakesTheSealedFieldsAndStrippingKeepsThem(): void
    {
        $cipher = new ReversingCipher();
        $sealing = new Versions($this->manager, $this->clock, new AllowVersionRestore(), new ListedPersonalFields(self::PERSONAL), PersonalFieldPolicy::Encrypt, $cipher);
        $sealing->freeze(new Profile('s', 'Eve', 'eve@example.org', 'Scellé'));
        $sealing->freeze(new Profile('t', 'Bob', 'bob@example.org', 'Scellé'));

        $connection = $this->manager->getConnection();
        $state = $this->rawState('s', 1);
        $state['email'] = 'eve@example.org';
        $connection->update('phil_record_version', ['state' => json_encode($state, \JSON_THROW_ON_ERROR)], ['subject_id' => 's']);
        $name = $state['name'];

        self::assertSame(1, $this->eraser()->stripPersonalFields());
        self::assertSame(['name' => $name, 'bio' => 'Scellé'], $this->rawState('s', 1), 'the plain field goes, the sealed one stays');
        self::assertSame(2, $this->eraser()->redact('ada'));
        self::assertSame(['bio' => 'Scellé'], $this->rawState('s', 1), 'a redaction takes the sealed one too');
        self::assertSame(['bio' => 'Scellé'], $this->rawState('t', 1));
    }

    public function testASingleRecordIsRedactedOfItsPersonalFieldsToo(): void
    {
        $versions = $this->keepingVersions();
        $profile = new Profile('p', 'Ada', 'ada@example.org', 'Bio');
        $versions->freeze($profile);

        self::assertSame(1, $this->eraser()->redactRecord($profile));
        self::assertSame(['bio' => 'Bio'], $this->rawState('p', 1));
    }

    // Versions: sealing and opening

    public function testACipherSealsOnlyWhenTheSealingPolicyAsksForIt(): void
    {
        $excluding = new Versions($this->manager, $this->clock, new AllowVersionRestore(), new ListedPersonalFields(self::PERSONAL), PersonalFieldPolicy::Exclude, new ReversingCipher());
        $excluding->freeze(new Profile('p', 'Ada', 'ada@example.org', 'Bio'));

        self::assertSame(['bio' => 'Bio'], $this->rawState('p', 1));
    }

    public function testASealedValueKeepsItsDecimalPointAndOpensWhateverFollowsAPlainField(): void
    {
        $cipher = new ReversingCipher();
        $versions = new Versions($this->manager, $this->clock, new AllowVersionRestore(), new ListedPersonalFields([Ledger::class => ['score']]), PersonalFieldPolicy::Encrypt, $cipher);
        $ledger = new Ledger('l', 'Note', 1.0);
        $versions->freeze($ledger);

        self::assertSame(['note' => 'Note', 'score' => [Versions::SEALED => $cipher->seal('1.0', 'phil_record_version|ledger|l|1|score')], 'data' => null], $this->rawState('l', 1));

        $profiles = new Versions($this->manager, $this->clock, new AllowVersionRestore(), new ListedPersonalFields(self::PERSONAL), PersonalFieldPolicy::Encrypt, $cipher);
        $profile = new Profile('p', 'Ada', 'ada@example.org', 'Bio');
        $profiles->freeze($profile);
        $state = $this->rawState('p', 1);
        $state['name'] = 'Ada';
        $this->manager->getConnection()->update('phil_record_version', ['state' => json_encode($state, \JSON_THROW_ON_ERROR)], ['subject_id' => 'p']);
        $this->manager->clear();
        $profile = $this->manager->find(Profile::class, 'p');
        self::assertInstanceOf(Profile::class, $profile);
        $profile->email = 'changed@example.org';
        $profiles->restore($profile, 1);

        self::assertSame('ada@example.org', $profile->email, 'the sealed field after a plain one is opened');
        self::assertSame('Ada', $profile->name);
    }

    public function testTheStateOfTheRecordIsReadOnceWhateverTheFieldsAVersionLeftOut(): void
    {
        $reads = [];

        foreach ([['name'], ['name', 'email']] as $fields) {
            $versions = new Versions($this->manager, $this->clock, new AllowVersionRestore(), new ListedPersonalFields([Profile::class => $fields]), PersonalFieldPolicy::Exclude);
            $profile = new Profile('p'.\count($fields), 'Ada', 'ada@example.org', 'Bio');
            $versions->freeze($profile);
            $profile->bio = 'Deux';
            $profile->reads = 0;
            $versions->restore($profile, 1);
            $reads[] = $profile->reads;
            self::assertSame('Ada', $profile->name);
        }

        self::assertSame($reads[0], $reads[1], 'a second field left out reads nothing more');
    }

    public function testASealedValueOpensToTheDepthOfJsonDecodeAndNoFurther(): void
    {
        $cipher = new ReversingCipher();
        $versions = new Versions($this->manager, $this->clock, new AllowVersionRestore(), new ListedPersonalFields([Ledger::class => ['data']]), PersonalFieldPolicy::Encrypt, $cipher);
        $ledger = new Ledger('l', 'Note');
        $versions->freeze($ledger);
        $context = 'phil_record_version|ledger|l|1|data';

        foreach ([[511, true], [512, false]] as [$depth, $opens]) {
            $json = str_repeat('[', $depth).str_repeat(']', $depth);
            $this->manager->getConnection()->update('phil_record_version', ['state' => json_encode(['note' => 'Note', 'score' => 0.0, 'data' => [Versions::SEALED => $cipher->seal($json, $context)]], \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION)], ['subject_id' => 'l']);

            $this->manager->clear();
            $ledger = $this->manager->find(Ledger::class, 'l');
            self::assertInstanceOf(Ledger::class, $ledger);

            if ($opens) {
                $versions->restore($ledger, 1);
                self::assertIsArray($ledger->data);

                continue;
            }

            $this->expectException(JsonException::class);
            $versions->restore($ledger, 1);
        }
    }

    private function eraser(): VersionEraser
    {
        return new VersionEraser($this->manager, new ListedPersonalFields(self::PERSONAL), $this->clock);
    }

    private function keepingVersions(): Versions
    {
        return new Versions($this->manager, $this->clock, new AllowVersionRestore(), new ListedPersonalFields(self::PERSONAL), PersonalFieldPolicy::Keep);
    }

    /**
     * @return array<string, mixed>
     */
    private function rawState(string $id, int $number): array
    {
        $state = $this->manager->getConnection()->fetchOne('SELECT state FROM phil_record_version WHERE subject_id = ? AND version_number = ?', [$id, $number]);
        self::assertIsString($state);
        $decoded = json_decode($state, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function managerWithSuspension(BlameSuspension $suspension): EntityManager
    {
        $events = $this->manager->getEventManager();

        foreach ($events->getAllListeners() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof BlameListener) {
                    $events->removeEventListener($event, $listener);
                }
            }
        }

        $events->addEventListener(['prePersist', 'onFlush'], new BlameListener($this->blame, $suspension));
        $this->manager->getFilters()->disable(SoftDeletedFilter::NAME);

        return $this->manager;
    }
}
