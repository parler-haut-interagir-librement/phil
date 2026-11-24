<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\Contract;

use Phil\CommerceConsignmentBundle\Domain\Model\ConsignmentAgreement;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignorPayout;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignorStatement;

/**
 * Finds and stores what the shop agreed with its consignors, the statements it issued them and the payouts it made.
 * `Infrastructure\Doctrine\DoctrineConsignorRepository` is the Doctrine adapter, `Infrastructure\Memory\InMemoryConsignorRepository`
 * the one in memory.
 */
interface ConsignorRepository
{
    /**
     * @return list<ConsignmentAgreement> every version of a consignor's agreement, the earliest effective first
     */
    public function agreementsOf(string $consignor): array;

    public function addAgreement(ConsignmentAgreement $agreement): void;

    public function addStatement(ConsignorStatement $statement): void;

    public function statement(string $number): ?ConsignorStatement;

    /**
     * @return list<ConsignorStatement> a consignor's statements, the last issued first, at most `$limit`
     */
    public function statementsOf(string $consignor, int $limit): array;

    public function addPayout(ConsignorPayout $payout): void;

    public function payout(string $id): ?ConsignorPayout;

    public function payoutByKey(string $idempotencyKey): ?ConsignorPayout;

    /**
     * The payout that reverses the one named; null when it stands.
     */
    public function reversalOf(string $id): ?ConsignorPayout;

    /**
     * @return list<ConsignorPayout> a consignor's payouts in a currency, reversals included, the earliest recorded first, at
     *                               most `$limit`
     */
    public function payoutsOf(string $consignor, string $currency, int $limit): array;

    /**
     * What the shop paid a consignor in a currency, reversals deducted, in minor units.
     */
    public function paidTo(string $consignor, string $currency): int;
}
