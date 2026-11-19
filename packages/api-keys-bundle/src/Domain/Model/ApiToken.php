<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\ApiKeysBundle\Domain\Exception\InvalidTokenValue;
use Phil\ApiKeysBundle\Domain\Token\TokenOwner;

/**
 * One access token, as a row of the table `phil_api_token`: its identifier, the owner it acts for — a member, an
 * organisation, an application, by type and identifier —, the name it was given, its scopes, the keyed hash of its secret — never the secret —, when it was created and expires,
 * when it was last used and from where, anonymized, and when it was revoked.
 *
 * A revoked or expired token keeps its row until the purge, so its owner sees what became of it.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'phil_api_token_owner', columns: ['owner_type', 'owner'])]
#[ORM\Index(name: 'phil_api_token_expires_at', columns: ['expires_at'])]
final class ApiToken
{
    public const string TABLE = 'phil_api_token';

    public const int OWNER_MAX_LENGTH = TokenOwner::ID_MAX_LENGTH;

    public const int NAME_MAX_LENGTH = 100;

    public const int ADDRESS_MAX_LENGTH = 100;

    /** Room for a keyed hash that names its algorithm and version, and for a longer one of a later version. */
    public const int SECRET_HASH_MAX_LENGTH = 100;

    /**
     * @param list<string> $scopes
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 12, options: ['fixed' => true])]
        private readonly string $id,
        #[ORM\Column(name: 'owner_type', length: TokenOwner::TYPE_MAX_LENGTH, options: ['default' => TokenOwner::USER])]
        private readonly string $ownerType,
        #[ORM\Column(name: 'owner', length: self::OWNER_MAX_LENGTH)]
        private readonly string $owner,
        #[ORM\Column(name: 'name', length: self::NAME_MAX_LENGTH)]
        private readonly string $name,
        #[ORM\Column(name: 'scopes', type: 'json')]
        private readonly array $scopes,
        #[ORM\Column(name: 'secret_hash', length: self::SECRET_HASH_MAX_LENGTH)]
        private readonly string $secretHash,
        #[ORM\Column(name: 'created_at')]
        private readonly DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'expires_at')]
        private DateTimeImmutable $expiresAt,
        #[ORM\Column(name: 'last_used_at', nullable: true)]
        private ?DateTimeImmutable $lastUsedAt = null,
        #[ORM\Column(name: 'last_used_address', length: self::ADDRESS_MAX_LENGTH, nullable: true)]
        private ?string $lastUsedAddress = null,
        #[ORM\Column(name: 'revoked_at', nullable: true)]
        private ?DateTimeImmutable $revokedAt = null,
    ) {
    }

    /**
     * A new token. The owner is a member by the identifier their user is loaded by, or a typed owner; the name 1 to 100
     * characters, trimmed; the scopes distinct; the expiry after the creation.
     *
     * @param list<string> $scopes
     *
     * @throws InvalidTokenValue when one of them is out of shape
     */
    public static function issue(string $id, TokenOwner|string $owner, string $name, array $scopes, string $secretHash, DateTimeImmutable $createdAt, DateTimeImmutable $expiresAt): self
    {
        $owner = TokenOwner::of($owner);
        $name = trim($name);

        if ('' === $name || mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw InvalidTokenValue::because(\sprintf('The name of a token is 1 to %d characters.', self::NAME_MAX_LENGTH));
        }

        if (\count(array_unique($scopes)) !== \count($scopes)) {
            throw InvalidTokenValue::because('A token holds each scope once.');
        }

        if ($expiresAt <= $createdAt) {
            throw InvalidTokenValue::because('A token expires after it is created.');
        }

        return new self($id, $owner->type, $owner->id, $name, $scopes, $secretHash, $createdAt, $expiresAt);
    }

    public function id(): string
    {
        return $this->id;
    }

    /**
     * The identifier of the owner, which the user provider of its type loads.
     */
    public function owner(): string
    {
        return $this->owner;
    }

    /**
     * The type of the owner: `user` for a member, or another type the configuration declares.
     */
    public function ownerType(): string
    {
        return $this->ownerType;
    }

    public function tokenOwner(): TokenOwner
    {
        return new TokenOwner($this->ownerType, $this->owner);
    }

    public function belongsTo(TokenOwner $owner): bool
    {
        return $owner->type === $this->ownerType && $owner->id === $this->owner;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    public function hasScope(string $scope): bool
    {
        return \in_array($scope, $this->scopes, true);
    }

    public function secretHash(): string
    {
        return $this->secretHash;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function lastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    /**
     * The address the token was last used from, as the configured anonymizer kept it; null when none is configured.
     */
    public function lastUsedAddress(): ?string
    {
        return $this->lastUsedAddress;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt instanceof DateTimeImmutable;
    }

    /**
     * Whether the token has expired at that instant: from its expiry on, it is refused.
     */
    public function hasExpiredAt(DateTimeImmutable $now): bool
    {
        return $this->expiresAt <= $now;
    }

    /**
     * Whether the token opens the API at that instant: neither revoked nor expired.
     */
    public function isActiveAt(DateTimeImmutable $now): bool
    {
        return !$this->isRevoked() && !$this->hasExpiredAt($now);
    }

    /**
     * Revokes the token; a token already revoked keeps its first date.
     *
     * @return bool whether the token was revoked now
     */
    public function revoke(DateTimeImmutable $now): bool
    {
        if ($this->isRevoked()) {
            return false;
        }

        $this->revokedAt = $now;

        return true;
    }

    /**
     * Brings the expiry forward to that instant, the grace a rotated token keeps; a later instant changes nothing.
     */
    public function expireBy(DateTimeImmutable $expiresAt): void
    {
        $this->expiresAt = min($expiresAt, $this->expiresAt);
    }

    /**
     * Whether a use at that instant is to be written: none was written less than `$throttle` seconds before, so that a
     * token called many times a second writes its row at most once per interval.
     *
     * @param non-negative-int $throttle
     */
    public function isUseDue(DateTimeImmutable $now, int $throttle): bool
    {
        return !$this->lastUsedAt instanceof DateTimeImmutable || $now->getTimestamp() - $this->lastUsedAt->getTimestamp() >= $throttle;
    }

    /**
     * Records a use, with the address it came from as the anonymizer kept it, cut to 100 bytes.
     */
    public function recordUse(DateTimeImmutable $now, ?string $address): void
    {
        $this->lastUsedAt = $now;
        $this->lastUsedAddress = null === $address ? null : mb_strcut($address, 0, self::ADDRESS_MAX_LENGTH);
    }
}
