<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Tests\Benchmark;

use Doctrine\ORM\EntityManager;
use Generator;
use LogicException;
use Phil\Slug\Slug;
use Phil\TaxonomyBundle\Application\Taxonomy;
use Phil\TaxonomyBundle\Domain\Model\Category;
use Phil\TaxonomyBundle\Infrastructure\Doctrine\DoctrineTaxonomyRepository;
use Phil\TaxonomyBundle\Tests\Benchmark\Fixture\CategoryTree;
use Phil\TaxonomyBundle\Tests\Unit\Fixtures\Database;

/**
 * Moving a category reads every category to find its branch; merging one moves each of its children in turn, so a
 * merge reads the whole tree once per child. Editors run both from the category admin.
 *
 * A merge cannot be undone between revs: each rev merges another of the categories set up for it, so a set holds one
 * such category per rev and warm-up rev (twelve) beside the tree.
 *
 * @BeforeMethods("setUp")
 */
final class CategoryMoveBench
{
    private const int MERGE_POOL = 12;

    private readonly EntityManager $entityManager;

    private readonly Taxonomy $taxonomy;

    /**
     * A category with a branch of its own, moved between two roots.
     */
    private readonly Category $moved;

    /**
     * @var array{Category, Category}
     */
    private readonly array $parents;

    /**
     * @var list<Category>
     */
    private array $merged = [];

    private readonly Category $into;

    private int $rev = 0;

    public function __construct()
    {
        $this->entityManager = Database::entityManager();
        $this->taxonomy = new Taxonomy(new DoctrineTaxonomyRepository($this->entityManager));
        $this->parents = [Category::create(Slug::fromString('root-b')), Category::create(Slug::fromString('root-a'))];
        $this->moved = Category::create(Slug::fromString('moved'), $this->parents[1]);
        $this->into = Category::create(Slug::fromString('merged-into'));

        foreach ([...$this->parents, $this->moved, $this->into] as $category) {
            $this->entityManager->persist($category);
        }

        $this->entityManager->flush();
    }

    /**
     * @param array{categories: int, children?: int} $params
     */
    public function setUp(array $params): void
    {
        CategoryTree::build($this->entityManager, $params['categories']);
        CategoryTree::build($this->entityManager, 5, true, $this->moved, 'moved-child');

        if (isset($params['children'])) {
            for ($pool = 0; $pool < self::MERGE_POOL; ++$pool) {
                $from = Category::create(Slug::fromString('merged-'.$pool));
                $this->entityManager->persist($from);
                CategoryTree::build($this->entityManager, $params['children'], true, $from, 'merged-'.$pool.'-child');
                $this->merged[] = $from;
            }
        }
    }

    /**
     * @ParamProviders("sizes")
     *
     * @Revs(10)
     */
    public function benchMove(): void
    {
        $this->taxonomy->move($this->moved, $this->parents[++$this->rev % 2]);
    }

    /**
     * @ParamProviders("merges")
     *
     * @Revs(1)
     *
     * @Iterations(2)
     */
    public function benchMerge(): void
    {
        $from = $this->merged[$this->rev++] ?? throw new LogicException(\sprintf('A set holds %d categories to merge: lower the revs.', self::MERGE_POOL));
        $this->taxonomy->mergeCategories($from, $this->into);
    }

    /**
     * @return Generator<string, array{categories: int}>
     */
    public function sizes(): Generator
    {
        yield '50 categories' => ['categories' => 50];
        yield '500 categories' => ['categories' => 500];
        yield '2000 categories' => ['categories' => 2_000];
    }

    /**
     * @return Generator<string, array{categories: int, children: int}>
     */
    public function merges(): Generator
    {
        yield '500 categories,5 children' => ['categories' => 500, 'children' => 5];
        yield '500 categories,15 children' => ['categories' => 500, 'children' => 15];
        yield '500 categories,40 children' => ['categories' => 500, 'children' => 40];
    }
}
