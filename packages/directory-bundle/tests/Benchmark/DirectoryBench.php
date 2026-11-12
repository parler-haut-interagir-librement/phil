<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Tests\Benchmark;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Generator;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\DirectoryBundle\Application\Actor;
use Phil\DirectoryBundle\Application\Directory;
use Phil\DirectoryBundle\Tests\Benchmark\Fixture\BenchKernel;
use RuntimeException;

/**
 * The page of a listing shows its related listings: those it points at, read by their identifiers, and those that
 * point at it, looked up among the live rows of the other genre then read by their identifiers. The list of a genre
 * reads its published listings too.
 *
 * Ten larp sites, and larps each played at one of them. The entity manager is cleared before each read, as a new
 * request starts with an empty identity map.
 *
 * @BeforeMethods("setUp")
 *
 * @Revs(5)
 *
 * @Iterations(5)
 */
final class DirectoryBench
{
    private readonly BenchKernel $kernel;

    private readonly Directory $directory;

    private readonly EntityManagerInterface $entityManager;

    private ContentReference $site;

    private ContentReference $larp;

    public function __construct()
    {
        $this->kernel = new BenchKernel('bench', false);
        $this->kernel->boot();

        $directory = $this->service('bench.directory');
        $entityManager = $this->service('bench.entity_manager');

        if (!$directory instanceof Directory || !$entityManager instanceof EntityManagerInterface) {
            throw new RuntimeException('The directory and the entity manager are services of the kernel.');
        }

        $this->directory = $directory;
        $this->entityManager = $entityManager;
        $this->site = ContentReference::of(Directory::RESOURCE_KEY, 'none');
        $this->larp = $this->site;
    }

    /**
     * @param array{larps: int} $params
     */
    public function setUp(array $params): void
    {
        $directory = $this->directory;
        $entityManager = $this->entityManager;
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $admin = new Actor('admin', true);
        $sites = [];

        for ($index = 0; $index < 10; ++$index) {
            $sites[] = $directory->create('larp_site', 'fr', ['excerptTitle' => 'Site '.$index, 'slug' => 'site-'.$index], $admin);
        }

        for ($index = 0; $index < $params['larps']; ++$index) {
            $this->larp = $directory->create('larp', 'fr', [
                'excerptTitle' => 'Larp '.$index,
                'slug' => 'larp-'.$index,
                'related' => ['played_at' => [$sites[$index % 10]->resourceId]],
            ], $admin);

            // A flush computes the changes of every managed entity: cleared, the setup stays linear.
            if (0 === $index % 20) {
                $entityManager->clear();
            }
        }

        $this->site = $sites[0];
        $entityManager->clear();
    }

    /**
     * The page of a larp site: the larps played there, looked up among the live rows of the larps.
     *
     * @ParamProviders("sizes")
     */
    public function benchRelatedOfASite(): void
    {
        $this->entityManager->clear();
        $this->directory->related($this->site, 'fr');
    }

    /**
     * The page of a larp: the site it is played at, read by its identifier.
     *
     * @ParamProviders("sizes")
     */
    public function benchRelatedOfALarp(): void
    {
        $this->entityManager->clear();
        $this->directory->related($this->larp, 'fr');
    }

    /**
     * Every published larp, as the list of the genre reads them.
     *
     * @ParamProviders("sizes")
     */
    public function benchPublished(): void
    {
        $this->entityManager->clear();
        $this->directory->published('larp', 'fr');
    }

    /**
     * @return Generator<string, array{larps: int}>
     */
    public function sizes(): Generator
    {
        yield '10 larps' => ['larps' => 10];
        yield '100 larps' => ['larps' => 100];
        yield '500 larps' => ['larps' => 500];
    }

    private function service(string $id): object
    {
        return $this->kernel->getContainer()->get($id);
    }
}
