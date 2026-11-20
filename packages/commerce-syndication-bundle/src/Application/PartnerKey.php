<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Application;

use Override;
use Phil\CommerceSyndicationBundle\Domain\Model\Partner;
use Phil\StandardWebhooks\Ed25519PublicKey;
use Phil\StandardWebhooks\VerificationKeyProvider;

/**
 * The one key a partner's messages are verified with: a message signed by another partner is refused, whatever
 * its signature.
 */
final readonly class PartnerKey implements VerificationKeyProvider
{
    public function __construct(
        private Partner $partner,
    ) {
    }

    #[Override]
    public function keys(): array
    {
        return [Ed25519PublicKey::fromString($this->partner->publicKey())];
    }

    #[Override]
    public function refresh(): array
    {
        return $this->keys();
    }
}
