<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Contract;

use Phil\NewsletterBundle\Domain\ValueObject\Site;

/**
 * The addresses never to mail again — bounced, complained —, kept as a keyed hash, so that the list outlives an
 * erasure without keeping the address.
 */
interface SuppressionRepository
{
    public function suppress(Site $site, string $emailKey, string $reason): void;

    public function isSuppressed(Site $site, string $emailKey): bool;
}
