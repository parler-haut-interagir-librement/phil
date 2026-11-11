<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Tests\Unit\Domain;

use Phil\Resource\ResourceReference;
use Phil\Slug\Slug;
use Phil\TaxonomyBundle\Domain\Exception\InvalidTaxonomy;
use Phil\TaxonomyBundle\Domain\Exception\TaxonomyException;
use Phil\TaxonomyBundle\Domain\Model\Assignment;
use Phil\TaxonomyBundle\Domain\Model\Category;
use Phil\TaxonomyBundle\Domain\Model\CategoryTranslation;
use Phil\TaxonomyBundle\Domain\Model\Tag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Tag::class)]
#[CoversClass(Category::class)]
#[CoversClass(CategoryTranslation::class)]
#[CoversClass(Assignment::class)]
#[CoversClass(ResourceReference::class)]
#[CoversClass(InvalidTaxonomy::class)]
final class ModelTest extends TestCase
{
    public function testATagIsItsSlug(): void
    {
        $tag = Tag::named('  Jeux de Rôle ', Slug::fromString('jeux-de-role'));

        self::assertSame(['Jeux de Rôle', 'jeux-de-role'], [$tag->name(), $tag->slug()]);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $tag->id);
        $tag->rename('Wargames', Slug::fromString('wargames'));
        self::assertSame(['Wargames', 'wargames'], [$tag->name(), $tag->slug()]);
        self::assertSame(str_repeat('é', 100), Tag::named(str_repeat('é', 100), Slug::fromString('e'))->name());
        self::assertSame(str_repeat('a', 128), Tag::named('a', Slug::fromString(str_repeat('a', 128)))->slug());
        self::assertSame('x', Tag::named(' x ', Slug::fromString('x'))->name());

