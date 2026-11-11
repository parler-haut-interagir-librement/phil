<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Tests\Unit\Application;

use Doctrine\ORM\EntityManager;
use Phil\Resource\ResourceReference;
use Phil\Slug\LeadingWordsRemoved;
use Phil\Slug\TransliteratingSlugger;
use Phil\TaxonomyBundle\Application\CategoryNode;
use Phil\TaxonomyBundle\Application\Taxonomy;
use Phil\TaxonomyBundle\Domain\Exception\InvalidTaxonomy;
use Phil\TaxonomyBundle\Domain\Model\Category;
use Phil\TaxonomyBundle\Domain\Model\CategoryTranslation;
use Phil\TaxonomyBundle\Domain\Model\Tag;
use Phil\TaxonomyBundle\Infrastructure\Doctrine\DoctrineTaxonomyRepository;
use Phil\TaxonomyBundle\Tests\Unit\Fixtures\CountingRepository;
use Phil\TaxonomyBundle\Tests\Unit\Fixtures\Database;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Taxonomy::class)]
#[CoversClass(CategoryNode::class)]
#[CoversClass(DoctrineTaxonomyRepository::class)]
final class TaxonomyTest extends TestCase
{
    private EntityManager $entityManager;

    private Taxonomy $taxonomy;

    protected function setUp(): void
    {
        $this->entityManager = Database::entityManager();
        $this->taxonomy = new Taxonomy(new DoctrineTaxonomyRepository($this->entityManager), 'en');
    }

    public function testTagsAreFoundOrCreatedAndGiven(): void
    {
        $page = ResourceReference::of('page', '1');
        $jeux = $this->taxonomy->tag('Jeux');

        self::assertSame($jeux, $this->taxonomy->tag('JEUX'));
        self::assertSame(['jeux', 'soiree'], $this->slugs($this->taxonomy->setTags($page, ['jeux', 'Soirée', 'soiree'])));
        self::assertSame(['jeux', 'wargames'], $this->slugs($this->taxonomy->setTags($page, ['Wargames', 'jeux'])));
        self::assertSame(['jeux', 'wargames'], $this->slugs($this->taxonomy->tagsOf($page)));
        self::assertSame(['page:1'], $this->references($this->taxonomy->taggedWith($jeux)));
        self::assertSame([], $this->references($this->taxonomy->taggedWith($jeux, 'post')));
        self::assertSame(['page:1'], $this->references($this->taxonomy->taggedWith($jeux, 'page')));
        self::assertSame([], $this->slugs($this->taxonomy->setTags($page, [])));
    }

    public function testTagsAreRenamedAndMerged(): void
    {
        $page = ResourceReference::of('page', '1');
        $post = ResourceReference::of('post', '2');
        $this->taxonomy->setTags($page, ['rpg', 'jdr']);
        $this->taxonomy->setTags($post, ['jdr']);

        $rpg = $this->taxonomy->tag('rpg');
        $jdr = $this->taxonomy->tag('jdr');

        try {
            $this->taxonomy->renameTag($rpg, 'JDR');
            self::fail('Two tags were given one name.');
        } catch (InvalidTaxonomy $invalidTaxonomy) {
            self::assertSame('The tag "jdr" exists: merge the two instead.', $invalidTaxonomy->getMessage());
        }

        $this->taxonomy->renameTag($rpg, 'RPG');
        $this->taxonomy->renameTag($rpg, 'Rpg');

        $this->entityManager->clear();
        $rpg = $this->taxonomy->tag('rpg');
        $jdr = $this->taxonomy->tag('jdr');
        self::assertSame('Rpg', $rpg->name());

        $this->taxonomy->mergeTags($jdr, $rpg);
        self::assertSame(['page:1', 'post:2'], $this->references($this->taxonomy->taggedWith($rpg)));
        self::assertSame([], new DoctrineTaxonomyRepository($this->entityManager)->resourcesWith('tag', [$jdr->id]));
        self::assertSame(['rpg'], $this->slugs($this->taxonomy->tagsOf($page)));
        self::assertNull($this->entityManager->find(Tag::class, $jdr->id));

        $this->expectException(InvalidTaxonomy::class);
        $this->expectExceptionMessageIsOrContains('A tag is not merged into itself.');

        $this->taxonomy->mergeTags($rpg, $rpg);
    }

