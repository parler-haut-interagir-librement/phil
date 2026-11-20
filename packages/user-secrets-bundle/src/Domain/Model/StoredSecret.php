<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\UserSecretsBundle\Domain\Credential\CredentialKind;
use Phil\UserSecretsBundle\Domain\Exception\RevokedSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SealedSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;

/**
 * One secret of one owner, as a row identified by the owner and the secret's name: the service it opens, its kind,
 * the key that sealed it, the ciphertext and the version of its format, and when it was stored, changed, last used,
 * when it expires and when it was revoked.
 *
 * The format column repeats the first byte of the ciphertext when it names a known format, so the key rotation finds
 * in SQL the values of no known format; readers go by the byte itself.
 *
 * Only the ciphertext is secret; the rest is what the owner sees in the list of their secrets. A revoked secret
 * keeps its row, its ciphertext and key wiped, so the owner sees that it was revoked and when.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
final class StoredSecret
{
    public const string TABLE = 'phil_user_secret';

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'owner', length: Owner::MAX_LENGTH)]
        private readonly string $owner,
        #[ORM\Id]
        #[ORM\Column(name: 'name', length: 64)]
        private readonly string $name,
        #[ORM\Column(name: 'service', length: 64)]
        private string $service,
        #[ORM\Column(name: 'kind', length: 16)]
        private string $kind,
        #[ORM\Column(name: 'key_id', length: 32)]
        private string $keyId,
        #[ORM\Column(name: 'ciphertext', type: 'text')]
        private string $ciphertext,
        #[ORM\Column(name: 'format', type: 'smallint', nullable: true)]
        private ?int $format,
        #[ORM\Column(name: 'expires_at', type: 'datetime_utc_immutable', nullable: true)]
        private ?DateTimeImmutable $expiresAt,
        // Not readonly, though never changed: Doctrine reads the row again into the entity, after a compare-and-swap.
        #[ORM\Column(name: 'created_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'updated_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $updatedAt,
        #[ORM\Column(name: 'last_used_at', type: 'datetime_utc_immutable', nullable: true)]
        private ?DateTimeImmutable $lastUsedAt = null,
        #[ORM\Column(name: 'revoked_at', type: 'datetime_utc_immutable', nullable: true)]
        private ?DateTimeImmutable $revokedAt = null,
    ) {
    }

    public static function seal(Owner $owner, SecretName $name, ServiceName $service, CredentialKind $kind, SealedSecret $sealed, ?DateTimeImmutable $expiresAt, DateTimeImmutable $now): self
    {
        return new self($owner->value, $name->value, $service->value, $kind->value, $sealed->keyId, $sealed->ciphertext, $sealed->format()?->value, $expiresAt, $now, $now);
    }

    /**
     * Takes a new value — for the same or another service and kind —, revoked or not before.
     */
    public function reseal(ServiceName $service, CredentialKind $kind, SealedSecret $sealed, ?DateTimeImmutable $expiresAt, DateTimeImmutable $now): void
    {
        $this->service = $service->value;
        $this->kind = $kind->value;
        $this->keyId = $sealed->keyId;
        $this->ciphertext = $sealed->ciphertext;
        $this->format = $sealed->format()?->value;
        $this->expiresAt = $expiresAt;
        $this->updatedAt = $now;
        $this->revokedAt = null;
    }

    /**
     * Takes the same value sealed under another key or in another format; the value did not change, nor does the date
     * it changed.
     *
     * @throws RevokedSecret
     */
    public function rekey(SealedSecret $sealed): void
    {
        $this->sealed();
        $this->keyId = $sealed->keyId;
        $this->ciphertext = $sealed->ciphertext;
        $this->format = $sealed->format()?->value;
    }

    /**
     * Wipes the value; revoking again keeps the first date.
     */
    public function revoke(DateTimeImmutable $now): void
    {
        $this->revokedAt ??= $now;
        $this->keyId = '';
        $this->ciphertext = '';
        $this->format = null;
    }

    public function markUsed(DateTimeImmutable $now): void
    {
        $this->lastUsedAt = $now;
    }

    /**
     * @throws RevokedSecret
     */
    public function sealed(): SealedSecret
    {
        if ($this->revokedAt instanceof DateTimeImmutable) {
            throw RevokedSecret::named($this->name);
        }

        return new SealedSecret($this->keyId, $this->ciphertext);
    }

    public function owner(): Owner
    {
        return Owner::fromString($this->owner);
    }

    public function name(): SecretName
    {
        return SecretName::fromString($this->name);
    }

    public function service(): ServiceName
    {
        return ServiceName::fromString($this->service);
    }

    public function kind(): CredentialKind
    {
        return CredentialKind::from($this->kind);
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function lastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }
}
