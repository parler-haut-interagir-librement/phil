<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Application\Contract;

use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * The parents of a subject, nearest first — the thread of a reply, the category of a thread, the forum of a category —,
 * as the application knows them: following a parent follows its children, unless the member chose otherwise on a
 * nearer subject. Asked once per activity; the bundle reads a bounded number of them and stops at the first repeat.
 */
interface SubjectParents
{
    /**
     * @return iterable<SubjectReference>
     */
    public function parentsOf(SubjectReference $subject): iterable;
}
