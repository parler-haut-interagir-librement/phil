<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Memory;

use Override;
use Phil\CodeOfConduct\ChangeKind;
use Phil\CodeOfConduct\CodeVersion;
use Phil\CodeOfConduct\Contract\CodeVersionStore;
use Phil\CodeOfConduct\Exception\ActionRefused;

/**
 * Versions in memory, for tests and demonstrations: they live as long as the object, so a long-running worker never
 * shares one between requests.
 */
final class InMemoryCodeVersionStore implements CodeVersionStore
{
    /** @var array<string, array<int, CodeVersion>> */
    private array $versions = [];

    #[Override]
    public function current(string $scope): ?CodeVersion
    {
        $versions = $this->versions[$scope] ?? [];

        return [] === $versions ? null : $versions[max(array_keys($versions))];
    }

    #[Override]
    public function version(string $scope, int $number): ?CodeVersion
    {
        return $this->versions[$scope][$number] ?? null;
    }

    #[Override]
    public function latestMajor(string $scope): ?int
    {
        $majors = array_keys(array_filter($this->versions[$scope] ?? [], static fn (CodeVersion $version): bool => ChangeKind::Major === $version->kind));

        return [] === $majors ? null : max($majors);
    }

    #[Override]
    public function add(CodeVersion $version): void
    {
        if (isset($this->versions[$version->scope][$version->number])) {
            throw ActionRefused::because('This version of the code was already published.');
        }

        $this->versions[$version->scope][$version->number] = $version;
    }
}
