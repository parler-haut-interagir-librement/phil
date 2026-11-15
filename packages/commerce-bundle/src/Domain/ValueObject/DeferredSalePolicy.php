<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\CommerceBundle\Domain\Deferred\BalanceTrigger;
use Phil\CommerceBundle\Domain\Deferred\Fulfilment;
use Phil\CommerceBundle\Domain\Deferred\StockCommitment;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * How a sale is paid later: what is asked first, what calls the balance, when the stock is committed and when the
 * goods may leave. Preorders, installments and layaways are three settings of this one policy; an order has at most
 * one deferred sale, so one deferred due date.
 *
 * With the `Date` trigger, the due date is given when the sale opens, or counted in days from then.
 */
final readonly class DeferredSalePolicy
{
    /** The farthest a due date is counted, in days. */
    public const int MAX_DAYS = 3_650;

    public function __construct(
        public Deposit $deposit,
        public BalanceTrigger $trigger,
        public StockCommitment $commitment,
        public Fulfilment $fulfilment,
        public ?int $dueAfterDays = null,
    ) {
        if (null === $dueAfterDays) {
            return;
        }

        if (BalanceTrigger::Date !== $trigger) {
            throw InvalidValue::because(\sprintf('Only a balance called by a date is due after a number of days, not one called by %s.', $trigger->value));
        }

        if ($dueAfterDays < 1 || $dueAfterDays > self::MAX_DAYS) {
            throw InvalidValue::because(\sprintf('A balance is due 1 to %d days after the sale opens, not %d.', self::MAX_DAYS, $dueAfterDays));
        }
    }

    /**
     * A preorder: the goods are not there yet; the balance is called when they arrive — or ship, or on their release
     * date —, and they leave once paid. The stock is committed once paid, unless the shop holds it from the order.
     */
    public static function preorder(Deposit $deposit, BalanceTrigger $trigger = BalanceTrigger::Availability, StockCommitment $commitment = StockCommitment::OnBalance, ?int $dueAfterDays = null): self
    {
        return new self($deposit, $trigger, $commitment, Fulfilment::OnBalance, $dueAfterDays);
    }

    /**
     * Installments: the buyer takes the goods once the deposit is paid and pays the balance by the due date.
     */
    public static function installments(Deposit $deposit, int $dueAfterDays): self
    {
        return new self($deposit, BalanceTrigger::Date, StockCommitment::OnOrder, Fulfilment::OnDeposit, $dueAfterDays);
    }

    /**
     * A layaway: the goods are put aside from the order, and leave once the balance is paid by the due date.
     */
    public static function layaway(Deposit $deposit, int $dueAfterDays): self
    {
        return new self($deposit, BalanceTrigger::Date, StockCommitment::OnOrder, Fulfilment::OnBalance, $dueAfterDays);
    }
}
