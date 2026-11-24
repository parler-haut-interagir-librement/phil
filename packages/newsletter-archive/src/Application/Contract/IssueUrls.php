<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Application\Contract;

use Phil\NewsletterArchive\Domain\Model\ArchivedIssue;

/**
 * The absolute addresses of the archive's pages: an issue at its slug, and the link an issue is shared by.
 */
interface IssueUrls
{
    public function issue(ArchivedIssue $issue): string;

    public function link(string $token): string;
}
