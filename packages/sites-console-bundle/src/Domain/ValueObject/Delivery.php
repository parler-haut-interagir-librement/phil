<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\SitesConsoleBundle\Domain\Status\DeliveryOutcome;

/**
 * The delivery of a configuration push to one site.
 */
final readonly class Delivery
{
    public function __construct(
        public string $siteId,
        public DeliveryOutcome $outcome,
        public string $message = '',
        public ?DateTimeImmutable $at = null,
    ) {
    }
}
