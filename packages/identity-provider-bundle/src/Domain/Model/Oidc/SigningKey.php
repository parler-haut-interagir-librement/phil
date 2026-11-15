<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Model\Oidc;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use SensitiveParameter;

/**
 * A key the provider signs with, as a row of `phil_identity_signing_key`: its identifier and algorithm, its private
 * material as the store keeps it — encrypted when a key management service is configured —, its public JSON Web Key,
 * and when it was created, started signing and was retired.
 *
 * A key is published from its creation, so that clients have it in their cache before it signs; it signs once
 * activated; once retired it signs no more but stays published until the tokens it signed have expired.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
final class SigningKey
{
    public const string TABLE = 'phil_identity_signing_key';

    /**
     * @param array<string, string> $publicJwk
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'kid', length: 64)]
        private readonly string $kid,
        #[ORM\Column(name: 'algorithm', length: 10, enumType: SigningAlgorithm::class)]
        private readonly SigningAlgorithm $algorithm,
        #[ORM\Column(name: 'private_material', type: 'text')]
        private readonly string $privateMaterial,
        #[ORM\Column(name: 'public_jwk', type: 'json')]
        private readonly array $publicJwk,
        #[ORM\Column(name: 'created_at')]
        private readonly DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'activated_at', nullable: true)]
        private ?DateTimeImmutable $activatedAt = null,
        #[ORM\Column(name: 'retired_at', nullable: true)]
        private ?DateTimeImmutable $retiredAt = null,
    ) {
    }

    /**
     * @param array<string, string> $publicJwk
     */
    public static function create(string $kid, SigningAlgorithm $algorithm, #[SensitiveParameter] string $privateMaterial, array $publicJwk, DateTimeImmutable $now): self
    {
        return new self($kid, $algorithm, $privateMaterial, $publicJwk, $now);
    }

    public function kid(): string
    {
        return $this->kid;
    }

    public function algorithm(): SigningAlgorithm
    {
        return $this->algorithm;
    }

    public function privateMaterial(): string
    {
        return $this->privateMaterial;
    }

    /**
     * @return array<string, string>
     */
    public function publicJwk(): array
    {
        return $this->publicJwk;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function activatedAt(): ?DateTimeImmutable
    {
        return $this->activatedAt;
    }

    public function retiredAt(): ?DateTimeImmutable
    {
        return $this->retiredAt;
    }

    public function isActive(): bool
    {
        return $this->activatedAt instanceof DateTimeImmutable && !$this->retiredAt instanceof DateTimeImmutable;
    }

    public function isPending(): bool
    {
        return !$this->activatedAt instanceof DateTimeImmutable && !$this->retiredAt instanceof DateTimeImmutable;
    }

    public function activate(DateTimeImmutable $now): void
    {
        $this->activatedAt ??= $now;
    }

    public function retire(DateTimeImmutable $now): void
    {
        $this->retiredAt ??= $now;
    }
}
