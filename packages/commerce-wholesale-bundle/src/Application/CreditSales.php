<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\CommerceWholesaleBundle\Domain\Exception\WholesaleRefused;
use Phil\CommerceWholesaleBundle\Domain\Model\CreditAccount;
use Phil\CommerceWholesaleBundle\Domain\Model\Receivable;
use Phil\CommerceWholesaleBundle\Domain\Repository\CreditRepository;
use Phil\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * Sales on credit: a buyer's account, purchases on it — payments of the core of method `on_account`, each a
 * receivable due after the terms —, the buyer's payments against them, and what is overdue.
 */
final readonly class CreditSales
{
    public function __construct(
        private Payments $payments,
        private CreditRepository $credit,
    ) {
    }

    public function open(string $buyer, Money $limit, int $termsDays): CreditAccount
    {
        $account = CreditAccount::open($buyer, $limit, $termsDays);
        $this->credit->addAccount($account);

        return $account;
    }

    public function account(string $buyer): CreditAccount
    {
        return $this->credit->findAccount($buyer) ?? throw WholesaleRefused::noAccount($buyer);
    }

    /**
     * Pays part or all of a placed order on the buyer's account.
     *
     * @param Money|null $amount what is left to pay by default
     */
    public function charge(Order $order, string $buyer, DateTimeImmutable $at, ?Money $amount = null): Receivable
    {
        $account = $this->account($buyer);
        $amount ??= $this->payments->summary($order)->payable();
        $account->charge($amount);
        [$payment] = $this->payments->start($order, AccountGateway::ON_ACCOUNT, $at, $amount);
        $payment->apply(PaymentNotification::of(Uuid::v7()->toRfc4122(), $payment->id, PaymentEvent::Captured));
        $receivable = Receivable::of($account, $order->number, $payment->id, $amount, $at);
        $this->credit->addReceivable($receivable);

        return $receivable;
    }

    /**
     * Records the buyer's payment of a receivable — a transfer, a cheque —, up to what is outstanding.
     *
     * @return Money the part applied
     */
    public function receive(string $receivable, Money $amount): Money
    {
        $due = $this->credit->findReceivable($receivable) ?? throw WholesaleRefused::unknownReceivable($receivable);
        $paid = $due->pay($amount);

        if ($paid->isPositive()) {
            $this->account($due->buyer)->reduce($paid);
        }

        return $paid;
    }

    /**
     * @return list<Receivable> the buyer's receivables past their date with something outstanding
     */
    public function overdue(string $buyer, DateTimeImmutable $at): array
    {
        return array_values(array_filter($this->credit->receivablesOf($buyer), static fn (Receivable $receivable): bool => $receivable->isOverdue($at)));
    }

    /**
     * @return list<Receivable> the buyer's receivables on which a refund owes money back to the buyer
     */
    public function refundsDue(string $buyer): array
    {
        return array_values(array_filter($this->credit->receivablesOf($buyer), static fn (Receivable $receivable): bool => $receivable->refundDue()->isPositive()));
    }

    /**
     * Records that the shop gave back to the buyer — a transfer, a cheque — part of what a refund owes on a
     * receivable, up to what is due.
     *
     * @return Money the part settled
     */
    public function settleRefund(string $receivable, Money $amount): Money
    {
        $due = $this->credit->findReceivable($receivable) ?? throw WholesaleRefused::unknownReceivable($receivable);

        return $due->settleRefund($amount);
    }
}
