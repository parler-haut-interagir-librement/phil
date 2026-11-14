<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Domain\Contract;

use Phil\MentionsBundle\Domain\Model\RecordedMention;
use Phil\Resource\ResourceReference;

/**
 * Where the mentions of each subject are kept.
 */
interface MentionRepository
{
    /**
     * The mentions of a subject, in the order they were recorded.
     *
     * @return list<RecordedMention>
     */
    public function ofSubject(ResourceReference $subject): array;

    /**
     * The subjects that mention a member, the latest first, at most `$limit`.
     *
     * @return list<RecordedMention>
     */
    public function ofMember(ResourceReference $member, int $limit): array;

    /**
     * Removes and adds mentions in one change. A mention added for a member its subject already mentions — written
     * meanwhile by another request — is left out, the row found kept.
     *
     * @param list<RecordedMention> $added
     * @param list<RecordedMention> $removed
     *
     * @return list<RecordedMention> the mentions added, in the order given
     */
    public function change(array $added, array $removed): array;

    /**
     * Removes every mention of a subject, as the subject is deleted.
     */
    public function forget(ResourceReference $subject): void;
}
