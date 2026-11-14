<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\Plan;

use Doctrine\ORM\Mapping as ORM;

/**
 * A subclass whose own association lives in the table of its single-table root: the root waits for the orders.
 */
#[ORM\Entity]
final class Refund extends Payment
{
    #[ORM\ManyToOne(targetEntity: Order::class)]
    public ?Order $order = null;
}
