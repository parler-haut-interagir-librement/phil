<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityNotFoundException;
use Override;
use Phil\DoctrineBehaviours\BlameableEntity;
use Phil\DoctrineBehaviours\BlameEraser;
use Phil\DoctrineBehaviours\BlameListener;
use Phil\DoctrineBehaviours\FixedBlame;
use Phil\DoctrineBehaviours\SoftDeletableEntity;
use Phil\DoctrineBehaviours\SoftDeletedFilter;
use Phil\DoctrineBehaviours\SoftDeleteListener;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Database;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Article;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Book;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Card;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Draft;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Essay;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Label;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Note;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Post;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Review;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Shelf;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\MutableBlame;
use Phil\DoctrineBehaviours\TimestampableEntity;
use Phil\DoctrineBehaviours\TimestampListener;
use Phil\DoctrineBehaviours\Utc;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(TimestampListener::class)]
#[CoversClass(BlameListener::class)]
#[CoversClass(SoftDeleteListener::class)]
#[CoversClass(SoftDeletedFilter::class)]
#[CoversClass(FixedBlame::class)]
#[CoversClass(BlameEraser::class)]
#[CoversClass(Utc::class)]
#[CoversTrait(TimestampableEntity::class)]
#[CoversTrait(BlameableEntity::class)]
#[CoversTrait(SoftDeletableEntity::class)]
final class BehavioursTest extends TestCase
{
    private MockClock $clock;

    private MutableBlame $blame;

