<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Contract;

use Phil\CommunityNotes\Note;
use Phil\Resource\ResourceReference;

/**
 * Who the application lets write and rate notes: its members qualified for it, by seniority, a role or a record of
 * ratings it keeps.
 */
interface Qualification
{
    public function mayWrite(ResourceReference $member, ResourceReference $subject): bool;

    public function mayRate(ResourceReference $member, Note $note): bool;
}
