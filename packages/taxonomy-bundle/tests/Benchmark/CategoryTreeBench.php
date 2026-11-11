<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Tests\Benchmark;

use Doctrine\ORM\EntityManager;
use Generator;
use Phil\TaxonomyBundle\Application\Taxonomy;
use Phil\TaxonomyBundle\Infrastructure\Doctrine\DoctrineTaxonomyRepository;
use Phil\TaxonomyBundle\Infrastructure\Form\CategoryType;
use Phil\TaxonomyBundle\Tests\Benchmark\Fixture\CategoryTree;
use Phil\TaxonomyBundle\Tests\Unit\Fixtures\Database;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;

/**
 * Every form that holds a category field reads the whole tree, names it in the request's language and flattens it
 * into choices; every category menu or page reads the same tree. The entity manager is cleared before each rev, as a
 * new request starts with nothing loaded.
 *
 * @BeforeMethods("setUp")
 */
final readonly class CategoryTreeBench
{
    private EntityManager $entityManager;

    private Taxonomy $taxonomy;

    private FormFactoryInterface $forms;

    public function __construct()
    {
        $this->entityManager = Database::entityManager();
        $this->taxonomy = new Taxonomy(new DoctrineTaxonomyRepository($this->entityManager));
        $this->forms = Forms::createFormFactoryBuilder()->addType(new CategoryType($this->taxonomy))->getFormFactory();
    }

    /**
     * @param array{categories: int, flat: bool} $params
     */
    public function setUp(array $params): void
    {
        CategoryTree::build($this->entityManager, $params['categories'], $params['flat']);
        $this->entityManager->clear();
    }

    /**
     * @ParamProviders({"sizes", "shapes"})
     *
     * @Revs(3)
     *
     * @Iterations(3)
     */
    public function benchTree(): void
    {
        $this->entityManager->clear();
        $this->taxonomy->tree('fr_CA');
    }

    /**
     * The choices and their labels, as rendering the form resolves them.
     *
     * @ParamProviders({"sizes", "shapes"})
     *
     * @Revs(3)
     *
     * @Iterations(3)
     */
    public function benchCategoryField(): void
    {
        $this->entityManager->clear();
        $this->forms->create(CategoryType::class, null, ['locale' => 'fr_CA'])->createView();
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
     * @return Generator<string, array{flat: bool}>
     */
    public function shapes(): Generator
    {
        yield 'nested' => ['flat' => false];
        yield 'flat' => ['flat' => true];
    }
}
