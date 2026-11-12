<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Integration;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\DoctrineBehaviours\AllowVersionRestore;
use Phil\DoctrineBehaviours\DeactivatedFilter;
use Phil\DoctrineBehaviours\FixedOwner;
use Phil\DoctrineBehaviours\OwnerEraser;
use Phil\DoctrineBehaviours\OwnerId;
use Phil\DoctrineBehaviours\Positions;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Database;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Bike;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Course;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Lamp;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Lesson;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Manual;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Recipe;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\RecipeTranslation;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Tag;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Ticket;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\MutableBlame;
use Phil\DoctrineBehaviours\Translations;
use Phil\DoctrineBehaviours\Versions;
use Phil\LocaleDimensions\FallbackChain;
use Phil\LocaleDimensions\Locale;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * The SQL that depends on a database, on a real server: the spelling of true in the filter and in the columns, the
 * slugs found by `IN` within a scope, the positions moved in a transaction, the state of a version in a JSON column, a
 * UUID in a `guid` column. Each subclass names the environment variable that holds the URL of its server; without it,
 * the test is skipped.
 */
abstract class RealDatabaseTestCase extends TestCase
{
    private ?EntityManager $database = null;

    private MockClock $clock;

    /**
     * @var array<string, mixed>
     */
    private array $parameters = [];

    /**
     * @return non-empty-string
     */
    abstract protected static function urlVariable(): string;

    /**
     * The statement that makes the session give up waiting for a lock after a second.
     *
     * @return non-empty-string
     */
    abstract protected static function lockTimeout(): string;

