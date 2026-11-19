<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Contract;

use DateTimeImmutable;
use Phil\GuardianConsent\ConsentRequest;
use Phil\GuardianConsent\GuardianId;
use Phil\GuardianConsent\MemberId;

/**
 * Where the requests sent to guardians are kept until they are answered or erased.
 */
interface RequestStore
{
    public function get(string $id): ?ConsentRequest;

    public function save(ConsentRequest $request): void;

    /**
     * Removes the request and says whether it was still there. Two answers to the same request race here: a store
     * shared by several processes removes it atomically — a `DELETE` whose count of rows it reads —, so that only one
     * of them gets `true`.
     */
    public function consume(string $id): bool;

    /**
     * The member's requests, the earliest sent first.
     *
     * @return list<ConsentRequest>
     */
    public function ofMember(MemberId $member): array;

    /**
     * The requests sent to the guardian, the earliest sent first.
     *
     * @return list<ConsentRequest>
     */
    public function ofGuardian(GuardianId $guardian): array;

    /**
     * The requests that expired at `$before` or earlier, the earliest first, at most `$limit`.
     *
     * @param positive-int $limit
     *
     * @return list<ConsentRequest>
     */
    public function expiredBy(DateTimeImmutable $before, int $limit): array;
}
