<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Domain\Contract;

use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Domain\ValueObject\MemberNote;

/**
 * Where the notes of the moderation on members are kept.
 */
interface MemberNoteRepository
{
    public function add(MemberNote $note): void;

    /**
     * @phpstan-impure
     */
    public function find(string $id): ?MemberNote;

    /**
     * @return list<MemberNote> the latest first
     *
     * @phpstan-impure
     */
    public function about(MemberId $member): array;

    public function remove(string $id): bool;

    /**
     * Deletes the notes on the member, and removes them as the author of the others.
     *
     * @return int how many notes were deleted or changed
     */
    public function erase(MemberId $member): int;
}
