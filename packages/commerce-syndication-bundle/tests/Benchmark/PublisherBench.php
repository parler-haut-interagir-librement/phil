<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Benchmark;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Generator;
use Phil\CommerceSyndicationBundle\Application\Publisher;
use Phil\CommerceSyndicationBundle\Infrastructure\Doctrine\DoctrineSyndicationRepository;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\Database;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\Partners;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * Every stock or price change of a product sold here is published: the resellers fed the SKU are found through the
 * index of fed SKUs, the last price each was sent is read in one query, and each gets a message.
 *
 * The repositories are Doctrine's on SQLite in memory; each rev starts from a cleared entity manager, as a request
 * does. Half the resellers are fed the SKU, last in their list; its price has not changed since the last
 * publication, so no new price is recorded.
 *
 * @BeforeMethods("setUp")
 */
final readonly class PublisherBench
{
    private EntityManager $entityManager;

    private DoctrineSyndicationRepository $repository;

    private Publisher $publisher;

    private Money $retail;

    private DateTimeImmutable $at;

    public function __construct()
    {
        $this->at = new DateTimeImmutable('2026-11-03 10:00:00');
        $this->retail = Money::of(2_000, Currency::of('CAD'));
        $this->entityManager = Database::entityManager();
        $this->repository = new DoctrineSyndicationRepository($this->entityManager);
        $this->publisher = new Publisher($this->repository, $this->repository, $this->repository);
    }

    /**
     * @param array{resellers: int, skus: int} $params
     */
    public function setUp(array $params): void
    {
        $repository = $this->repository;

        $others = array_map(static fn (int $index): string => 'SKU-'.$index, range(1, $params['skus'] - 1));

        for ($reseller = 0; $reseller < $params['resellers']; ++$reseller) {
            $skus = 0 === $reseller % 2 ? [...$others, 'TEA-1'] : [...$others, 'MUG-1'];
            $repository->addPartner(Partners::reseller('reseller-'.$reseller, $skus, 250_000 + $reseller));
        }

        $this->entityManager->flush();
        $this->publisher->publish('TEA-1', 'Green tea', $this->retail, 'food', 10, true, $this->at->modify('-1 day'));
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * @Revs(10)
     *
     * @Iterations(5)
     *
     * @ParamProviders("feeds")
     */
    public function benchPublish(): void
    {
        $this->entityManager->clear();
        $this->publisher->publish('TEA-1', 'Green tea', $this->retail, 'food', 9, true, $this->at);
    }

    /**
     * @return Generator<string, array{resellers: int, skus: int}>
     */
    public function feeds(): Generator
    {
        yield '4 resellers, 20 SKUs' => ['resellers' => 4, 'skus' => 20];
        yield '20 resellers, 200 SKUs' => ['resellers' => 20, 'skus' => 200];
        yield '100 resellers, 2000 SKUs' => ['resellers' => 100, 'skus' => 2_000];
    }
}
