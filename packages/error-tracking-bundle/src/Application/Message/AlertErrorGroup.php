<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Application\Message;

/**
 * Tells a group needs a look, by its fingerprint and the occurrence that raised the alert: identifiers only, the
 * handler reads the group from the store.
 */
final readonly class AlertErrorGroup
{
    public function __construct(
        public string $fingerprint,
        public string $occurrence,
    ) {
    }
}
