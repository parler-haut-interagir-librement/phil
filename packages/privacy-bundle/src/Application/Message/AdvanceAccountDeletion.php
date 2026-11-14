<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Message;

/**
 * Runs what is left of one account deletion. It carries the identifier of the request and nothing about the person.
 */
final readonly class AdvanceAccountDeletion
{
    public function __construct(
        public string $deletionId,
    ) {
    }
}
