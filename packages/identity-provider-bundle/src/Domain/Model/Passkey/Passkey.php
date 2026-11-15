<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Model\Passkey;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\WebAuthn\RegisteredCredential;

/**
 * A passkey of an account, as a row of `phil_identity_passkey`: the SHA-256 of its credential identifier as key — the
 * identifier itself is up to 1023 bytes —, the identifier, its COSE public key, its counter, its AAGUID, the name its
 * owner gave it, whether it is synced, and when it was added and last used.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'phil_identity_passkey_account', columns: ['account_id'])]
final class Passkey
{
    public const string TABLE = 'phil_identity_passkey';

    public const int NAME_MAX_LENGTH = 100;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 64, options: ['fixed' => true])]
        private readonly string $id,
        #[ORM\Column(name: 'account_id', length: 26, options: ['fixed' => true])]
        private readonly string $accountId,
        #[ORM\Column(name: 'credential_id', type: 'text')]
        private readonly string $credentialId,
        #[ORM\Column(name: 'cose_key', type: 'text')]
        private readonly string $coseKey,
        #[ORM\Column(name: 'aaguid', length: 36, options: ['fixed' => true])]
        private readonly string $aaguid,
        #[ORM\Column(name: 'name', length: self::NAME_MAX_LENGTH)]
        private string $name,
        #[ORM\Column(name: 'sign_count', type: 'bigint')]
        private int $signCount,
        #[ORM\Column(name: 'backup_eligible')]
        private readonly bool $backupEligible,
        #[ORM\Column(name: 'backed_up')]
        private bool $backedUp,
        #[ORM\Column(name: 'created_at')]
        private readonly DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'last_used_at', nullable: true)]
        private ?DateTimeImmutable $lastUsedAt = null,
    ) {
    }

    public static function register(string $accountId, RegisteredCredential $credential, string $name, DateTimeImmutable $now): self
    {
        $hex = sodium_bin2hex($credential->aaguid);
        $aaguid = \sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));

        return new self(self::idOf($credential->credentialId), $accountId, Base64Url::encode($credential->credentialId), Base64Url::encode($credential->coseKey), $aaguid, self::normalizeName($name), $credential->signCount, $credential->backupEligible, $credential->backedUp, $now);
    }

    /**
     * The key of the row of a credential identifier, raw bytes.
     */
    public static function idOf(string $credentialId): string
    {
        return hash('sha256', $credentialId);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    /**
     * The credential identifier, base64url.
     */
    public function credentialId(): string
    {
        return $this->credentialId;
    }

    public function coseKey(): string
    {
        return Base64Url::decode($this->coseKey);
    }

    public function aaguid(): string
    {
        return $this->aaguid;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = self::normalizeName($name);
    }

    public function signCount(): int
    {
        return $this->signCount;
    }

    public function isBackupEligible(): bool
    {
        return $this->backupEligible;
    }

    public function isBackedUp(): bool
    {
        return $this->backedUp;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function lastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function recordUse(int $signCount, bool $backedUp, DateTimeImmutable $now): void
    {
        $this->signCount = $signCount;
        $this->backedUp = $backedUp;
        $this->lastUsedAt = $now;
    }

    private static function normalizeName(string $name): string
    {
        $name = trim($name);

        if ('' === $name || mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw InvalidValue::because('The name of a passkey holds 1 to 100 characters.');
        }

        return $name;
    }
}
