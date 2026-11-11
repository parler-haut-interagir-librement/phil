<?php

declare(strict_types=1);

namespace Phil\OpenBadges;

use DateTimeImmutable;
use Phil\OpenBadges\Contract\Signer;
use Phil\OpenBadges\Jwt\JwtSigner;
use Phil\OpenBadges\Status\StatusListEntry;

/**
 * Issues Open Badges 3.0 credentials: an `OpenBadgeCredential` in the data model of Verifiable Credentials 2.0, with
 * its recipient, its achievement, its period of validity, its status entry when it can be revoked and the endorsements
 * third parties signed for it.
 *
 * `credential()` returns it unsecured, for a Data Integrity proof ({@see DataIntegrity\EddsaRdfc2022}); `issue()`
 * secures it as a JSON Web Token signed by the issuer's key.
 */
final readonly class CredentialIssuer
{
    public function __construct(
        private Issuer $issuer,
        private Signer $signer,
        private ?string $keyId = null,
    ) {
    }

    /**
     * The credential, unsecured.
     *
     * @param list<array<string, mixed>|string> $endorsements signed `EndorsementCredential`s of the credential
     *
     * @return array<string, mixed>
     */
    public function credential(Achievement $achievement, Recipient $recipient, string $id, DateTimeImmutable $validFrom, ?DateTimeImmutable $validUntil = null, ?StatusListEntry $status = null, array $endorsements = []): array
    {
        Credentials::checkId($id);
        $period = Credentials::period($validFrom, $validUntil);

        $credential = [
            '@context' => Context::CREDENTIAL,
            'id' => $id,
            'type' => ['VerifiableCredential', 'OpenBadgeCredential'],
            'issuer' => $this->issuer->toArray(),
            'validFrom' => $period['validFrom'],
            'name' => $achievement->name,
            'credentialSubject' => ['type' => ['AchievementSubject'], ...$recipient->toArray(), 'achievement' => $achievement->toArray()],
        ];

        if (isset($period['validUntil'])) {
            $credential['validUntil'] = $period['validUntil'];
        }

        if ($status instanceof StatusListEntry) {
            $credential['credentialStatus'] = $status->toArray();
        }

        return [...$credential, ...Endorsements::fields($endorsements)];
    }

    /**
     * The credential, secured as a compact JSON Web Token.
     *
     * @param list<array<string, mixed>|string> $endorsements
     */
    public function issue(Achievement $achievement, Recipient $recipient, string $id, DateTimeImmutable $validFrom, ?DateTimeImmutable $validUntil = null, ?StatusListEntry $status = null, array $endorsements = []): string
    {
        return new JwtSigner($this->signer, $this->keyId)->sign($this->credential($achievement, $recipient, $id, $validFrom, $validUntil, $status, $endorsements));
    }
}