        foreach ([
            [static fn (): Tag => Tag::named(str_repeat('é', 101), Slug::fromString('e')), 'A tag is 1 to 100 characters.'],
            [static fn (): Tag => Tag::named('  ', Slug::fromString('e')), 'A tag is 1 to 100 characters.'],
            [static fn (): Tag => Tag::named('a', Slug::fromString(str_repeat('a', 129))), 'The slug of a tag is 128 characters or fewer; "'.str_repeat('a', 129).'" is not.'],
            [static fn () => Tag::named('a', Slug::fromString('a'))->rename('b', Slug::fromString(str_repeat('b', 129))), 'The slug of a tag is 128 characters or fewer; "'.str_repeat('b', 129).'" is not.'],
            [static fn (): Category => Category::create(Slug::fromString(str_repeat('c', 129))), 'The key of a category is 128 characters or fewer; "'.str_repeat('c', 129).'" is not.'],
            [static fn () => Category::create(Slug::fromString('c'))->rekey(Slug::fromString(str_repeat('d', 129))), 'The key of a category is 128 characters or fewer; "'.str_repeat('d', 129).'" is not.'],
        ] as [$make, $message]) {
            try {
                $make();
                self::fail($message);
            } catch (InvalidTaxonomy $invalidTaxonomy) {
                self::assertSame($message, $invalidTaxonomy->getMessage());
                self::assertInstanceOf(TaxonomyException::class, $invalidTaxonomy);
            }
        }
    }

    public function testCategoriesFormATree(): void
    {
        $games = Category::create(Slug::fromString('games'));
        $board = Category::create(Slug::fromString('board-games'), $games, 2);
        $chess = Category::create(Slug::fromString('chess'), $board);

        self::assertSame(['games', null, '/'.$games->id.'/', 0, 0], [$games->key(), $games->parent(), $games->path(), $games->position(), $games->depth()]);
        self::assertSame(['board-games', $games, '/'.$games->id.'/'.$board->id.'/', 2, 1], [$board->key(), $board->parent(), $board->path(), $board->position(), $board->depth()]);
        self::assertSame(2, $chess->depth());
        self::assertTrue($games->isAncestorOf($chess));
        self::assertFalse($chess->isAncestorOf($games));
        self::assertFalse($games->isAncestorOf($games));

        $books = Category::create(Slug::fromString('books'));
        $board->moveTo($books, 5);
        self::assertSame(['/'.$books->id.'/'.$board->id.'/', 5], [$board->path(), $board->position()]);
        $chess->followParent();
        self::assertSame('/'.$books->id.'/'.$board->id.'/'.$chess->id.'/', $chess->path());
        $board->moveTo(null);
        $chess->followParent();
        self::assertSame(['/'.$board->id.'/', 0, null], [$board->path(), $board->position(), $board->parent()]);
        $games->rekey(Slug::fromString('jeux'));
        self::assertSame(str_repeat('k', 128), Category::create(Slug::fromString(str_repeat('k', 128)))->key());
        self::assertSame('jeux', $games->key());

        foreach ([[$board, $board], [$board, $chess]] as [$moved, $under]) {
            try {
                $moved->moveTo($under);
                self::fail('A category moved under its own branch.');
            } catch (InvalidTaxonomy $invalidTaxonomy) {
                self::assertSame('A category cannot move under itself or one of its descendants.', $invalidTaxonomy->getMessage());
            }
        }
    }

    public function testACategoryIsAtDepthTenAtMost(): void
    {
        $category = Category::create(Slug::fromString('c0'));

        for ($depth = 1; $depth <= Category::MAX_DEPTH; ++$depth) {
            $category = Category::create(Slug::fromString('c'.$depth), $category);
        }

        self::assertSame(10, $category->depth());
        self::assertSame(10, Category::MAX_DEPTH);
        $this->expectException(InvalidTaxonomy::class);
        $this->expectExceptionMessageIsOrContains('A category is at depth 10 at most, a root being at depth 0.');

        Category::create(Slug::fromString('c11'), $category);
    }

    public function testABranchMovedToTheRootFitsWhenItsHeightDoes(): void
    {
        $category = Category::create(Slug::fromString('games'), Category::create(Slug::fromString('all')));

        $category->moveTo(null, 0, Category::MAX_DEPTH);
        self::assertSame(0, $category->depth());

        $this->expectException(InvalidTaxonomy::class);
        $this->expectExceptionMessageIsOrContains('A category is at depth 10 at most, a root being at depth 0.');

        $category->moveTo(null, 0, Category::MAX_DEPTH + 1);
    }

    public function testTranslationsAndAssignments(): void
    {
        $games = Category::create(Slug::fromString('games'));
        $french = CategoryTranslation::of($games, 'fr_CA', '  Jeux ', '  Tous les jeux ');

        self::assertSame([$games, 'fr_CA', 'Jeux', 'Tous les jeux'], [$french->category, $french->locale, $french->name(), $french->description()]);
        $french->change('Jeux de société', '  ');
        self::assertSame(['Jeux de société', null], [$french->name(), $french->description()]);
        self::assertNull(CategoryTranslation::of($games, 'fr', 'Jeux')->description());
        self::assertSame(str_repeat('é', 150), CategoryTranslation::of($games, 'ast', str_repeat('é', 150))->name());

        foreach ([['1fr', 'Jeux', 'A locale is a language, and maybe a region: "fr" or "fr_CA"; "1fr" is not.'], ['fr-CA', 'Jeux', 'A locale is a language, and maybe a region: "fr" or "fr_CA"; "fr-CA" is not.'], ['f', 'Jeux', 'A locale is a language, and maybe a region: "fr" or "fr_CA"; "f" is not.'], ["fr\n", 'Jeux', "A locale is a language, and maybe a region: \"fr\" or \"fr_CA\"; \"fr\n\" is not."], ['fr', ' ', 'A category name is 1 to 150 characters.'], ['fr', str_repeat('é', 151), 'A category name is 1 to 150 characters.']] as [$locale, $name, $message]) {
            try {
                CategoryTranslation::of($games, $locale, $name);
                self::fail($message);
            } catch (InvalidTaxonomy $invalidTaxonomy) {
                self::assertSame($message, $invalidTaxonomy->getMessage());
            }
        }

        $page = ResourceReference::of('page', '1');
        $tag = Tag::named('jeux', Slug::fromString('jeux'));
        $byTag = Assignment::ofTag($tag, $page);
        $byCategory = Assignment::ofCategory($games, $page);
        self::assertSame(['tag', $tag->id, 'page', '1', 'page:1'], [$byTag->termType, $byTag->termId, $byTag->resourceKind, $byTag->resourceId, $byTag->resource()->toString()]);
        self::assertSame(['category', $games->id], [$byCategory->termType, $byCategory->termId]);
        self::assertSame(['tag', 'category'], [Assignment::TAG, Assignment::CATEGORY]);
        self::assertNotSame($byTag->id, $byCategory->id);
    }
}
