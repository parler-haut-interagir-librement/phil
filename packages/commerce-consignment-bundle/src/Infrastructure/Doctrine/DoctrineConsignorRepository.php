<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceConsignmentBundle\Domain\Contract\ConsignorRepository;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignmentAgreement;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignorPayout;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignorStatement;

/**
 * Agreements, statements and payouts in three tables: `phil_commerce_consignment_agreement`,
 * `phil_commerce_consignment_statement` and `phil_commerce_consignment_payout`.
 *
 * Nothing is flushed here. A lookup by key, and the sum of what was paid, count what this request persisted and has not
 * flushed yet; the unique constraints of the tables refuse, at the flush, a payout another request wrote meanwhile.
 */
final readonly class DoctrineConsignorRepository implements ConsignorRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function agreementsOf(string $consignor): array
    {
        $agreements = $this->entityManager->getRepository(ConsignmentAgreement::class)->findBy(['consignor' => $consignor]);

        foreach ($this->pending(ConsignmentAgreement::class) as $agreement) {
            if ($agreement->consignor === $consignor) {
                $agreements[] = $agreement;
            }
        }

        usort($agreements, static fn (ConsignmentAgreement $left, ConsignmentAgreement $right): int => $left->effectiveFrom() <=> $right->effectiveFrom());

        return $agreements;
    }

    #[Override]
    public function addAgreement(ConsignmentAgreement $agreement): void
    {
        $this->entityManager->persist($agreement);
    }

    #[Override]
    public function addStatement(ConsignorStatement $statement): void
    {
        $this->entityManager->persist($statement);
    }

    #[Override]
    public function statement(string $number): ?ConsignorStatement
    {
        foreach ($this->pending(ConsignorStatement::class) as $statement) {
            if ($statement->number === $number) {
                return $statement;
            }
        }

        return $this->entityManager->getRepository(ConsignorStatement::class)->findOneBy(['number' => $number]);
    }

    #[Override]
    public function statementsOf(string $consignor, int $limit): array
    {
        return $this->entityManager->getRepository(ConsignorStatement::class)->findBy(['consignor' => $consignor], ['issuedAt' => 'DESC', 'id' => 'DESC'], max(1, $limit));
    }

    #[Override]
    public function addPayout(ConsignorPayout $payout): void
    {
        $this->entityManager->persist($payout);
    }

    #[Override]
    public function payout(string $id): ?ConsignorPayout
    {
        return $this->entityManager->find(ConsignorPayout::class, $id);
    }

    #[Override]
    public function payoutByKey(string $idempotencyKey): ?ConsignorPayout
    {
        return $this->onePayout(static fn (ConsignorPayout $payout): bool => $payout->idempotencyKey === $idempotencyKey, ['idempotencyKey' => $idempotencyKey]);
    }

    #[Override]
    public function reversalOf(string $id): ?ConsignorPayout
    {
        return $this->onePayout(static fn (ConsignorPayout $payout): bool => $payout->reverses === $id, ['reverses' => $id]);
    }

    #[Override]
    public function payoutsOf(string $consignor, string $currency, int $limit): array
    {
        return $this->entityManager->getRepository(ConsignorPayout::class)->findBy(['consignor' => $consignor, 'currency' => $currency], ['recordedAt' => 'ASC', 'id' => 'ASC'], max(1, $limit));
    }

    #[Override]
    public function paidTo(string $consignor, string $currency): int
    {
        $paid = (int) $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(payout.amount), 0)')
            ->from(ConsignorPayout::class, 'payout')
            ->where('payout.consignor = :consignor AND payout.currency = :currency')
            ->setParameter('consignor', $consignor)
            ->setParameter('currency', $currency)
            ->getQuery()
            ->getSingleScalarResult();

        foreach ($this->pending(ConsignorPayout::class) as $payout) {
            if ($payout->consignor === $consignor && $payout->amount()->currency->code === $currency) {
                $paid += $payout->amount()->minorUnits;
            }
        }

        return $paid;
    }

    /**
     * @param callable(ConsignorPayout): bool $match
     * @param array<string, string>           $criteria
     */
    private function onePayout(callable $match, array $criteria): ?ConsignorPayout
    {
        foreach ($this->pending(ConsignorPayout::class) as $payout) {
            if ($match($payout)) {
                return $payout;
            }
        }

        return $this->entityManager->getRepository(ConsignorPayout::class)->findOneBy($criteria);
    }

    /**
     * The entities of a class persisted and not flushed yet.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    private function pending(string $class): array
    {
        return array_values(array_filter($this->entityManager->getUnitOfWork()->getScheduledEntityInsertions(), static fn (object $entity): bool => $entity instanceof $class));
    }
}
