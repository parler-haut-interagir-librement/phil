<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Infrastructure\Logistics;

use Override;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceLogisticsBundle\Application\Contract\ShipmentAuthorization;
use Phil\CommerceWholesaleBundle\Domain\Model\CreditAccount;
use Phil\CommerceWholesaleBundle\Domain\Repository\CreditRepository;

/**
 * Lets a wholesale order leave before it is paid when its buyer — the order's customer — has an active credit account,
 * in the order's currency, whose available credit covers what is left to pay. It only answers: the purchase is owed
 * once `CreditSales::charge()` puts it on the account, which the application does when it invoices.
 *
 * Registered with phil/commerce-logistics-bundle, as one more authorization beside the payments.
 */
final readonly class CreditShipmentAuthorization implements ShipmentAuthorization
{
    public function __construct(
        private Payments $payments,
        private CreditRepository $credit,
    ) {
    }

    #[Override]
    public function authorizes(Order $order): bool
    {
        $buyer = $order->customer();
        $account = null === $buyer ? null : $this->credit->findAccount($buyer);

        if (!$account instanceof CreditAccount || !$account->isActive() || !$account->currency()->equals($order->total()->currency)) {
            return false;
        }

        return !$this->payments->summary($order)->payable()->isGreaterThan($account->available());
    }
}
