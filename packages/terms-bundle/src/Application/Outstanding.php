<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Application;

use DateTimeImmutable;
use Generator;
use Phil\TermsBundle\Application\Contract\MemberSource;
use Phil\TermsBundle\Domain\Contract\AcceptanceRepository;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;

/**
 * The members who have not accepted a policy, read from the member source a batch at a time:
 *
 * - at a moment, those who have not accepted the version required then, or whose acceptance lapsed by then;
 * - for the latest material version, those who have not accepted it yet, whether it has taken effect or not — the
 *   members a publication notice goes to.
 */
final readonly class Outstanding
{
    public const int BATCH = 500;

    public function __construct(
        private Policies $policies,
        private VersionCatalog $catalog,
        private AcceptanceRepository $acceptances,
        private MemberSource $members,
    ) {
    }

    /**
     * @return Generator<int, string>
     */
    public function at(string $policy, DateTimeImmutable $at): Generator
    {
        $definition = $this->policies->get($policy);

        return $this->without($definition->key, $this->catalog->required($definition->key, $at), $definition->acceptedSince($at));
    }

    /**
     * @return Generator<int, string>
     */
    public function ofLatestMaterial(string $policy): Generator
    {
        return $this->without($this->policies->get($policy)->key, $this->catalog->latestMaterial($policy), null);
    }

    /**
     * @return Generator<int, string>
     */
    private function without(string $policy, ?PolicyVersion $version, ?DateTimeImmutable $since): Generator
    {
        if (!$version instanceof PolicyVersion) {
            return;
        }

        $batch = [];

        foreach ($this->members->members() as $member) {
            $batch[] = $member;

            if (self::BATCH === \count($batch)) {
                yield from $this->missing($policy, $batch, $version->number, $since);
                $batch = [];
            }
        }

        if ([] !== $batch) {
            yield from $this->missing($policy, $batch, $version->number, $since);
        }
    }

    /**
     * @param list<string> $batch
     *
     * @return list<string>
     */
    private function missing(string $policy, array $batch, int $minimum, ?DateTimeImmutable $since): array
    {
        return array_values(array_diff($batch, $this->acceptances->compliant($policy, $batch, $minimum, $since)));
    }
}
