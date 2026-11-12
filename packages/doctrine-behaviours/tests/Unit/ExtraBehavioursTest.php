<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Override;
use Phil\DoctrineBehaviours\AllowVersionRestore;
use Phil\DoctrineBehaviours\BlameEraser;
use Phil\DoctrineBehaviours\DatedIdentifierEntity;
use Phil\DoctrineBehaviours\DatedIdentifierListener;
use Phil\DoctrineBehaviours\DeactivatableEntity;
use Phil\DoctrineBehaviours\DeactivatedFilter;
use Phil\DoctrineBehaviours\DeactivationListener;
use Phil\DoctrineBehaviours\Exception\InvalidOwner;
use Phil\DoctrineBehaviours\Exception\InvalidVersionedState;
use Phil\DoctrineBehaviours\Exception\SlugUnavailable;
use Phil\DoctrineBehaviours\Exception\UnknownVersion;
use Phil\DoctrineBehaviours\Exception\VersionRestoreDenied;
use Phil\DoctrineBehaviours\FixedOwner;
use Phil\DoctrineBehaviours\MappedColumns;
use Phil\DoctrineBehaviours\OwnableEntity;
use Phil\DoctrineBehaviours\OwnerEraser;
use Phil\DoctrineBehaviours\OwnerId;
use Phil\DoctrineBehaviours\OwnerListener;
use Phil\DoctrineBehaviours\PublicationListener;
use Phil\DoctrineBehaviours\PublicationWorkflow;
use Phil\DoctrineBehaviours\PublishableEntity;
use Phil\DoctrineBehaviours\RefuseVersionRestore;
use Phil\DoctrineBehaviours\SluggableEntity;
use Phil\DoctrineBehaviours\SlugListener;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Database;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Aircraft;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Bike;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\ChildTag;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Fan;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Guide;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Kite;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Lamp;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Linked;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Manual;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Note;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Report;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Scooter;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\ShortTag;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\SiblingTag;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Skate;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Story;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Tag;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Ticket;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Trike;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\MutableBlame;
use Phil\DoctrineBehaviours\Version\RecordVersion;
use Phil\DoctrineBehaviours\VersionableEntity;
use Phil\DoctrineBehaviours\VersionListener;
use Phil\DoctrineBehaviours\Versions;
use Phil\Slug\InvalidSlug;
use Phil\Slug\Slug;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Uid\UuidV7;
use Symfony\Component\Workflow\Event\EnteredEvent;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\StateMachine;

#[CoversClass(DeactivationListener::class)]
#[CoversClass(DeactivatedFilter::class)]
#[CoversClass(OwnerId::class)]
#[CoversClass(InvalidOwner::class)]
#[CoversClass(OwnerListener::class)]
#[CoversClass(FixedOwner::class)]
#[CoversClass(OwnerEraser::class)]
#[CoversClass(MappedColumns::class)]
#[CoversClass(PublicationWorkflow::class)]
#[CoversClass(PublicationListener::class)]
#[CoversClass(SlugListener::class)]
#[CoversClass(SlugUnavailable::class)]
#[CoversClass(VersionListener::class)]
#[CoversClass(Versions::class)]
#[CoversClass(RecordVersion::class)]
#[CoversClass(UnknownVersion::class)]
#[CoversClass(VersionRestoreDenied::class)]
#[CoversClass(InvalidVersionedState::class)]
#[CoversClass(AllowVersionRestore::class)]
#[CoversClass(RefuseVersionRestore::class)]
#[CoversClass(DatedIdentifierListener::class)]
#[CoversTrait(DeactivatableEntity::class)]
#[CoversTrait(OwnableEntity::class)]
#[CoversTrait(PublishableEntity::class)]
#[CoversTrait(SluggableEntity::class)]
#[CoversTrait(VersionableEntity::class)]
#[CoversTrait(DatedIdentifierEntity::class)]
final class ExtraBehavioursTest extends TestCase
{
    private MockClock $clock;

    private MutableBlame $blame;

