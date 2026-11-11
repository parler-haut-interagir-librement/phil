<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Tests\Benchmark;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Generator;
use Phil\ReferencesBundle\Domain\ValueObject\Usage;
use Phil\ReferencesBundle\Infrastructure\Doctrine\DbalUsageRepository;
use Phil\ReferencesBundle\Infrastructure\Doctrine\UsageTable;
use Phil\Resource\ResourceReference;

/**
 * Every flush of a source that holds links replaces its usages: one read of the stored keys, then a delete per usage
 * gone and an insert per usage new, in one transaction. A page with many blocks pays it on every save.
 *
 * @BeforeMethods("setUp")
 */
final class UsageReplaceBench
{
    private readonly DbalUsageRepository $usages;

    private readonly ResourceReference $source;

    /**
     * Two versions of the source's usages, which a rev alternates between.
     *
     * @var array{list<Usage>, list<Usage>}
     */
    private array $versions = [[], []];

    private int $rev = 0;

    public function __construct()
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach ($connection->getDatabasePlatform()->getCreateTablesSQL(UsageTable::configure(new Schema())->getTables()) as $sql) {
            $connection->executeStatement($sql);
        }

        $this->usages = new DbalUsageRepository($connection);
        $this->source = ResourceReference::of('page', '42');
    }

    /**
     * @param array{links: int, change: string} $params
     */
    public function setUp(array $params): void
    {
        // Other sources fill the table, so that the reads go through its indexes as they would in production.
        for ($page = 0; $page < 200; ++$page) {
            $other = ResourceReference::of('page', 'other-'.$page);
            $this->usages->replace($other, $this->links($other, 20, 0));
        }

        $first = $this->links($this->source, $params['links'], 0);
        $second = match ($params['change']) {
            'unchanged' => $first,
            'one link' => [...\array_slice($first, 0, -1), ...$this->links($this->source, 1, $params['links'])],
            default => $this->links($this->source, $params['links'], $params['links']),
        };

        // Every link is found twice, as a block repeated in a page would give it.
        $this->versions = [[...$first, ...$first], [...$second, ...$second]];
        $this->usages->replace($this->source, $this->versions[0]);
    }

    /**
     * @ParamProviders({"sizes", "changes"})
     *
     * @Revs(20)
     */
    public function benchReplace(): void
    {
        $this->usages->replace($this->source, $this->versions[++$this->rev % 2]);
    }

    /**
     * @return Generator<string, array{links: int}>
     */
    public function sizes(): Generator
    {
        yield '10 links' => ['links' => 10];
        yield '100 links' => ['links' => 100];
        yield '500 links' => ['links' => 500];
    }

    /**
     * @return Generator<string, array{change: string}>
     */
    public function changes(): Generator
    {
        yield 'unchanged' => ['change' => 'unchanged'];
        yield 'one link' => ['change' => 'one link'];
        yield 'every link' => ['change' => 'every link'];
    }

    /**
     * @return list<Usage>
     */
    private function links(ResourceReference $source, int $count, int $from): array
    {
        $links = [];

        for ($i = $from; $i < $from + $count; ++$i) {
            $links[] = Usage::of($source, 'blocks.'.$i.'.image', ResourceReference::of('image', 'image-'.$i));
        }

        return $links;
    }
}
