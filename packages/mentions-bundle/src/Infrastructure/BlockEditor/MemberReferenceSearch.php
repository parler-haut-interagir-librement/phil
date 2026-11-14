<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Infrastructure\BlockEditor;

use Override;
use Phil\BlockEditorBundle\Application\Exception\InvalidSuggestion;
use Phil\BlockEditorBundle\Application\Reference\ReferenceSearch;
use Phil\BlockEditorBundle\Application\Reference\Suggestion;
use Phil\MentionsBundle\Application\Contract\MemberDirectory;

/**
 * The block editor's `@` menu on the members of the directory: each suggestion is the member's reference, their name
 * as the label, their profile as the address and their handle as the hint, `@ada`. The autocomplete controller of
 * plain text fields reads the same endpoint. A member whose reference the editor cannot write is passed over.
 */
final readonly class MemberReferenceSearch implements ReferenceSearch
{
    public function __construct(
        private MemberDirectory $directory,
    ) {
    }

    #[Override]
    public function search(string $query, int $limit): iterable
    {
        foreach ($this->directory->search(ltrim($query, '@'), $limit) as $member) {
            try {
                yield new Suggestion($member->reference->toString(), $member->name, $member->url, '@'.$member->handle);
            } catch (InvalidSuggestion) {
                continue;
            }
        }
    }
}