    private EntityManager $manager;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-11-04 08:00', new DateTimeZone('America/Toronto')));
        $this->blame = new MutableBlame('ada');
        $this->manager = Database::entityManager($this->clock, $this->blame, false, new FixedOwner(new OwnerId('owner-1')));
    }

    // Deactivation

    public function testADeactivatedRecordStaysHiddenAndComesBack(): void
    {
        $lamp = new Lamp('l');
        $this->manager->persist($lamp);
        $this->manager->persist(new Lamp('k'));
        $this->manager->flush();
        self::assertTrue($lamp->isActive());
        self::assertNull($lamp->deactivatedAt());

        $this->clock->modify('+1 day');
        $lamp->deactivate();
        $this->manager->flush();
        $this->manager->clear();

        self::assertNull($this->manager->find(Lamp::class, 'l'));
        self::assertCount(1, $this->manager->getRepository(Lamp::class)->findAll());

        $this->manager->getFilters()->disable(DeactivatedFilter::NAME);
        $kept = $this->manager->find(Lamp::class, 'l');
        self::assertInstanceOf(Lamp::class, $kept);
        self::assertFalse($kept->isActive());
        self::assertSame('2026-11-05T13:00:00+00:00', $kept->deactivatedAt()?->format('c'));

        $kept->reactivate();
        $this->manager->flush();
        $this->manager->clear();
        $this->manager->getFilters()->enable(DeactivatedFilter::NAME);

        $back = $this->manager->find(Lamp::class, 'l');
        self::assertInstanceOf(Lamp::class, $back);
        self::assertTrue($back->isActive());
        self::assertNull($back->deactivatedAt());
    }

    public function testARecordBornInactiveIsStampedAndAGivenDateKept(): void
    {
        $inactive = new Lamp('a');
        $inactive->deactivate();

        $given = new DateTimeImmutable('2020-01-01 00:00 UTC');
        $imported = new Lamp('b');
        $imported->deactivate();
        $imported->stampDeactivated($given);

        $active = new Lamp('c');
        $active->stampDeactivated($given);

        $this->manager->persist($inactive);
        $this->manager->persist($imported);
        $this->manager->persist($active);
        $this->manager->flush();

        self::assertSame('2026-11-04T13:00:00+00:00', $inactive->deactivatedAt()?->format('c'));
        self::assertSame($given, $imported->deactivatedAt());
        self::assertNull($active->deactivatedAt());

        $imported->room = 'attic';
        $later = new DateTimeImmutable('2021-01-01 00:00 UTC');
        $imported->stampDeactivated($later);
        $this->manager->flush();
        $this->manager->clear();

        $this->manager->getFilters()->disable(DeactivatedFilter::NAME);
        $reread = $this->manager->find(Lamp::class, 'b');
        self::assertSame('2021-01-01T00:00:00+00:00', $reread?->deactivatedAt()?->format('c'));
    }

    public function testADeactivationIsWrittenInTheSameFlushAsTheChange(): void
    {
        $lamp = new Lamp('l');
        $this->manager->persist($lamp);
        $this->manager->flush();

        $lamp->deactivate();
        $this->manager->flush();

        self::assertSame('2026-11-04 13:00:00', $this->manager->getConnection()->fetchOne("SELECT deactivated_at FROM lamp WHERE id = 'l'"));
    }

    public function testAnEntityThatMapsNoActiveColumnIsLeftVisible(): void
    {
        $this->manager->persist(new Fan('f'));
        $this->manager->persist(new Note('n', 'rien'));
        $this->manager->flush();
        $this->manager->clear();

        self::assertInstanceOf(Fan::class, $this->manager->find(Fan::class, 'f'));
        self::assertInstanceOf(Note::class, $this->manager->find(Note::class, 'n'));
    }

    // Owner

    public function testAnOwnerIsAStableIdentifier(): void
    {
        self::assertSame('owner-1', new OwnerId('owner-1')->value);
        self::assertSame('owner-1', (string) new OwnerId('owner-1'));
        self::assertSame(255, \strlen(new OwnerId(str_repeat('a', 255))->value));

        foreach (['' => 'cannot be empty', 'ada@example.com' => 'holds an @', str_repeat('a', 256) => 'at most 255 bytes'] as $value => $message) {
            try {
                new OwnerId($value);
                self::fail('Accepted.');
            } catch (InvalidOwner $invalidOwner) {
                self::assertStringContainsString($message, $invalidOwner->getMessage());
                self::assertStringNotContainsString('example.com', $invalidOwner->getMessage());
            }
        }
    }

    public function testAPersistedRecordGetsItsOwnerAndKeepsIt(): void
    {
        $bike = new Bike('b');
        $given = new Bike('g');
        $given->claimOwner(new OwnerId('someone'));

        $this->manager->persist($bike);
        $this->manager->persist($given);
        $this->manager->persist(new Note('n', 'rien'));
        $this->manager->flush();

        self::assertSame('owner-1', $bike->ownerId()?->value);
        self::assertSame('someone', $given->ownerId()?->value);

        $bike->colour = 'blue';
        $this->manager->flush();
        self::assertSame('owner-1', $bike->ownerId()?->value);

        $bike->transferOwner(new OwnerId('other'));
        self::assertSame('other', $bike->ownerId()?->value);
        $bike->transferOwner(null);
        self::assertNull($bike->ownerId());
    }

    public function testNobodyKnownLeavesNoOwner(): void
    {
        $manager = Database::entityManager($this->clock, $this->blame);
        $bike = new Bike('b');
        $manager->persist($bike);
        $manager->flush();

        self::assertNull($bike->ownerId());
        self::assertNull(new FixedOwner()->current());
    }

    public function testTheEraserFindsAndReplacesAnOwnerEverywhere(): void
    {
        $this->manager->persist(new Bike('a'));
        $this->manager->persist(new Bike('b'));

        $other = new Bike('c');
        $other->claimOwner(new OwnerId('someone'));

        $this->manager->persist($other);
        $this->manager->persist(new Scooter('s'));
        $this->manager->persist(new Trike('t'));
        $this->manager->persist(new Skate('k'));
        $this->manager->persist(new Aircraft('p'));
        $this->manager->persist(new Kite('w', 'owner-1'));
        $this->manager->flush();

        $eraser = new OwnerEraser($this->manager);
        $owner = new OwnerId('owner-1');

        self::assertSame(['bike' => 2, 'vehicle' => 2, 'zz_aircraft' => 1], $eraser->find($owner));
        self::assertSame([], $eraser->find(new OwnerId('nobody')));
        self::assertContains('bike', $eraser->tables());
        self::assertContains('zz_aircraft', $eraser->tables());
        self::assertNotContains('kite', $eraser->tables(), 'an owner column, but no Ownable');
        self::assertNotContains('tag', $eraser->tables());
        self::assertTrue(array_is_list($eraser->tables()));
        self::assertSame(5, $eraser->replace($owner, new OwnerId('former')));
        self::assertSame(['bike' => 2, 'vehicle' => 2, 'zz_aircraft' => 1], $eraser->find(new OwnerId('former')));
        self::assertSame([], $eraser->find($owner));
        self::assertSame('owner-1', $this->manager->getConnection()->fetchOne("SELECT owner_id FROM kite WHERE id = 'w'"));
        self::assertSame(5, $eraser->replace(new OwnerId('former')));
        self::assertSame(['bike' => 1], $eraser->find(new OwnerId('someone')));
        $this->manager->clear();
        self::assertNull($this->manager->find(Bike::class, 'a')?->ownerId());
        self::assertSame(0, $eraser->replace(new OwnerId('ghost')));
    }

    // Publication

    public function testPublicationIsAStateMachineThatStampsItsDates(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('workflow.'.PublicationWorkflow::NAME.'.entered', new PublicationListener($this->clock));

        $workflow = new StateMachine(PublicationWorkflow::definition(), PublicationWorkflow::markingStore(), $dispatcher, PublicationWorkflow::NAME);
        $story = new Story('s');

        self::assertSame(['draft'], PublicationWorkflow::definition()->getInitialPlaces());
        self::assertSame('draft', $story->getPublicationState());
        self::assertFalse($story->isPublished());
        self::assertTrue($workflow->can($story, 'publish'));
        self::assertFalse($workflow->can($story, 'unpublish'));

        $workflow->apply($story, 'publish');
        self::assertTrue($story->isPublished());
        self::assertSame('published', $story->getPublicationState());
        self::assertSame('2026-11-04T13:00:00+00:00', $story->publishedAt()?->format('c'));
        self::assertNull($story->unpublishedAt());
        self::assertFalse($workflow->can($story, 'publish'));

        $this->clock->modify('+1 day');
        $workflow->apply($story, 'unpublish');
        self::assertFalse($story->isPublished());
        self::assertSame('unpublished', $story->getPublicationState());
        self::assertSame('2026-11-05T13:00:00+00:00', $this->iso($story->unpublishedAt()));

        $this->clock->modify('+1 day');
        $workflow->apply($story, 'publish');
        self::assertSame('2026-11-04T13:00:00+00:00', $this->iso($story->publishedAt()));
        self::assertNull($story->unpublishedAt());

        $this->manager->persist($story);
        $this->manager->flush();
        $this->manager->clear();
        self::assertSame('published', $this->manager->find(Story::class, 's')?->getPublicationState());
    }

    public function testATransitionTheWorkflowRefusesChangesNothing(): void
    {
        $workflow = new StateMachine(PublicationWorkflow::definition(), PublicationWorkflow::markingStore(), new EventDispatcher(), PublicationWorkflow::NAME);
        $story = new Story('s');

        $this->expectException(NotEnabledTransitionException::class);
        $workflow->apply($story, 'unpublish');
    }

    public function testTheListenerLeavesOtherSubjectsAndPlacesAlone(): void
    {
        $listener = new PublicationListener($this->clock);
        $story = new Story('s');
        $transition = PublicationWorkflow::definition()->getTransitions()[0];

        $listener(new EnteredEvent($story, new Marking(['draft' => 1]), $transition));
        self::assertNull($story->publishedAt());
        self::assertNull($story->unpublishedAt());

        $listener(new EnteredEvent(new stdClass(), new Marking(['published' => 1]), $transition));
        self::assertNull($story->publishedAt());
    }

    public function testADateGivenBeforePublicationIsKept(): void
    {
        $story = new Story('s');
        $given = new DateTimeImmutable('2020-01-01 00:00 UTC');
        $story->markPublished($given);
        $story->markPublished(new DateTimeImmutable('2021-01-01 00:00 UTC'));

        self::assertSame($given, $story->publishedAt());
    }

    // Slug

    public function testASlugIsMadeOnceAndKept(): void
    {
        $tag = new Tag('t', 'Jeux de rôle');
        $this->manager->persist($tag);
        $this->manager->persist(new Note('n', 'rien'));
        $this->manager->flush();
        self::assertSame('jeux-de-role', $tag->slug());

        $tag->name = 'Autre chose';
        $this->manager->flush();
        self::assertSame('jeux-de-role', $tag->slug());
        $this->manager->clear();
        self::assertSame('jeux-de-role', $this->manager->find(Tag::class, 't')?->slug());
    }

    public function testEveryRecordOfAFlushIsLookedAt(): void
    {
        $kept = new Tag('k', 'Kept');
        $cleared = new Tag('c', 'Cleared');
        $this->manager->persist($kept);
        $this->manager->persist($cleared);
        $this->manager->flush();

        $this->manager->persist(new Note('n', 'rien'));

        $fresh = new Tag('f', 'Fresh');
        $this->manager->persist($fresh);
        $kept->name = 'Renamed';
        $cleared->name = 'Made again';
        $cleared->assignSlug(null);

        $this->manager->flush();

        self::assertSame(['kept', 'made-again', 'fresh'], [$kept->slug(), $cleared->slug(), $fresh->slug()]);
    }

    public function testASlugTakenElsewhereIsNumbered(): void
    {
        $first = new Tag('a', 'Hello');
        $this->manager->persist($first);
        $this->manager->flush();

        $second = new Tag('b', 'Hello');
        $third = new Tag('c', 'Hello');
        $this->manager->persist($second);
        $this->manager->persist($third);
        $this->manager->flush();

        self::assertSame(['hello', 'hello-2', 'hello-3'], [$first->slug(), $second->slug(), $third->slug()]);

        $this->manager->clear();
        self::assertSame('hello-2', $this->manager->find(Tag::class, 'b')?->slug());
    }

    public function testAGivenSlugIsKeptOnceUnique(): void
    {
        $this->manager->persist(new Tag('a', 'Taken'));
        $this->manager->flush();

        $free = new Tag('b', 'Whatever');
        $free->assignSlug(Slug::fromString('my-own'));

        $taken = new Tag('c', 'Whatever');
        $taken->assignSlug(Slug::fromString('taken'));

        $this->manager->persist($free);
        $this->manager->persist($taken);
        $this->manager->flush();
        self::assertSame(['my-own', 'taken-2'], [$free->slug(), $taken->slug()]);

        $free->assignSlug(Slug::fromString('taken'));
        $this->manager->flush();
        self::assertSame('taken-3', $free->slug());
    }

    public function testAClearedSlugIsMadeAgainAndMayStayTheSame(): void
    {
        $tag = new Tag('a', 'Hello');
        $this->manager->persist($tag);
        $this->manager->flush();

        $tag->assignSlug(null);
        $this->manager->flush();
        self::assertSame('hello', $tag->slug());

        $tag->name = 'Bonjour';
        $tag->assignSlug(null);

        $this->manager->flush();
        $this->manager->clear();
        self::assertSame('bonjour', $this->manager->find(Tag::class, 'a')?->slug());
    }

    public function testRowsTheFiltersHideStillHoldTheirSlug(): void
    {
        $tag = new Tag('a', 'Hello');
        $this->manager->persist($tag);
        $this->manager->flush();
        $this->manager->getConnection()->executeStatement("UPDATE tag SET slug = 'hello-x' WHERE id = 'a'");

        $this->manager->persist(new Tag('b', 'Hello-x'));
        $this->manager->flush();

        self::assertSame('hello-x-2', $this->manager->getRepository(Tag::class)->findOneBy(['id' => 'b'])?->slug());
    }

    public function testASlugFitsItsColumnAndItsSlugger(): void
    {
        $long = new ShortTag('a', 'Bonjour le monde');
        $this->manager->persist($long);
        $this->manager->flush();
        self::assertSame('bonjour', $long->slug());

        $other = new ShortTag('b', 'Bonjour le monde');
        $this->manager->persist($other);
        $this->manager->flush();
        self::assertSame('bonjou-2', $other->slug());

        $hyphen = new ShortTag('c', 'x');
        $hyphen->assignSlug(Slug::fromString('abcdefg-hi'));

        $this->manager->persist($hyphen);
        $this->manager->flush();
        self::assertSame('abcdefg', $hyphen->slug());

        $this->manager->getConnection()->executeStatement("UPDATE short_tag SET slug = 'abcde-gh' WHERE id = 'c'");
        $cut = new ShortTag('d', 'x');
        $cut->assignSlug(Slug::fromString('abcde-gh'));

        $this->manager->persist($cut);
        $this->manager->flush();
        self::assertSame('abcde-2', $cut->slug());
    }

    public function testASubclassCountsTheSlugsOfTheTableThatHoldsThem(): void
    {
        $child = new ChildTag('a', 'Hello');
        $sibling = new SiblingTag('b', 'Hello');
        $this->manager->persist($child);
        $this->manager->flush();
        $this->manager->persist($sibling);
        $this->manager->flush();

        self::assertSame(['hello', 'hello-2'], [$child->slug(), $sibling->slug()]);
    }

    public function testWhenEveryNumberIsTakenTheErrorSaysSo(): void
    {
        $connection = $this->manager->getConnection();
        $connection->executeStatement("INSERT INTO tag (id, name, slug) VALUES ('0', 'x', 'hello')");

        for ($number = 2; $number <= SlugListener::MAX_ATTEMPTS; ++$number) {
            $connection->executeStatement("INSERT INTO tag (id, name, slug) VALUES (?, 'x', ?)", [(string) $number, 'hello-'.$number]);
        }

        $this->manager->persist(new Tag('z', 'Hello'));

        $this->expectException(SlugUnavailable::class);
        $this->expectExceptionMessageIsOrContains('No slug is free for "hello" in the table tag after 100 attempts');
        $this->manager->flush();
    }

    public function testTheLastNumberIsTried(): void
    {
        $connection = $this->manager->getConnection();
        $connection->executeStatement("INSERT INTO tag (id, name, slug) VALUES ('0', 'x', 'hello')");

        for ($number = 2; $number < SlugListener::MAX_ATTEMPTS; ++$number) {
            $connection->executeStatement("INSERT INTO tag (id, name, slug) VALUES (?, 'x', ?)", [(string) $number, 'hello-'.$number]);
        }

        $tag = new Tag('z', 'Hello');
        $this->manager->persist($tag);
        $this->manager->flush();

        self::assertSame('hello-100', $tag->slug());
    }

    public function testATextWithNothingToSlugIsRefused(): void
    {
        $this->manager->persist(new Tag('a', '!!!'));

        $this->expectException(InvalidSlug::class);
        $this->manager->flush();
    }

    public function testTheIndexHoldsARaceTheQueryCannotSee(): void
    {
        $this->manager->persist(new Tag('a', 'Hello'));
        $this->manager->flush();

        $this->expectException(UniqueConstraintViolationException::class);
        $this->manager->getConnection()->executeStatement("INSERT INTO tag (id, name, slug) VALUES ('b', 'x', 'hello')");
    }

    // Versions

    public function testAFrozenVersionIsForkedByTheNextEdit(): void
    {
        $versions = $this->versions();
        $manual = new Manual('m', 'Un', 'Premier');
        $this->manager->persist($manual);
        $this->manager->flush();
        self::assertSame([1, false], [$manual->versionNumber(), $manual->isVersionFrozen()]);

        $manual->body = 'Encore';
        $this->manager->flush();
        self::assertSame(1, $manual->versionNumber());

        $frozen = $versions->freeze($manual);
        self::assertSame([1, true], [$manual->versionNumber(), $manual->isVersionFrozen()]);
        self::assertSame(['manual', 'm', 1, ['title' => 'Un', 'body' => 'Encore'], '2026-11-04T13:00:00+00:00', 'ada'], [$frozen->subjectTable(), $frozen->subjectId(), $frozen->number(), $frozen->state(), $frozen->frozenAt()->format('c'), $frozen->createdBy()]);
        self::assertTrue($versions->isFrozen($manual, 1));
        self::assertFalse($versions->isFrozen($manual, 2));
        self::assertSame($frozen, $versions->freeze($manual));
        self::assertSame(1, $manual->versionNumber());

        $manual->note = 'pas versionné';
        $this->manager->flush();
        self::assertSame([1, true], [$manual->versionNumber(), $manual->isVersionFrozen()]);

        $manual->title = 'Deux';
        $this->manager->flush();
        self::assertSame([2, false], [$manual->versionNumber(), $manual->isVersionFrozen()]);
        $this->manager->clear();

        $reread = $this->manager->find(Manual::class, 'm');
        self::assertInstanceOf(Manual::class, $reread);
        self::assertSame([2, false, 'Deux'], [$reread->versionNumber(), $reread->isVersionFrozen(), $reread->title]);
        self::assertSame(['title' => 'Un', 'body' => 'Encore'], $versions->get($reread, 1)?->state());
        self::assertNull($versions->get($reread, 2));
    }

    public function testFreezingFlushesWhatIsPendingAndCanStartANewRecord(): void
    {
        $versions = $this->versions();
        $manual = new Manual('m', 'Un');
        $version = $versions->freeze($manual);
        self::assertSame(1, $version->number());
        self::assertTrue($this->manager->contains($manual));

        $manual->title = 'Deux';
        $second = $versions->freeze($manual);
        self::assertSame([2, ['title' => 'Deux', 'body' => '']], [$second->number(), $second->state()]);
        self::assertSame([2, true], [$manual->versionNumber(), $manual->isVersionFrozen()]);
    }

    public function testVersionsAreListedTheLatestFirstWithALimit(): void
    {
        $versions = $this->versions();
        $manual = new Manual('m', 'Un');
        $other = new Manual('o', 'Autre');
        $versions->freeze($other);

        foreach (['A', 'B', 'C'] as $title) {
            $manual->title = $title;
            $versions->freeze($manual);
        }

        self::assertSame([3, 2, 1], array_map(static fn (RecordVersion $v): int => $v->number(), $versions->list($manual)));
        self::assertSame([3], array_map(static fn (RecordVersion $v): int => $v->number(), $versions->list($manual, 1)));
        self::assertSame(50, Versions::DEFAULT_LIST_LIMIT);
    }

    public function testGoingBackForksTheNextNumberAndLosesNothing(): void
    {
        $versions = $this->versions();
        $manual = new Manual('m', 'Un', 'Premier');
        $versions->freeze($manual);
        $manual->title = 'Deux';
        $this->manager->flush();
        self::assertSame(2, $manual->versionNumber());

        $versions->restore($manual, 1);

        self::assertSame(['Un', 'Premier', 3, false], [$manual->title, $manual->body, $manual->versionNumber(), $manual->isVersionFrozen()]);
        self::assertSame(['title' => 'Deux', 'body' => 'Premier'], $versions->get($manual, 2)?->state());
        self::assertTrue($versions->isFrozen($manual, 1));
        $this->manager->clear();
        $reread = $this->manager->find(Manual::class, 'm');
        self::assertSame(['Un', 3], [$reread?->title, $reread?->versionNumber()]);
    }

    public function testGoingBackTakesTheRight(): void
    {
        $manual = new Manual('m', 'Un');
        $this->versions()->freeze($manual);
        $manual->title = 'Deux';
        $this->manager->flush();
        $versions = new Versions($this->manager, $this->clock, new RefuseVersionRestore());

        try {
            $versions->restore($manual, 1);
            self::fail('Allowed.');
        } catch (VersionRestoreDenied $versionRestoreDenied) {
            self::assertStringContainsString('Going back to version 1 of '.Manual::class.' "m" is not allowed.', $versionRestoreDenied->getMessage());
        }

        self::assertSame(['Deux', 2], [$manual->title, $manual->versionNumber()]);
    }

    public function testGoingBackToAVersionNeverFrozenIsRefused(): void
    {
        $manual = new Manual('m', 'Un');
        $this->manager->persist($manual);
        $this->manager->flush();

        $this->expectException(UnknownVersion::class);
        $this->expectExceptionMessageIsOrContains(Manual::class.' "m" has no frozen version 4.');
        $this->versions()->restore($manual, 4);
    }

    public function testAVersionKeepsMappedFieldsOnly(): void
    {
        $guide = new Guide('g');

        try {
            $this->versions()->freeze($guide);
            self::fail('Frozen.');
        } catch (InvalidVersionedState $invalidVersionedState) {
            self::assertStringContainsString(Guide::class.'::versionedState() names "nothing"', $invalidVersionedState->getMessage());
        }
    }

    public function testAnAssociationCanBeKept(): void
    {
        $linked = new Linked('l');
        $version = $this->versions()->freeze($linked);

        self::assertSame(['note' => null], $version->state());
    }

    public function testVersionsOfASubclassGoByTheRootTable(): void
    {
        self::assertSame('document', $this->versions()->freeze(new Report('r'))->subjectTable());
    }

    // The erasers meet the versions

    public function testWhoFrozeAVersionIsErasedWithTheBlame(): void
    {
        $this->versions()->freeze(new Manual('m', 'Un'));

        self::assertSame(['phil_record_version' => 1], new BlameEraser($this->manager)->find('ada'));
    }

    // Dated identifier

    public function testAVersionNamesItsRecordInColumnsOfKnownLengths(): void
    {
        $metadata = $this->manager->getClassMetadata(RecordVersion::class);

        self::assertSame(128, $metadata->getFieldMapping('subjectTable')->length);
        self::assertSame(255, $metadata->getFieldMapping('subjectId')->length);
    }

    public function testAnIdentifierHoldsItsTime(): void
    {
        $ticket = new Ticket('sujet');
        $this->manager->persist($ticket);
        $this->manager->flush();

        $id = $ticket->datedId();
        self::assertIsString($id);
        self::assertTrue(UuidV7::isValid($id));
        self::assertSame('2026-11-04T13:00:00+00:00', $ticket->datedIdTime()?->format('c'));
        $this->manager->clear();
        self::assertSame('sujet', $this->manager->find(Ticket::class, $id)?->subject);
    }

    public function testAGivenIdentifierIsKeptAndAnythingElseRefused(): void
    {
        $id = UuidV7::generate(new DateTimeImmutable('2020-01-01 00:00 UTC'));
        $ticket = new Ticket();
        self::assertNull($ticket->datedId());
        self::assertNull($ticket->datedIdTime());
        $ticket->assignDatedId($id);
        $this->manager->persist($ticket);
        $this->manager->persist(new Note('n', 'rien'));
        $this->manager->flush();
        self::assertSame($id, $ticket->datedId());

        $this->expectException(InvalidArgumentException::class);
        $ticket->assignDatedId('not-a-uuid');
    }

    public function testIdentifiersSortInCreationOrder(): void
    {
        $first = new Ticket();
        $this->manager->persist($first);
        $this->clock->modify('+1 second');
        $second = new Ticket();
        $this->manager->persist($second);

        self::assertLessThan((string) $second->datedId(), (string) $first->datedId());
    }

    private function iso(?DateTimeImmutable $date): ?string
    {
        return $date?->format('c');
    }

    private function versions(): Versions
    {
        return new Versions($this->manager, $this->clock, new AllowVersionRestore());
    }
}
