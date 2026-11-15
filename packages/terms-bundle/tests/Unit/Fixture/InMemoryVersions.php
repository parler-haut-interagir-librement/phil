<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Fixture;

use Override;
use Phil\TermsBundle\Domain\Contract\PolicyVersionRepository;
use Phil\TermsBundle\Domain\Exception\InvalidTerms;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;

/**
 * The versions in memory, with the count of the reads of each policy.
 */
final class InMemoryVersions implements PolicyVersionRepository
{
    /** @var array<string, array<int, PolicyVersion>> */
    private array $versions = [];

    /** @var array<string, int> */
    public array $reads = [];

    #[Override]
    public function add(PolicyVersion $version): void
    {
        if (isset($this->versions[$version->policy][$version->number])) {
            throw InvalidTerms::because('version_taken', 'Taken.');
        }

        $this->versions[$version->policy][$version->number] = $version;
    }

    /**
     * Forgets every version, as a store that was emptied.
     */
    public function clear(): void
    {
        $this->versions = [];
    }

    #[Override]
    public function all(string $policy): array
    {
        $this->reads[$policy] = ($this->reads[$policy] ?? 0) + 1;
        $versions = $this->versions[$policy] ?? [];
        krsort($versions);

        return array_values($versions);
    }
}
