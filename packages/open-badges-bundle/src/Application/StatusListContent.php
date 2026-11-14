<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Application;

use Phil\OpenBadges\Exception\InvalidBadge;
use Phil\OpenBadges\Status\StatusListEntry;

/**
 * What a status list holds: the purpose of its bits — `revocation` or `suspension` — and the indexes whose bit is set.
 */
final readonly class StatusListContent
{
    /**
     * @param list<int> $set the indexes of the credentials revoked, or suspended
     */
    public function __construct(
        public array $set,
        public string $purpose = 'revocation',
    ) {
        if (!\in_array($purpose, StatusListEntry::PURPOSES, true)) {
            throw InvalidBadge::because(\sprintf('The purpose of a status is "revocation" or "suspension"; "%s" is not.', $purpose));
        }
    }
}
