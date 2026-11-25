<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceLogisticsBundle\Application\Contract\ShipmentAuthorization;
use Phil\CommerceLogisticsBundle\Application\Contract\ShipmentForcing;

/**
 * An authorization and a forcing a test turns on or off, which remembers whom it was asked about.
 */
final class SwitchableAuthorization implements ShipmentAuthorization, ShipmentForcing
{
    /** @var list<string> the order numbers, then `number/actor` for a forcing, as asked */
    public array $asked = [];

    public function __construct(
        public bool $answer = true,
    ) {
    }

    #[Override]
    public function authorizes(Order $order): bool
    {
        $this->asked[] = $order->number;

        return $this->answer;
    }

    #[Override]
    public function allows(Order $order, string $actor): bool
    {
        $this->asked[] = $order->number.'/'.$actor;

        return $this->answer;
    }
}
