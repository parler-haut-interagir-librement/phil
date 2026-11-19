<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Application\Contract;

use DateTimeImmutable;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnRequest;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnLabel;

/**
 * Asks a carrier for the label that sends a return's goods back: an adapter per carrier, which knows the shop's
 * account and the customer's address.
 */
interface ReturnLabels
{
    public function labelFor(ReturnRequest $return, DateTimeImmutable $at): ReturnLabel;
}
