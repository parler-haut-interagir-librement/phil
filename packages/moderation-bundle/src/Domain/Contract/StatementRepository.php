<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Domain\Contract;

use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Domain\ValueObject\StatementOfReasons;

/**
 * Where the statements of reasons are kept, for the member each one is addressed to.
 */
interface StatementRepository
{
    public function add(StatementOfReasons $statement): void;

    /**
     * @phpstan-impure
     */
    public function find(string $id): ?StatementOfReasons;

    /**
     * The statement that came with a decision, when one did.
     *
     * @phpstan-impure
     */
    public function forDecision(string $decisionId): ?StatementOfReasons;

    /**
     * The statements addressed to the member, the latest first.
     *
     * @param positive-int     $limit
     * @param non-negative-int $offset
     *
     * @return list<StatementOfReasons>
     *
     * @phpstan-impure
     */
    public function addressedTo(MemberId $recipient, int $limit, int $offset = 0): array;

    /**
     * @phpstan-impure
     */
    public function countAddressedTo(MemberId $recipient): int;

    /**
     * Deletes the statements addressed to the member.
     *
     * @return int how many were deleted
     */
    public function erase(MemberId $recipient): int;
}
