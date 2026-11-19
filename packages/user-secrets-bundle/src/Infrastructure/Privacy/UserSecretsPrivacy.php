<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Infrastructure\Privacy;

use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Phil\UserSecretsBundle\Application\SecretSummary;
use Phil\UserSecretsBundle\Application\UserSecrets;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;

/**
 * The secrets' part of an access or erasure request, the data subject being the owner. The export lists what the
 * person entrusted — names, services, kinds and dates — and never a value, which is the person's own and would only
 * travel in clear; the erasure removes every secret.
 */
final readonly class UserSecretsPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'user_secrets';

    public function __construct(
        private UserSecrets $secrets,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    #[Override]
    public function export(DataSubject $subject): array
    {
        $secrets = $this->secrets->secrets(Owner::fromString($subject->value));

        return [] === $secrets ? [] : ['secrets' => array_map(static fn (SecretSummary $secret): array => [
            'name' => $secret->name->value,
            'service' => $secret->service->value,
            'kind' => $secret->kind->value,
            'created_at' => self::date($secret->createdAt),
            'updated_at' => self::date($secret->updatedAt),
            'last_used_at' => self::date($secret->lastUsedAt),
            'expires_at' => self::date($secret->expiresAt),
            'revoked_at' => self::date($secret->revokedAt),
        ], $secrets)];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $this->secrets->eraseOwner(Owner::fromString($subject->value));

        return ErasureReport::of(self::MODULE, ['phil_user_secret' => ErasureEffect::Deleted]);
    }

    private static function date(?DateTimeImmutable $date): ?string
    {
        return $date?->format(DateTimeInterface::ATOM);
    }
}
