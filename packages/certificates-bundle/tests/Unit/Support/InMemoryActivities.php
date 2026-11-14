<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Support;

use Override;
use Phil\CertificatesBundle\Domain\Contract\LearningActivityRepository;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\Model\LearningActivity;
use Phil\CertificatesBundle\Domain\ValueObject\Credits;
use Phil\CertificatesBundle\Domain\ValueObject\MemberId;
use Phil\CertificatesBundle\Domain\ValueObject\Period;

final class InMemoryActivities implements LearningActivityRepository
{
    /** @var array<string, LearningActivity> */
    public array $activities = [];

    /** @var list<int> */
    public array $limits = [];

    #[Override]
    public function save(LearningActivity $activity): void
    {
        $this->activities[$activity->id] = $activity;
    }

    #[Override]
    public function remove(LearningActivity $activity): void
    {
        unset($this->activities[$activity->id]);
    }

    #[Override]
    public function find(string $id): ?LearningActivity
    {
        return $this->activities[$id] ?? null;
    }

    #[Override]
    public function findBySource(MemberId $member, string $source): ?LearningActivity
    {
        foreach ($this->activities as $activity) {
            if ($activity->member === $member->value && $activity->source === $source) {
                return $activity;
            }
        }

        return null;
    }

    #[Override]
    public function of(MemberId $member, Period $period, int $limit, int $offset = 0): array
    {
        $this->limits[] = $limit;

        return \array_slice($this->all($member, $period), $offset, $limit);
    }

    #[Override]
    public function total(MemberId $member, Period $period, ?Course $toward = null): Credits
    {
        $total = Credits::zero();

        foreach ($this->all($member, $period) as $activity) {
            if (!$toward instanceof Course || $activity->countsToward($toward)) {
                $total = $total->plus($activity->credits);
            }
        }

        return $total;
    }

    /**
     * @return list<LearningActivity>
     */
    private function all(MemberId $member, Period $period): array
    {
        $of = array_values(array_filter($this->activities, static fn (LearningActivity $activity): bool => $activity->member === $member->value && $period->contains($activity->completedAt)));
        usort($of, static fn (LearningActivity $a, LearningActivity $b): int => $b->completedAt <=> $a->completedAt);

        return $of;
    }
}