    private EntityManager $manager;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-11-04 08:00', new DateTimeZone('America/Toronto')));
        $this->blame = new MutableBlame('ada');
        $this->manager = Database::entityManager($this->clock, $this->blame);
    }

    public function testAnEntityIsStampedAndBlamed(): void
    {
        $article = new Article('a', 'Bonjour');
        $this->manager->persist($article);
        $this->manager->persist(new Note('n', 'rien'));
        $this->manager->flush();

        self::assertSame(['2026-11-04T13:00:00+00:00', '2026-11-04T13:00:00+00:00', 'ada', 'ada'], [$article->createdAt()?->format('c'), $article->updatedAt()?->format('c'), $article->createdBy(), $article->updatedBy()]);

        $this->clock->modify('+1 hour');
        $this->blame->who = 'grace';
        $article->title = 'Allo';
        $this->manager->flush();
        $this->manager->clear();

        $kept = $this->article('a');
        self::assertSame(['2026-11-04T13:00:00+00:00', '2026-11-04T14:00:00+00:00', 'ada', 'grace'], [$kept->createdAt()?->format('c'), $kept->updatedAt()?->format('c'), $kept->createdBy(), $kept->updatedBy()]);

        $this->clock->modify('+1 hour');
        $this->blame->who = null;
        $kept->title = 'Salut';
        $this->manager->flush();
        $this->manager->clear();

        $again = $this->article('a');
        self::assertSame(['2026-11-04T15:00:00+00:00', 'grace'], [$again->updatedAt()?->format('c'), $again->updatedBy()]);
        self::assertSame(['bob', null], [new FixedBlame('bob')->current(), new FixedBlame()->current()]);
    }

    public function testWhatAnEntityIsGivenIsKept(): void
    {
        $given = new DateTimeImmutable('2020-01-01 00:00 UTC');
        $article = new Article('a', 'Ancien');
        $article->stampCreated($given);
        $article->blameCreated('importer');

        $edited = new Article('c', 'Modifié');
        $edited->stampUpdated($given);
        $edited->blameUpdated('editor');

        $this->manager->persist($article);
        $this->manager->persist($edited);
        $this->manager->flush();

        self::assertSame([$given, $given, 'importer', 'importer'], [$article->createdAt(), $article->updatedAt(), $article->createdBy(), $article->updatedBy()]);
        self::assertSame(['2026-11-04T13:00:00+00:00', $given, 'ada', 'editor'], [$edited->createdAt()?->format('c'), $edited->updatedAt(), $edited->createdBy(), $edited->updatedBy()]);

        $this->blame->who = null;
        $nobody = new Article('b', 'Personne');
        $this->manager->persist($nobody);
        $this->manager->flush();
        self::assertSame([null, null], [$nobody->createdBy(), $nobody->updatedBy()]);

        $this->blame->who = 'ada';
        $later = new DateTimeImmutable('2021-01-01 00:00 UTC');
        $article->title = 'Importé';
        $article->stampUpdated($later);
        $article->blameUpdated('importer2');

        $this->manager->flush();
        $this->manager->clear();

        $kept = $this->article('a');
        self::assertSame(['2021-01-01T00:00:00+00:00', 'importer2'], [$kept->updatedAt()?->format('c'), $kept->updatedBy()]);
    }

    public function testARemovedEntityIsMarkedDeletedThenHidden(): void
    {
        $article = new Article('a', 'Bonjour');
        $note = new Note('n', 'rien');
        $this->manager->persist($article);
        $this->manager->persist($note);
        $this->manager->persist(new Article('b', 'Reste'));
        $this->manager->flush();

        $this->clock->modify('+1 day');
        $this->manager->remove($note);
        $this->manager->remove($article);
        $this->manager->flush();
        self::assertEquals($article->deletedAt(), $this->manager->getUnitOfWork()->getOriginalEntityData($article)['deletedAt'] ?? null);

        self::assertTrue($this->manager->contains($article));
        self::assertSame('2026-11-05T13:00:00+00:00', $article->deletedAt()?->format('c'));
        self::assertTrue($article->isDeleted());
        self::assertNull($this->manager->find(Note::class, 'n'));
        $this->manager->flush();
        $this->manager->clear();

        self::assertSame(['b'], array_map(static fn (Article $article): string => $article->id, $this->manager->getRepository(Article::class)->findAll()));
        self::assertEquals(1, $this->manager->getConnection()->fetchOne("SELECT COUNT(*) FROM article WHERE id = 'a' AND deleted_at IS NOT NULL"));
        self::assertEquals(0, $this->manager->getConnection()->fetchOne('SELECT COUNT(*) FROM note'));

        $this->manager->getFilters()->disable(SoftDeletedFilter::NAME);
        $deleted = $this->article('a');
        self::assertSame('2026-11-05T13:00:00+00:00', $deleted->deletedAt()?->format('c'));

        $deleted->restore();
        self::assertFalse($deleted->isDeleted());
        $this->manager->flush();
        $this->manager->getFilters()->enable(SoftDeletedFilter::NAME);
        $this->manager->clear();
        self::assertCount(2, $this->manager->getRepository(Article::class)->findAll());

        $this->manager->getFilters()->disable(SoftDeletedFilter::NAME);
        $gone = $this->article('b');
        $this->manager->remove($gone);
        $this->manager->flush();
        $this->manager->remove($gone);
        $this->manager->flush();
        self::assertEquals(0, $this->manager->getConnection()->fetchOne("SELECT COUNT(*) FROM article WHERE id = 'b'"));
        self::assertSame('phil_soft_deleted', SoftDeletedFilter::NAME);

        $this->manager->getFilters()->enable(SoftDeletedFilter::NAME);
        $this->manager->persist(new Draft('d', new DateTimeImmutable('2026-01-01 UTC')));
        $this->manager->flush();
        $this->manager->clear();
        self::assertCount(1, $this->manager->getRepository(Draft::class)->findAll());
    }

    /**
     * A removal that cascades: the parent's row stays, so do the children a cascaded persist brings back, the soft
     * deletable ones marked deleted; those cascaded by removal alone are deleted for good.
     */
    public function testARemovalCascadesAsTheAssociationsSay(): void
    {
        $shelf = new Shelf('s');
        $shelf->books->add(new Book('b', $shelf));
        $shelf->cards->add(new Card('c', $shelf));

        $label = new Label('l', $shelf);
        $shelf->labels->add($label);
        $this->manager->persist($shelf);
        $this->manager->persist($label);
        $this->manager->flush();

        $this->manager->remove($shelf);
        $this->manager->flush();
        $this->manager->clear();

        $connection = $this->manager->getConnection();
        self::assertSame(
            [1, 1, 1, 0],
            [
                $connection->fetchOne('SELECT COUNT(*) FROM shelf WHERE deleted_at IS NOT NULL'),
                $connection->fetchOne('SELECT COUNT(*) FROM book'),
                $connection->fetchOne('SELECT COUNT(*) FROM card WHERE deleted_at IS NOT NULL'),
                $connection->fetchOne('SELECT COUNT(*) FROM label'),
            ],
            'the book kept, the card soft deleted, the label deleted for good',
        );

        $this->manager->getFilters()->disable(SoftDeletedFilter::NAME);
        $restored = $this->manager->find(Shelf::class, 's');
        self::assertInstanceOf(Shelf::class, $restored);
        $restored->restore();
        $this->manager->flush();
        $this->manager->getFilters()->enable(SoftDeletedFilter::NAME);
        $this->manager->clear();
        self::assertSame(['b'], array_map(static fn (Book $book): string => $book->id, $this->manager->find(Shelf::class, 's')?->books->toArray() ?? []), 'the shelf comes back with its books');
    }

    /**
     * A to-one relation to a soft-deleted row is not hidden: the foreign key still names it, and loading it under the
     * filter finds no row.
     */
    public function testAToOneRelationToADeletedRowCannotBeLoadedUnderTheFilter(): void
    {
        $shelf = new Shelf('s');
        $this->manager->persist($shelf);
        $this->manager->persist(new Book('b', $shelf));
        $this->manager->flush();
        $this->manager->remove($shelf);
        $this->manager->flush();
        $this->manager->clear();

        $book = $this->manager->find(Book::class, 'b');
        self::assertInstanceOf(Book::class, $book);

        try {
            $book->shelf->isDeleted();
            self::fail('A deleted row is not found under the filter.');
        } catch (EntityNotFoundException) {
        }

        $this->manager->clear();
        $this->manager->getFilters()->disable(SoftDeletedFilter::NAME);
        self::assertTrue($this->manager->find(Book::class, 'b')?->shelf->isDeleted(), 'read with the filter disabled');
    }

    public function testABlameIsFoundAndErased(): void
    {
        $this->manager->persist(new Article('a', 'Bonjour'));
        $this->blame->who = 'grace';
        $this->manager->persist(new Article('b', 'Allo'));
        $this->manager->flush();

        $this->blame->who = 'ada';
        $this->article('b')->title = 'Salut';
        $this->manager->flush();
        $this->manager->remove($this->article('a'));
        $this->manager->flush();
        $this->manager->clear();

        $eraser = new BlameEraser($this->manager);
        self::assertSame(['article' => 2], $eraser->find('ada'), 'the soft-deleted row too');
        self::assertSame(['article' => 1], $eraser->find('grace'));
        self::assertSame([], $eraser->find('nobody'));
        self::assertContains('article', $eraser->tables());
        self::assertContains('post', $eraser->tables(), 'a table that blames nobody yet');
        self::assertNotContains('essay', $eraser->tables(), "a subclass is in its parent's table");
        self::assertNotContains('tag', $eraser->tables());
        self::assertTrue(array_is_list($eraser->tables()));

        self::assertSame(3, $eraser->replace('ada', 'former-member'));
        self::assertSame([], $eraser->find('ada'));
        self::assertSame(1, $eraser->replace('grace'));
        self::assertSame(
            [['a', 'former-member', 'former-member'], ['b', null, 'former-member']],
            $this->manager->getConnection()->fetchAllNumeric('SELECT id, created_by, updated_by FROM article ORDER BY id'),
        );
        self::assertSame(0, $eraser->replace('nobody'));
    }

    public function testABlameInheritedFromAnEntityIsErasedInItsParentsTable(): void
    {
        $this->blame->who = 'ada';
        $this->manager->persist(new Post('p'));
        $this->manager->persist(new Essay('e'));
        $this->manager->flush();

        $eraser = new BlameEraser($this->manager);
        self::assertSame(['post' => 2], $eraser->find('ada'));
        self::assertSame(4, $eraser->replace('ada', 'former-member'));
        self::assertSame([], $eraser->find('ada'));
    }

    public function testEveryBlameableTableIsSearchedInTheOrderOfItsName(): void
    {
        $this->manager = Database::entityManager($this->clock, $this->blame, stringFetches: true);
        $this->blame->who = 'ada';
        $this->manager->persist(new Post('p'));
        $this->manager->persist(new Review('r'));
        $this->manager->persist(new Article('a', 'Bonjour'));
        $this->manager->flush();

        $eraser = new BlameEraser($this->manager);
        self::assertSame(['annotation' => 1, 'article' => 1, 'post' => 1], $eraser->find('ada'), 'a mapped superclass has no table of its own; a count fetched as a string is a number');
        self::assertSame(6, $eraser->replace('ada'));
    }

    private function article(string $id): Article
    {
        $article = $this->manager->find(Article::class, $id);
        self::assertInstanceOf(Article::class, $article);

        return $article;
    }
}
