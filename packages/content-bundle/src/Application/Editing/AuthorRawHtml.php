<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Editing;

use Override;
use Phil\ContentBundle\Application\Contract\RawHtmlPermission;

/**
 * The raw HTML permission of the author of the revision being written, which the body data mapper reads.
 *
 * - **Outside `as()`, the author is the user at work**: the permission is the one it wraps, the application's
 *   `RawHtmlPermission` (`RawHtmlRole` with `phil_content.raw_html_role`); `RawHtmlAuthorPass` registers it then.
 * - **Inside `as()`, the author is someone else**: `WorkingCopies` writes a working copy into the draft as its author
 *   saved it, so the revision carries the permission that author held, not the permission of the editor applying it.
 */
final class AuthorRawHtml implements RawHtmlPermission
{
    private ?bool $author = null;

    public function __construct(
        private readonly RawHtmlPermission $user,
    ) {
    }

    #[Override]
    public function isGranted(): bool
    {
        return $this->author ?? $this->userIsGranted();
    }

    /**
     * Whether the user at work holds the permission, whatever revision is being written.
     */
    public function userIsGranted(): bool
    {
        return $this->user->isGranted();
    }

    /**
     * Runs `$edit` as an author who holds the permission, or not.
     *
     * @template T
     *
     * @param callable(): T $edit
     *
     * @return T
     */
    public function as(bool $granted, callable $edit): mixed
    {
        $previous = $this->author;
        $this->author = $granted;

        try {
            return $edit();
        } finally {
            $this->author = $previous;
        }
    }
}
