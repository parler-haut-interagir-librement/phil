<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Status;

/**
 * Where the delivery of a configuration push to one site stands: applied, replayed when the site had already applied
 * it, failed.
 */
enum DeliveryOutcome: string
{
    case Pending = 'pending';
    case Applied = 'applied';
    case Replayed = 'replayed';
    case Failed = 'failed';

    public function isDelivered(): bool
    {
        return self::Applied === $this || self::Replayed === $this;
    }
}
