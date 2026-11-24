<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Phil\ReviewsBundle\Application\Reviews;
use Phil\ReviewsBundle\Domain\Status\ReviewOrder;
use Phil\ReviewsBundle\Infrastructure\Doctrine\DbalReviewRepository;
use Phil\ReviewsBundle\Tests\Unit\Fixture\RecordingLogger;
use Phil\ReviewsBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Reviews::class)]
#[CoversClass(DbalReviewRepository::class)]
final class QueriesTest extends TestCase
{
    private RecordingLogger $logger;

    private Store $store;

    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
        $configuration = new Configuration();
        $configuration->setMiddlewares([new Middleware($this->logger)]);

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        Store::create($connection);
        $this->store = new Store($connection);
    }

    public function testAPageStartsAtTheFirstReviewUnlessAskedOtherwise(): void
    {
        $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(4));
        $this->store->reviews->of(Store::game(), ReviewOrder::Newest, 5);
        $this->store->reviews->of(Store::game(), ReviewOrder::Newest, 5, 10);

        self::assertSame([' LIMIT 5 OFFSET 0', ' LIMIT 5 OFFSET 10'], array_map(static fn (string $sql): string => substr($sql, (int) strrpos($sql, ' LIMIT')), $this->queries('OFFSET')));
    }

    public function testTheRepositoryStartsAtTheFirstReviewToo(): void
    {
        $this->store->repository->published(Store::game(), ReviewOrder::Newest, 5);

        self::assertSame([' LIMIT 5 OFFSET 0'], array_map(static fn (string $sql): string => substr($sql, (int) strrpos($sql, ' LIMIT')), $this->queries('OFFSET')));
    }

    public function testTheScoresAndLabelsAreReadOnlyForTheReviewsOfThePage(): void
    {
        $this->store->reviews->write(Store::game(), Store::member('ada'), Store::content(4, ['rules' => 3], null, ['quick']));
        $this->logger->records = [];

        $this->store->reviews->of(Store::game(), ReviewOrder::Newest, 5);

        self::assertCount(1, $this->queries('FROM phil_review_score WHERE review_id IN ('));
        self::assertCount(1, $this->queries('FROM phil_review_superlative WHERE review_id IN ('));
    }

    /**
     * @return list<string>
     */
    private function queries(string $containing): array
    {
        $sqls = [];

        foreach ($this->logger->records as $record) {
            $sql = $record['context']['sql'] ?? null;

            if (\is_string($sql) && str_contains($sql, $containing)) {
                $sqls[] = $sql;
            }
        }

        return $sqls;
    }
}
