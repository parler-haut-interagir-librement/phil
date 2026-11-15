<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Domain\ValueObject\QueueItem;
use Phil\ModerationBundle\Domain\ValueObject\Report;
use Phil\ModerationBundle\Domain\ValueObject\TargetReference;

/**
 * Where the reports are kept.
 */
interface ReportRepository
{
    public function add(Report $report): void;

    /**
     * The reports still open on the target, the earliest first.
     *
     * @return list<Report>
     *
     * @phpstan-impure
     */
    public function openOn(TargetReference $target): array;

    /**
     * The open report of this member on the target, if there is one.
     *
     * @phpstan-impure
     */
    public function openBy(TargetReference $target, MemberId $reporter): ?Report;

    /**
     * The targets with open reports, the earliest report first, of the types given or of every type.
     *
     * @param positive-int     $limit
     * @param non-negative-int $offset
     * @param list<string>     $types
     *
     * @return list<QueueItem>
     *
     * @phpstan-impure
     */
    public function queue(int $limit, int $offset = 0, array $types = []): array;

    /**
     * @param list<string> $types
     *
     * @phpstan-impure
     */
    public function countOpenTargets(array $types = []): int;

    /**
     * Closes the reports open on the target with the decision.
     *
     * @return int how many were closed
     */
    public function close(TargetReference $target, string $decisionId): int;

    /**
     * @phpstan-impure
     */
    public function countSince(DateTimeImmutable $since): int;

    /**
     * The reports the member made, the latest first.
     *
     * @return list<Report>
     *
     * @phpstan-impure
     */
    public function madeBy(MemberId $reporter): array;

    /**
     * Removes the member from the reports they made, which stay without a reporter.
     *
     * @return int how many were changed
     */
    public function forgetReporter(MemberId $reporter): int;
}
