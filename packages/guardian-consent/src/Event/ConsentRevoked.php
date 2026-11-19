<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Event;

/**
 * A guardian withdrew a consent to a feature. It names the member and the feature only.
 */
final readonly class ConsentRevoked
{
    public function __construct(
        public string $member,
        public string $feature,
    ) {
    }
}
