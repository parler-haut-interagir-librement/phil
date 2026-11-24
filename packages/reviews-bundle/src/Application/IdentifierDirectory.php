<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Application;

use Override;
use Phil\ReviewsBundle\Application\Contract\ReviewerDirectory;
use Phil\ReviewsBundle\Application\Contract\SubjectDirectory;
use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;

/**
 * The names of an application that gives none: a member by their identifier, a subject by its type and identifier,
 * without an address.
 */
final readonly class IdentifierDirectory implements ReviewerDirectory, SubjectDirectory
{
    #[Override]
    public function displayName(MemberId $member): string
    {
        return $member->value;
    }

    #[Override]
    public function title(SubjectRef $subject): string
    {
        return $subject->type.' '.$subject->id;
    }

    #[Override]
    public function url(SubjectRef $subject): ?string
    {
        return null;
    }
}
