<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Event;

use DateTimeImmutable;

/**
 * A subscriber clicked a link of a campaign that counts clicks per recipient. A campaign that counts them in a total
 * dispatches nothing, since it does not know who clicked.
 */
final readonly class LinkClicked
{
    public function __construct(
        public string $campaignId,
        public int $link,
        public string $subscriberId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
