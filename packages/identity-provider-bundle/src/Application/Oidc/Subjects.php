<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Oidc;

use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\IdentityProviderBundle\Domain\Oidc\SubjectType;

/**
 * The `sub` of an account for a client, by the subject type of the provider: the internal identifier, or the HMAC-SHA256
 * under `oidc.subject.salt` of the type, the sector — none for the opaque type — and the internal identifier, in
 * base64url. The identifier stays internal: no client learns it, and the same salt always gives the same subject, so
 * that the salt never changes once clients have seen subjects.
 *
 * The sector of a client is the one it registered, or the host of its redirect addresses when they share one, or its own
 * identifier.
 */
final readonly class Subjects
{
    public function __construct(
        private ProviderSettings $settings,
    ) {
    }

    public function of(string $accountId, Client $client): string
    {
        return match ($this->settings->subjectType) {
            SubjectType::Account => $accountId,
            SubjectType::Opaque => $this->derive('', $accountId),
            SubjectType::Pairwise => $this->derive($client->sector(), $accountId),
        };
    }

    private function derive(string $sector, string $accountId): string
    {
        return Base64Url::encode(hash_hmac('sha256', $this->settings->subjectType->value."\0".$sector."\0".$accountId, (string) $this->settings->subjectSalt, true));
    }
}
