<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Application;

use DateTimeImmutable;
use Phil\CommerceConsignmentBundle\Domain\Contract\ConsignorRepository;
use Phil\CommerceConsignmentBundle\Domain\Contract\LedgerRepository;
use Phil\CommerceConsignmentBundle\Domain\Exception\ConsignmentRefused;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignorPayout;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignorBalance;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * What the shop owes its consignors and what it paid them. The bundle moves no money: the application pays — a transfer,
 * a cheque, cash — and writes the payout here, partial or whole. A payout is never changed: its reversal cancels it.
 *
 * Nothing is committed here: the caller flushes.
 */
final readonly class ConsignorPayouts
{
    public function __construct(
        private LedgerRepository $ledger,
        private ConsignorRepository $consignors,
    ) {
    }

    /**
     * Writes a payout of at most the balance owed. A key already written refuses it: the same payout written twice by
     * mistake, a form sent again.
     */
    public function record(string $idempotencyKey, Owner $consignor, Money $amount, DateTimeImmutable $paidAt, string $method, ?string $reference, DateTimeImmutable $at): ConsignorPayout
    {
        $payout = ConsignorPayout::record($idempotencyKey, $consignor, $amount, $paidAt, $method, $reference, $at);
        $this->checkKey($payout->idempotencyKey);
        $balance = $this->balance($consignor, $amount->currency)->balance;

        if ($amount->isGreaterThan($balance)) {
            throw ConsignmentRefused::beyondBalance($payout->consignor, $amount->toDecimal(), $balance->toDecimal());
        }

        $this->consignors->addPayout($payout);

        return $payout;
    }

    /**
     * Cancels a payout written by mistake with its reversal: a payout of the opposite amount, written at the instant.
     */
    public function reverse(string $payoutId, string $idempotencyKey, DateTimeImmutable $at): ConsignorPayout
    {
        $payout = $this->consignors->payout($payoutId) ?? throw ConsignmentRefused::unknownPayout($payoutId);

        if ($this->consignors->reversalOf($payout->id) instanceof ConsignorPayout) {
            throw ConsignmentRefused::alreadyReversed($payout->id);
        }

        $reversal = $payout->reversal($idempotencyKey, $at);
        $this->checkKey($reversal->idempotencyKey);
        $this->consignors->addPayout($reversal);

        return $reversal;
    }

    /**
     * What the shop owes a consignor in a currency, from the whole ledger, against what it paid them.
     */
    public function balance(Owner $consignor, Currency $currency): ConsignorBalance
    {
        if (!$consignor->isConsigned()) {
            throw ConsignmentRefused::notConsigned('A balance');
        }

        return ConsignorBalance::of(
            $consignor,
            Money::of($this->ledger->owedTo($consignor->key(), $currency->code), $currency),
            Money::of($this->consignors->paidTo($consignor->key(), $currency->code), $currency),
        );
    }

    private function checkKey(string $idempotencyKey): void
    {
        if ($this->consignors->payoutByKey($idempotencyKey) instanceof ConsignorPayout) {
            throw ConsignmentRefused::duplicatePayout($idempotencyKey);
        }
    }
}
