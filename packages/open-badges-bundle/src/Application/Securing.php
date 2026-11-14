<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Application;

use Phil\OpenBadges\Contract\Signer;
use Phil\OpenBadges\DataIntegrity\EddsaRdfc2022;
use Phil\OpenBadges\Jwt\JwtSigner;
use Phil\OpenBadges\Key\Ed25519Signer;
use Psr\Clock\ClockInterface;

/**
 * Secures the credentials of the application's issuer the way its configuration says: as JSON Web Tokens, or with a
 * Data Integrity proof `eddsa-rdfc-2022`, created now — both with the issuer's key and its verification method.
 */
final readonly class Securing
{
    public const string JWT = 'jwt';

    public const string DATA_INTEGRITY = 'data_integrity';

    public function __construct(
        private Signer $signer,
        private string $verificationMethod,
        private string $format,
        private ClockInterface $clock,
        private EddsaRdfc2022 $dataIntegrity = new EddsaRdfc2022(),
    ) {
    }

    /**
     * The credential secured — a compact token, or the document with its proof — with the media type of the result.
     *
     * @param array<string, mixed> $credential
     */
    public function secure(array $credential): SecuredCredential
    {
        if (self::DATA_INTEGRITY === $this->format && $this->signer instanceof Ed25519Signer) {
            return SecuredCredential::document($this->dataIntegrity->sign($credential, $this->signer, $this->verificationMethod, $this->clock->now()));
        }

        return SecuredCredential::token(new JwtSigner($this->signer, $this->verificationMethod)->sign($credential));
    }
}
