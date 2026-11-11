<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Verification;

use Override;
use Phil\OpenBadges\Contract\VerificationKey;
use Phil\OpenBadges\Contract\VerificationMethods;
use Phil\OpenBadges\Exception\InvalidCredential;

/**
 * The verification methods of the issuer a credential names: its identifier, the fragments of it, and those the
 * application lists for it. Any other method is refused before its key is looked up, so a key signs only in its own
 * issuer's name. The credential is read before its signature is verified: it is verified with that key, or refused.
 *
 * @internal
 */
final readonly class IssuerMethods implements VerificationMethods
{
    /**
     * @param array<array-key, mixed>     $credential  the credential, its signature not verified yet
     * @param array<string, list<string>> $controllers the other verification methods of each issuer, by identifier
     */
    public function __construct(
        private VerificationMethods $methods,
        private array $credential,
        private array $controllers = [],
    ) {
    }

    #[Override]
    public function key(string $verificationMethod): VerificationKey
    {
        $issuer = new VerifiedCredential($this->credential, '')->issuer() ?? throw InvalidCredential::because('The credential names no issuer.');

        if ($verificationMethod !== $issuer && !str_starts_with($verificationMethod, $issuer.'#') && !\in_array($verificationMethod, $this->controllers[$issuer] ?? [], true)) {
            throw InvalidCredential::because(\sprintf('The verification method "%s" is not one of the issuer "%s".', $verificationMethod, $issuer));
        }

        return $this->methods->key($verificationMethod);
    }
}
