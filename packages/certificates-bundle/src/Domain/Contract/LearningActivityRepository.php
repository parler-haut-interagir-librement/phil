<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Domain\Contract;

use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\Model\LearningActivity;
use Phil\CertificatesBundle\Domain\ValueObject\Credits;
use Phil\CertificatesBundle\Domain\ValueObject\MemberId;
use Phil\CertificatesBundle\Domain\ValueObject\Period;

/**
 * Where the activities of continuing education are kept, and their credits summed.
 */
interface LearningActivityRepository
{
    public function save(LearningActivity $activity): void;

    public function remove(LearningActivity $activity): void;

    public function find(string $id): ?LearningActivity;

    public function findBySource(MemberId $member, string $source): ?LearningActivity;

    /**
     * The member's activities completed within the period, the latest first.
     *
     * @return list<LearningActivity>
     */
    public function of(MemberId $member, Period $period, int $limit, int $offset = 0): array;

    /**
     * The credits of the member's activities completed within the period; with a course, only those that count toward
     * its recertification — the course's own, and those that name no course.
     */
    public function total(MemberId $member, Period $period, ?Course $toward = null): Credits;
}
