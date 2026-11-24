<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Infrastructure\Memory;

use Override;
use Phil\CommerceConsignmentBundle\Domain\Contract\ConsignorRepository;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignmentAgreement;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignorPayout;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignorStatement;

/**
 * Agreements, statements and payouts in memory, for tests and for an application that keeps them elsewhere. It keeps
 * what it is given until it is dropped: never share one between requests.
 */
final class InMemoryConsignorRepository implements ConsignorRepository
{
    /** @var list<ConsignmentAgreement> */
    private array $agreements = [];

    /** @var array<string, ConsignorStatement> */
    private array $statements = [];

    /** @var array<string, ConsignorPayout> */
    private array $payouts = [];

    #[Override]
    public function agreementsOf(string $consignor): array
    {
        $agreements = array_filter($this->agreements, static fn (ConsignmentAgreement $agreement): bool => $agreement->consignor === $consignor);
        usort($agreements, static fn (ConsignmentAgreement $left, ConsignmentAgreement $right): int => $left->effectiveFrom() <=> $right->effectiveFrom());

        return $agreements;
    }

    #[Override]
    public function addAgreement(ConsignmentAgreement $agreement): void
    {
        $this->agreements[] = $agreement;
    }

    #[Override]
    public function addStatement(ConsignorStatement $statement): void
    {
        $this->statements[$statement->number] = $statement;
    }

    #[Override]
    public function statement(string $number): ?ConsignorStatement
    {
        return $this->statements[$number] ?? null;
    }

    #[Override]
    public function statementsOf(string $consignor, int $limit): array
    {
        $statements = array_filter($this->statements, static fn (ConsignorStatement $statement): bool => $statement->consignor === $consignor);
        usort($statements, static fn (ConsignorStatement $left, ConsignorStatement $right): int => [$right->issuedAt(), $right->id] <=> [$left->issuedAt(), $left->id]);

        return \array_slice($statements, 0, max(1, $limit));
    }

    #[Override]
    public function addPayout(ConsignorPayout $payout): void
    {
        $this->payouts[$payout->id] = $payout;
    }

    #[Override]
    public function payout(string $id): ?ConsignorPayout
    {
        return $this->payouts[$id] ?? null;
    }

    #[Override]
    public function payoutByKey(string $idempotencyKey): ?ConsignorPayout
    {
        foreach ($this->payouts as $payout) {
            if ($payout->idempotencyKey === $idempotencyKey) {
                return $payout;
            }
        }

        return null;
    }

    #[Override]
    public function reversalOf(string $id): ?ConsignorPayout
    {
        foreach ($this->payouts as $payout) {
            if ($payout->reverses === $id) {
                return $payout;
            }
        }

        return null;
    }

    #[Override]
    public function payoutsOf(string $consignor, string $currency, int $limit): array
    {
        $payouts = array_filter($this->payouts, static fn (ConsignorPayout $payout): bool => $payout->consignor === $consignor && $payout->amount()->currency->code === $currency);
        usort($payouts, static fn (ConsignorPayout $left, ConsignorPayout $right): int => [$left->recordedAt(), $left->id] <=> [$right->recordedAt(), $right->id]);

        return \array_slice($payouts, 0, max(1, $limit));
    }

    #[Override]
    public function paidTo(string $consignor, string $currency): int
    {
        return array_sum(array_map(static fn (ConsignorPayout $payout): int => $payout->amount()->minorUnits, $this->payoutsOf($consignor, $currency, \PHP_INT_MAX)));
    }
}