    #[Override]
    protected function setUp(): void
    {
        $url = getenv(static::urlVariable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', static::urlVariable()));
        }

        $this->clock = new MockClock(new DateTimeImmutable('2026-11-04 13:00 UTC'));
        $this->parameters = new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url);
        $this->database = Database::onConnection($this->clock, new MutableBlame('ada'), new FixedOwner(new OwnerId('owner-1')), $this->parameters);
    }

    #[Override]
    protected function tearDown(): void
    {
        if ($this->database instanceof EntityManager) {
            new SchemaTool($this->database)->dropSchema($this->database->getMetadataFactory()->getAllMetadata());
            $this->database->getConnection()->close();
        }
    }

    public function testTheFilterHidesWhatIsDeactivated(): void
    {
        $manager = $this->manager();
        $lamp = new Lamp('l');
        $manager->persist($lamp);
        $manager->persist(new Lamp('k'));
        $manager->flush();

        $lamp->deactivate();
        $manager->flush();
        $manager->clear();

        self::assertNull($manager->find(Lamp::class, 'l'));
        self::assertCount(1, $manager->getRepository(Lamp::class)->findAll());
        $manager->getFilters()->disable(DeactivatedFilter::NAME);
        self::assertFalse($manager->find(Lamp::class, 'l')?->isActive());
        $manager->getFilters()->enable(DeactivatedFilter::NAME);
    }

    public function testASlugIsNumberedByWhatTheTableHolds(): void
    {
        $manager = $this->manager();
        $first = new Tag('a', 'Bonjour');
        $manager->persist($first);
        $manager->flush();

        $second = new Tag('b', 'Bonjour');
        $third = new Tag('c', 'Bonjour');
        $manager->persist($second);
        $manager->persist($third);
        $manager->flush();

        self::assertSame(['bonjour', 'bonjour-2', 'bonjour-3'], [$first->slug(), $second->slug(), $third->slug()]);
    }

    public function testAVersionComesBackFromItsColumn(): void
    {
        $manager = $this->manager();
        $versions = new Versions($manager, $this->clock, new AllowVersionRestore());
        $manual = new Manual('m', 'Été', 'Corps « accentué »');
        $versions->freeze($manual);
        $manual->title = 'Deux';
        $manager->flush();
        $versions->restore($manual, 1);
        $manager->clear();

        $reread = $manager->find(Manual::class, 'm');
        self::assertInstanceOf(Manual::class, $reread);
        self::assertSame(['Été', 3, false], [$reread->title, $reread->versionNumber(), $reread->isVersionFrozen()]);
        self::assertEquals(['title' => 'Été', 'body' => 'Corps « accentué »'], $versions->get($reread, 1)?->state());
    }

    public function testADatedIdentifierKeepsItsTime(): void
    {
        $manager = $this->manager();
        $ticket = new Ticket('sujet');
        $manager->persist($ticket);
        $manager->flush();
        $manager->clear();

        $reread = $manager->find(Ticket::class, $ticket->datedId());
        self::assertSame('2026-11-04T13:00:00+00:00', $reread?->datedIdTime()?->format('c'));
    }

    public function testAnOwnerIsErased(): void
    {
        $manager = $this->manager();
        $manager->persist(new Bike('b'));
        $manager->flush();

        $eraser = new OwnerEraser($manager);

        self::assertSame(['bike' => 1], $eraser->find(new OwnerId('owner-1')));
        self::assertSame(1, $eraser->replace(new OwnerId('owner-1'), new OwnerId('former')));
    }

    public function testRecordsAreOrderedAndMovedWithinTheirGroup(): void
    {
        $manager = $this->manager();
        $music = new Course('music');
        $lessons = [new Lesson('a', 'Intro', $music), new Lesson('b', 'Intro', $music), new Lesson('c', 'Intro', $music)];
        $manager->persist($music);

        foreach ($lessons as $lesson) {
            $manager->persist($lesson);
        }

        $manager->flush();

        self::assertSame(['intro', 'intro-2', 'intro-3'], array_map(static fn (Lesson $lesson): ?string => $lesson->slug(), $lessons));
        self::assertSame([1, 2, 3], array_map(static fn (Lesson $lesson): ?int => $lesson->position(), $lessons));

        $positions = new Positions($manager);
        $positions->moveBefore($lessons[2], $lessons[0]);
        $positions->moveLast($lessons[1]);

        $manager->clear();

        /** @var list<Lesson> $ordered */
        $ordered = $manager->getRepository(Lesson::class)->findBy([], ['position' => 'ASC']);
        self::assertSame(['c', 'a', 'b'], array_map(static fn (Lesson $lesson): string => $lesson->id, $ordered));
    }

    public function testAMoveToAnEndWaitsForAnotherMoveInItsGroup(): void
    {
        $manager = $this->manager();
        $music = new Course('music');
        $chess = new Course('chess');
        $lessons = [new Lesson('a', 'Intro', $music), new Lesson('b', 'Scales', $music), new Lesson('c', 'Chords', $music), new Lesson('d', 'Openings', $chess)];
        $manager->persist($music);
        $manager->persist($chess);

        foreach ($lessons as $lesson) {
            $manager->persist($lesson);
        }

        $manager->flush();
        $positions = new Positions($manager);
        $manager->getConnection()->executeStatement(static::lockTimeout());

        /** @phpstan-ignore argument.type (the parameters of a test, as DriverManager documents them) */
        $other = DriverManager::getConnection($this->parameters);
        $other->beginTransaction();

        try {
            // Another move in the same group holds the row of its first lesson.
            $other->executeStatement("UPDATE lesson SET position = position WHERE id = 'a'");

            foreach ([$positions->moveFirst(...), $positions->moveLast(...)] as $move) {
                try {
                    $move($lessons[2]);
                    self::fail('A move to an end waits for the other move in its group.');
                } catch (DriverException $timeout) {
                    // MySQL reports a lock wait timeout, PostgreSQL a lock it could not get in time.
                    self::assertStringContainsStringIgnoringCase('lock', $timeout->getMessage());
                }
            }

            $positions->moveLast($lessons[3]);
            self::assertSame(1, $lessons[3]->position(), 'a move in another group does not wait');
        } finally {
            $other->rollBack();
            $other->close();
        }

        $positions->moveFirst($lessons[2]);
        $positions->moveLast($lessons[0]);

        $manager->clear();

        /** @var list<Lesson> $ordered */
        $ordered = $manager->getRepository(Lesson::class)->findBy(['course' => 'music'], ['position' => 'ASC']);
        self::assertSame(['c', 'b', 'a'], array_map(static fn (Lesson $lesson): string => $lesson->id, $ordered), 'once released, the moves go through');
    }

    public function testTranslationsAreReadAlongTheChainAndOnePerLocale(): void
    {
        $manager = $this->manager();
        $recipes = [new Recipe('crepes'), new Recipe('waffles')];
        $translations = new Translations($manager);

        foreach ($recipes as $recipe) {
            $manager->persist($recipe);
        }

        $manager->flush();
        $french = $translations->translate($recipes[0], Locale::fromString('fr'));
        self::assertInstanceOf(RecipeTranslation::class, $french);
        $french->title = 'Crêpes';
        $english = $translations->translate($recipes[1], Locale::fromString('en'));
        self::assertInstanceOf(RecipeTranslation::class, $english);
        $english->title = 'Waffles';
        $manager->flush();
        $manager->clear();

        $loaded = $manager->getRepository(Recipe::class)->findBy([], ['id' => 'ASC']);
        $set = $translations->preload($loaded, FallbackChain::of([Locale::fromString('fr_CA'), Locale::fromString('fr'), Locale::fromString('en')]));

        self::assertSame(['fr', 'en'], array_map(static fn (Recipe $recipe): ?string => $set->of($recipe)?->locale()->value, $loaded));
        self::assertSame('crepes', $set->of($loaded[0]) instanceof RecipeTranslation ? $set->of($loaded[0])->slug() : null);

        $this->expectException(UniqueConstraintViolationException::class);
        $manager->getConnection()->insert('recipe_translation', ['id' => 'other', 'recipe_id' => 'crepes', 'locale' => 'fr', 'title' => 'Crêpes', 'version_number' => 1, 'version_frozen' => false], ['version_frozen' => 'boolean']);
    }

    private function manager(): EntityManager
    {
        self::assertInstanceOf(EntityManager::class, $this->database);

        return $this->database;
    }
}
