<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application;

use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Model\External\LinkedIdentity;

/**
 * What a federated provider's package hands to the bundle once the person signed in there: the provider's name, the
 * subject it gives them, and, when the person shared them, an address — with whether the provider verified it — and
 * a name. Tokens of the provider stay in its package; the bundle keeps the subject only.
 */
final readonly class ExternalIdentity
{
    public function __construct(
        public string $provider,
        public string $subject,
        public ?string $email = null,
        public bool $emailVerified = false,
        public ?string $displayName = null,
    ) {
        if (1 !== preg_match(LinkedIdentity::PROVIDER_PATTERN, $provider) || '' === $subject || \strlen($subject) > LinkedIdentity::SUBJECT_MAX_LENGTH) {
            throw InvalidValue::because('A provider is a lowercase name of up to 64 characters, a subject 1 to 255 bytes.');
        }
    }
}
