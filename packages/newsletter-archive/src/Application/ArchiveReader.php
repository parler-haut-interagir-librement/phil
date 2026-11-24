<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Application;

use Phil\NewsletterArchive\Domain\Contract\ArchiveRepository;
use Phil\NewsletterArchive\Domain\Model\ArchivedIssue;
use Phil\NewsletterArchive\Domain\ValueObject\ArchiveFilter;
use Phil\NewsletterArchive\Domain\ValueObject\IssuePage;
use SensitiveParameter;

/**
 * What the public reads of the archive: a page of the published issues, an issue at its slug, an issue by the link it
 * is shared by, and the lists and tags the archive is filtered by.
 */
final readonly class ArchiveReader
{
    public function __construct(
        private ArchiveRepository $issues,
    ) {
    }

    public function page(ArchiveFilter $filter): IssuePage
    {
        return $this->issues->page($filter);
    }

    /**
     * The published issue of the site at the slug; `null` when none is published there.
     */
    public function issue(string $site, string $slug): ?ArchivedIssue
    {
        return $this->issues->published($site, $slug);
    }

    /**
     * The issue shared by the link of the token; `null` for a token of another shape, revoked, or never issued.
     */
    public function shared(#[SensitiveParameter] string $token): ?ArchivedIssue
    {
        return LinkTokens::isWellFormed($token) ? $this->issues->byLinkHash(LinkTokens::hash($token)) : null;
    }

    /**
     * @return array<string, string> the keys and names of the lists with published issues
     */
    public function lists(string $site): array
    {
        return $this->issues->lists($site);
    }

    /**
     * @return list<string> the tags of the published issues
     */
    public function tags(string $site): array
    {
        return $this->issues->tags($site);
    }
}
