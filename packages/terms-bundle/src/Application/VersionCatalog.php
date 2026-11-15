<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Application;

use DateTimeInterface;
use Override;
use Phil\TermsBundle\Domain\Contract\PolicyVersionRepository;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The versions of each policy, read once per request and kept until `kernel.reset`: the gate, the pages and the
 * acceptance read the same list without asking the store again, and a worker never serves a request with the list of
 * another.
 *
 * - the version **in force** at a moment is the newest that has taken effect;
 * - the version **required** at a moment is the newest material version that has taken effect: a member who accepted
 *   it, or a later one, may go on;
 * - the **latest material** version is the newest material one, whether it has taken effect or not.
 */
final class VersionCatalog implements ResetInterface
{
    /**
     * @var array<string, list<PolicyVersion>>
     */
    private array $versions = [];

    public function __construct(
        private readonly PolicyVersionRepository $repository,
    ) {
    }

    /**
     * @return list<PolicyVersion> the newest first
     */
    public function versions(string $policy): array
    {
        return $this->versions[$policy] ??= $this->repository->all($policy);
    }

    public function find(string $policy, int $number): ?PolicyVersion
    {
        return array_find($this->versions($policy), static fn (PolicyVersion $version): bool => $version->number === $number);
    }

    public function latest(string $policy): ?PolicyVersion
    {
        return $this->versions($policy)[0] ?? null;
    }

    public function inForce(string $policy, DateTimeInterface $at): ?PolicyVersion
    {
        return array_find($this->versions($policy), static fn (PolicyVersion $version): bool => $version->isInForceAt($at));
    }

    public function required(string $policy, DateTimeInterface $at): ?PolicyVersion
    {
        return array_find($this->versions($policy), static fn (PolicyVersion $version): bool => $version->material && $version->isInForceAt($at));
    }

    public function latestMaterial(string $policy): ?PolicyVersion
    {
        return array_find($this->versions($policy), static fn (PolicyVersion $version): bool => $version->material);
    }

    /**
     * Drops the versions of a policy, after one was added.
     */
    public function forget(string $policy): void
    {
        unset($this->versions[$policy]);
    }

    #[Override]
    public function reset(): void
    {
        $this->versions = [];
    }
}
