<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Security;

use Phil\CommunityNotes\Note;
use Phil\Resource\ResourceReference;

/**
 * What a voter of the application decides on: the member who writes or rates, the resource the note is about, and
 * the note being rated, `null` while one is being written.
 */
final readonly class QualificationSubject
{
    public function __construct(
        public ResourceReference $member,
        public ResourceReference $subject,
        public ?Note $note,
    ) {
    }
}
