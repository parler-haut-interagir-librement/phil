<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\TermsBundle\Domain\Contract\AcceptanceRepository;
use Phil\TermsBundle\Domain\ValueObject\Acceptance;

/**
 * The acceptances in memory, with the batches of members each compliance question was asked for.
 */
final class InMemoryAcceptances implements AcceptanceRepository
{
    /** @var list<Acceptance> */
    public array $acceptances = [];

    /** @var list<list<string>> */
    public array $batches = [];

    #[Override]
    public function append(Acceptance $acceptance): void
    {
        $this->acceptances[] = $acceptance;
    }

    #[Override]
    public function compliant(string $policy, array $members, int $minimum, ?DateTimeImmutable $since = null): array
    {
        $this->batches[] = $members;
        $compliant = [];

        foreach ($this->acceptances as $acceptance) {
            if ($acceptance->policy === $policy && $acceptance->version >= $minimum && \in_array($acceptance->member, $members, true) && (!$since instanceof DateTimeImmutable || $acceptance->acceptedAt >= $since)) {
                $compliant[$acceptance->member] = $acceptance->member;
            }
        }

        return array_values($compliant);
    }

    #[Override]
    public function ofMember(string $member): array
    {
        return array_values(array_filter($this->acceptances, static fn (Acceptance $acceptance): bool => $acceptance->member === $member));
    }

    #[Override]
    public function erase(string $member): int
    {
        $before = \count($this->acceptances);
        $this->acceptances = array_values(array_filter($this->acceptances, static fn (Acceptance $acceptance): bool => $acceptance->member !== $member));

        return $before - \count($this->acceptances);
    }
}