    public function testCategoriesAreATranslatedTree(): void
    {
        $games = $this->taxonomy->createCategory('games', null, ['en' => 'Games', 'fr' => 'Jeux'], 1);
        $books = $this->taxonomy->createCategory('books', null, ['en' => 'Books'], 0);
        $board = $this->taxonomy->createCategory('board', $games, ['fr' => 'Jeux de société']);
        $this->taxonomy->createCategory('dice', $games, [], 0);
        $this->taxonomy->translate($games, 'fr_CA', 'Jeux (Québec)', 'Les jeux');
        $this->taxonomy->translate($games, 'fr_CA', 'Jeux du Québec');

        self::assertSame(['Jeux du Québec', 'Jeux', 'Games', 'Jeux de société', 'board', 'Jeux de société'], [$this->taxonomy->name($games, 'fr_CA'), $this->taxonomy->name($games, 'fr_FR'), $this->taxonomy->name($games, 'de'), $this->taxonomy->name($board, 'fr_CA'), $this->taxonomy->name($board, 'de'), $this->taxonomy->name($board, 'fr')]);
        self::assertSame([['Books', []], ['Games', [['board', []], ['dice', []]]]], self::tree($this->taxonomy->tree('en')));

        try {
            $this->taxonomy->createCategory('Games');
            self::fail('Two categories were given one key.');
        } catch (InvalidTaxonomy $invalidTaxonomy) {
            self::assertSame('A category has the key "games".', $invalidTaxonomy->getMessage());
        }

        $this->taxonomy->move($games, $books, 3);
        $this->entityManager->clear();
        $board = $this->category('board');
        self::assertSame(2, $board->depth());
        self::assertSame([['Books', [['Games', [['board', []], ['dice', []]]]]]], self::tree($this->taxonomy->tree('en')));
    }

    public function testResourcesAreCategorizedAndFoundByBranch(): void
    {
        $games = $this->taxonomy->createCategory('games');
        $board = $this->taxonomy->createCategory('board', $games);
        $chess = $this->taxonomy->createCategory('chess', $board);
        $books = $this->taxonomy->createCategory('books');
        $page = ResourceReference::of('page', '1');
        $post = ResourceReference::of('post', '2');

        $this->taxonomy->categorize($page, [$chess, $books]);
        $this->taxonomy->categorize($post, [$games]);
        $this->taxonomy->categorize($page, [$chess, $chess]);
        $this->taxonomy->setTags($page, ['jeux']);
        $this->taxonomy->categorize(ResourceReference::of('book', '9'), [$books]);

        self::assertSame(['chess'], array_map(static fn (Category $category): string => $category->key(), $this->taxonomy->categoriesOf($page)));
        self::assertSame(['page:1', 'post:2'], $this->references($this->taxonomy->categorizedIn($games)));
        self::assertSame(['post:2'], $this->references($this->taxonomy->categorizedIn($games, false)));
        self::assertSame(['page:1'], $this->references($this->taxonomy->categorizedIn($games, true, 'page')));
        self::assertSame(['book:9'], $this->references($this->taxonomy->categorizedIn($books)));
    }

