<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Tests\Unit\Fixture;

use Override;
use Phil\CommunityNotes\Contract\Qualification;
use Phil\CommunityNotes\Note;
use Phil\Resource\ResourceReference;

/**
 * Every member is qualified but those listed.
 */
final class Members implements Qualification
{
    /** @var list<string> */
    public array $writersRefused = [];

    /** @var list<string> */
    public array $ratersRefused = [];

    #[Override]
    public function mayWrite(ResourceReference $member, ResourceReference $subject): bool
    {
        return !\in_array($member->toString(), $this->writersRefused, true);
    }

    #[Override]
    public function mayRate(ResourceReference $member, Note $note): bool
    {
        return !\in_array($member->toString(), $this->ratersRefused, true);
    }
}
