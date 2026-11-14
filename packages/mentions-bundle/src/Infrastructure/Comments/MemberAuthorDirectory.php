<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Infrastructure\Comments;

use Override;
use Phil\CommentsBundle\Application\AuthorDirectory;
use Phil\MentionsBundle\Application\Contract\MemberDirectory;
use Phil\Resource\ResourceReference;

/**
 * The names of `phil/comments-bundle` authors, read from the member directory: `null` for an author it does not know.
 */
final readonly class MemberAuthorDirectory implements AuthorDirectory
{
    public function __construct(
        private MemberDirectory $directory,
    ) {
    }

    #[Override]
    public function name(ResourceReference $author): ?string
    {
        foreach ($this->directory->members([$author]) as $member) {
            return $member->name;
        }

        return null;
    }
}