    public function testCategoriesAreMerged(): void
    {
        $games = $this->taxonomy->createCategory('games', null, ['en' => 'Games']);
        $board = $this->taxonomy->createCategory('board', $games);
        $jeux = $this->taxonomy->createCategory('jeux');
        $page = ResourceReference::of('page', '1');
        $this->taxonomy->categorize($page, [$games, $jeux]);
        $this->taxonomy->categorize(ResourceReference::of('post', '2'), [$games]);

        foreach ([[$games, $games], [$games, $board]] as [$from, $into]) {
            try {
                $this->taxonomy->mergeCategories($from, $into);
                self::fail('A category was merged into its own branch.');
            } catch (InvalidTaxonomy $invalidTaxonomy) {
                self::assertSame('A category is not merged into itself or its own branch.', $invalidTaxonomy->getMessage());
            }
        }

        $this->taxonomy->mergeCategories($games, $jeux);
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(Category::class, $games->id));
        self::assertSame([['jeux', [['board', []]]]], self::tree($this->taxonomy->tree('en')));
        self::assertSame(['page:1', 'post:2'], $this->references($this->taxonomy->categorizedIn($this->category('jeux'), false)));
        $this->taxonomy->mergeCategories($this->category('board'), $this->category('jeux'));
        self::assertSame([['jeux', []]], self::tree($this->taxonomy->tree('en')));
    }

    public function testAMergeReadsTheTreeOnceAndWritesItsChildrenTogether(): void
    {
        $repository = new CountingRepository(new DoctrineTaxonomyRepository($this->entityManager));
        $taxonomy = new Taxonomy($repository, 'en');
        $from = $taxonomy->createCategory('from');
        $into = $taxonomy->createCategory('into');
        $taxonomy->createCategory('kept', $into);
        $first = $taxonomy->createCategory('first', $from, [], 2);
        $taxonomy->createCategory('deep', $taxonomy->createCategory('under', $first));
        $taxonomy->createCategory('second', $from, [], 1);
        $taxonomy->createCategory('elsewhere');

        $repository->reads = [];
        $repository->saves = [];

        $taxonomy->mergeCategories($from, $into);

        self::assertSame(['categories' => 1], $repository->reads);
        self::assertSame([4], $repository->saves, 'the children and their branches in one flush');
        $this->entityManager->clear();
        self::assertSame([['elsewhere', []], ['into', [['kept', []], ['second', []], ['first', [['under', [['deep', []]]]]]]]], self::tree($taxonomy->tree('en')));
        self::assertSame([2, 3, 2], [$this->category('under')->depth(), $this->category('deep')->depth(), $this->category('first')->position()]);
        self::assertSame($this->category('into')->path().$this->category('first')->id.'/'.$this->category('under')->id.'/', $this->category('under')->path());

        $repository->saves = [];
        $taxonomy->mergeCategories($this->category('elsewhere'), $this->category('into'));
        self::assertSame([0], $repository->saves, 'no children');
    }

    public function testAMergeRefusesABranchThatWouldGoTooDeep(): void
    {
        $from = $this->taxonomy->createCategory('from');
        $branch = $this->taxonomy->createCategory('branch', $from);
        $parent = null;

        for ($depth = 0; $depth < Category::MAX_DEPTH; ++$depth) {
            $parent = $this->taxonomy->createCategory('d'.$depth, $parent);
        }

        $this->taxonomy->createCategory('leaf', $branch);
        self::assertInstanceOf(Category::class, $parent);

        $this->expectException(InvalidTaxonomy::class);
        $this->expectExceptionMessageIsOrContains('at depth');

        $this->taxonomy->mergeCategories($from, $parent);
    }

    public function testNamesReadTheTranslationsOfTheirLanguagesOnly(): void
    {
        $repository = new CountingRepository(new DoctrineTaxonomyRepository($this->entityManager));
        $taxonomy = new Taxonomy($repository, 'en');
        $games = $taxonomy->createCategory('games', null, ['en' => 'Games', 'fr' => 'Jeux', 'fr_CA' => 'Jeux (Québec)', 'de' => 'Spiele', 'es' => 'Juegos']);
        $books = $taxonomy->createCategory('books', null, ['de' => 'Bücher', 'fr' => 'Livres']);
        $this->entityManager->clear();
        $games = $this->category('games');
        $books = $this->category('books');

        self::assertSame([$games->id => 'Jeux (Québec)', $books->id => 'Livres'], $taxonomy->names([$games, $books], 'fr_CA'));
        $loaded = [];

        foreach ($this->entityManager->getUnitOfWork()->getIdentityMap()[CategoryTranslation::class] ?? [] as $translation) {
            self::assertInstanceOf(CategoryTranslation::class, $translation);
            $loaded[] = $translation->locale;
        }

        sort($loaded);
        self::assertSame(['en', 'fr', 'fr', 'fr_CA'], $loaded, 'the other languages are not read');
        self::assertSame([$games->id => 'Games', $books->id => 'books'], $taxonomy->names([$games, $books], 'it'));
        self::assertSame([$games->id => 'Spiele'], $taxonomy->names([$games], 'de_CH'));
        self::assertSame(['en'], array_map(static fn (CategoryTranslation $translation): string => $translation->locale, $repository->translationsOf([$games], ['en', 'it'])));
        self::assertCount(5, $repository->translationsOf([$games]), 'every language without a list');
        self::assertSame([], $repository->translationsOf([$games], []));
    }

    public function testEverythingIsStored(): void
    {
        $tag = $this->taxonomy->tag('Alpha');
        $games = $this->taxonomy->createCategory('games');
        $books = $this->taxonomy->createCategory('books');
        $this->taxonomy->translate($games, 'fr', 'Jeu');
        $this->taxonomy->translate($games, 'fr', 'Jeux');

        $this->entityManager->clear();
        self::assertSame('Jeux', $this->taxonomy->name($this->category('games'), 'fr'));
        [$games, $books] = [$this->category('games'), $this->category('books')];
        $this->taxonomy->move($books, $games, 4);
        $this->taxonomy->move($books, $games);

        $this->entityManager->clear();

        self::assertSame($tag->id, $this->taxonomy->tag('alpha')->id);
        self::assertSame('Jeux', $this->taxonomy->name($this->category('games'), 'fr'));
        self::assertSame([0, 0], [$this->category('games')->position(), $this->category('books')->position()]);
    }

    public function testTagsAreSortedAndTermsThatAreGoneLeftOut(): void
    {
        $page = ResourceReference::of('page', '1');
        self::assertSame(['alpha', 'zeta'], $this->slugs($this->taxonomy->setTags($page, ['zeta', 'alpha'])));

        $first = $this->taxonomy->createCategory('first');
        $gone = $this->taxonomy->createCategory('gone');
        $last = $this->taxonomy->createCategory('last');
        $this->taxonomy->categorize($page, [$first, $gone, $last]);
        $this->taxonomy->setTags($page, ['zeta']);

        $this->entityManager->remove($this->taxonomy->tag('zeta'));
        $this->entityManager->remove($gone);
        $this->entityManager->flush();

        self::assertSame([], $this->taxonomy->tagsOf($page));
        self::assertSame(['first', 'last'], array_map(static fn (Category $category): string => $category->key(), $this->taxonomy->categoriesOf($page)));
        self::assertSame([0, 1], array_keys($this->taxonomy->categoriesOf($page)));
    }

    public function testABranchMovedUnderADeepParentMustFit(): void
    {
        $deep = null;

        for ($depth = 0; $depth <= 9; ++$depth) {
            $deep = $this->taxonomy->createCategory('c'.$depth, $deep);
        }

        $branch = $this->taxonomy->createCategory('b0');
        $this->taxonomy->createCategory('b2', $this->taxonomy->createCategory('b1', $branch));

        try {
            $this->taxonomy->move($branch, $deep);
            self::fail('A branch went deeper than the deepest level.');
        } catch (InvalidTaxonomy $invalidTaxonomy) {
            self::assertSame('A category is at depth 10 at most, a root being at depth 0.', $invalidTaxonomy->getMessage());
        }

        self::assertSame([0, 2], [$branch->depth(), $this->category('b2')->depth()]);
        $this->taxonomy->move($branch, $this->category('c7'));
        self::assertSame([8, 10], [$branch->depth(), $this->category('b2')->depth()]);
        self::assertLessThanOrEqual(420, \strlen($this->category('b2')->path()));

        // The branch's height counts from where it is, not from the root.
        $this->taxonomy->move($branch, $this->category('c6'));
        self::assertSame([7, 9], [$branch->depth(), $this->category('b2')->depth()]);

        // A category without children fits under a parent at depth 9, not under one at depth 10.
        $leaf = $this->taxonomy->createCategory('leaf');
        $this->taxonomy->move($leaf, $deep);
        self::assertSame(10, $leaf->depth());
        $this->taxonomy->move($leaf, null);
        $deepest = $this->taxonomy->createCategory('c10', $deep);

        try {
            $this->taxonomy->move($leaf, $deepest);
            self::fail('A category went deeper than the deepest level.');
        } catch (InvalidTaxonomy $invalidTaxonomy) {
            self::assertSame('A category is at depth 10 at most, a root being at depth 0.', $invalidTaxonomy->getMessage());
        }

        self::assertSame(0, $leaf->depth());
    }

    public function testTreesAndTermsAreReadWithoutAQueryPerItem(): void
    {
        $repository = new CountingRepository(new DoctrineTaxonomyRepository($this->entityManager));
        $taxonomy = new Taxonomy($repository, 'en');
        $page = ResourceReference::of('page', '1');
        $categories = [];

        foreach (['a', 'b', 'c', 'd'] as $key) {
            $categories[] = $taxonomy->createCategory($key, $categories[0] ?? null, ['fr' => strtoupper($key)]);
        }

        $taxonomy->categorize($page, $categories);
        $taxonomy->setTags($page, ['un', 'deux', 'trois']);

        $repository->reads = [];

        self::assertSame([['A', [['B', []], ['C', []], ['D', []]]]], self::tree($taxonomy->tree('fr')));
        self::assertSame(['categories' => 1, 'translationsOf' => 1], $repository->reads);

        $repository->reads = [];
        self::assertCount(3, $taxonomy->tagsOf($page));
        self::assertSame(['a', 'b', 'c', 'd'], array_map(static fn (Category $category): string => $category->key(), $taxonomy->categoriesOf($page)));
        self::assertSame(['assignments' => 2, 'tags' => 1, 'categoriesWithIds' => 1], $repository->reads);
        self::assertSame([[], []], [$repository->tags([]), $repository->categoriesWithIds([])]);
        self::assertSame([], $repository->translationsOf([]));

        // Only what is asked for is read.
        $this->entityManager->clear();
        self::assertSame(['B'], array_map(static fn (CategoryTranslation $translation): string => $translation->name(), $repository->translationsOf([$this->category('b')])));
        $this->entityManager->clear();
        self::assertSame(['a'], array_map(static fn (Category $category): string => $category->key(), $repository->categoriesWithIds([$categories[0]->id])));
        self::assertCount(1, $this->entityManager->getUnitOfWork()->getIdentityMap()[Category::class] ?? []);
    }

    public function testTheSluggerMakesTheSlugsAndKeys(): void
    {
        $taxonomy = new Taxonomy(new DoctrineTaxonomyRepository($this->entityManager), 'en', new LeadingWordsRemoved(new TransliteratingSlugger(), ['the', 'les']));

        self::assertSame('guild', $taxonomy->tag('The Guild')->slug());
        self::assertSame($taxonomy->tag('Guild'), $taxonomy->tag('The Guild'));
        self::assertSame('gardiens', $taxonomy->createCategory('Les Gardiens')->key());
        $tag = $taxonomy->tag('Dice');
        $taxonomy->renameTag($tag, 'The Dice');
        self::assertSame(['The Dice', 'dice'], [$tag->name(), $tag->slug()]);
        self::assertSame(str_repeat('a', 128), $this->taxonomy->createCategory(str_repeat('a', 200))->key());
        self::assertSame(str_repeat('a', 60).'-'.str_repeat('b', 60), $this->taxonomy->createCategory(str_repeat('a', 60).' '.str_repeat('b', 60).' '.str_repeat('c', 60))->key());
    }

    public function testAResourceTakesABoundedNumberOfTags(): void
    {
        $repository = new CountingRepository(new DoctrineTaxonomyRepository($this->entityManager));
        $taxonomy = new Taxonomy($repository);
        $page = ResourceReference::of('page', '1');
        $names = array_map(static fn (int $i): string => 't'.$i, range(1, Taxonomy::MAX_TAGS));

        self::assertCount(Taxonomy::MAX_TAGS, $taxonomy->setTags($page, $names));
        $repository->reads = [];

        try {
            $taxonomy->setTags($page, [...$names, 'one-more']);
            self::fail('A resource took more tags than the limit.');
        } catch (InvalidTaxonomy $invalidTaxonomy) {
            self::assertSame(\sprintf('A resource takes at most %d tags.', Taxonomy::MAX_TAGS), $invalidTaxonomy->getMessage());
        }

        self::assertSame([], $repository->reads, 'refused before any query');
        self::assertCount(Taxonomy::MAX_TAGS, $taxonomy->tagsOf($page), 'the tags given before are kept');
    }

    public function testANameWithNothingToSlugIsRefused(): void
    {
        foreach ([fn (): Tag => $this->taxonomy->tag(' ?! '), fn (): Category => $this->taxonomy->createCategory('—'), fn () => $this->taxonomy->renameTag($this->taxonomy->tag('x'), '!')] as $make) {
            try {
                $make();
                self::fail('A name without a letter made a slug.');
            } catch (InvalidTaxonomy $invalidTaxonomy) {
                self::assertStringEndsWith('holds no letter or digit to make a slug of.', $invalidTaxonomy->getMessage());
            }
        }
    }

    public function testSiblingsAreByPositionThenKey(): void
    {
        $this->taxonomy->createCategory('zz', null, [], 0);
        $aa = $this->taxonomy->createCategory('aa', null, [], 1);
        $this->taxonomy->createCategory('dice', $aa, [], 0);
        $this->taxonomy->createCategory('board', $aa, [], 0);
        $this->taxonomy->createCategory('cards', $aa, [], 1);

        self::assertSame([['zz', []], ['aa', [['board', []], ['dice', []], ['cards', []]]]], self::tree($this->taxonomy->tree('en')));
    }

    private function category(string $key): Category
    {
        $category = $this->entityManager->getRepository(Category::class)->findOneBy(['key' => $key]);
        self::assertInstanceOf(Category::class, $category);

        return $category;
    }

    /**
     * @param list<Tag> $tags
     *
     * @return list<string>
     */
    private function slugs(array $tags): array
    {
        return array_map(static fn (Tag $tag): string => $tag->slug(), $tags);
    }

    /**
     * @param list<ResourceReference> $references
     *
     * @return list<string>
     */
    private function references(array $references): array
    {
        return array_map(static fn (ResourceReference $reference): string => $reference->toString(), $references);
    }

    /**
     * @param list<CategoryNode> $nodes
     *
     * @return list<array{string, list<mixed>}>
     */
    private static function tree(array $nodes): array
    {
        return array_map(static fn (CategoryNode $node): array => [$node->name, self::tree($node->children)], $nodes);
    }
}
