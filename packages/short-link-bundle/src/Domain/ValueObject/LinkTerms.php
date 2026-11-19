<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\RedirectsBundle\Domain\Enum\QueryPolicy;
use Phil\RedirectsBundle\Domain\Enum\RedirectStatus;

/**
 * What a short link does besides its code and its target: the status it redirects with, what becomes of the query
 * string of the visit, its label in the administration, its locale, and when it is in force.
 */
final readonly class LinkTerms
{
    public function __construct(
        public RedirectStatus $status = RedirectStatus::Found,
        public QueryPolicy $query = QueryPolicy::Drop,
        public ?string $label = null,
        public ?string $locale = null,
        public bool $enabled = true,
        public ?DateTimeImmutable $validFrom = null,
        public ?DateTimeImmutable $validUntil = null,
        public ?int $maxVisits = null,
    ) {
    }
}
