<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Domain\Contract;

use Phil\NewsletterArchive\Domain\Model\ArchivedIssue;
use Phil\NewsletterArchive\Domain\ValueObject\ArchiveFilter;
use Phil\NewsletterArchive\Domain\ValueObject\IssuePage;

/**
 * Where the archived issues are kept.
 */
interface ArchiveRepository
{
    public function save(ArchivedIssue $issue): void;

    /**
     * The issue of a campaign, public or not.
     */
    public function get(string $campaignId): ?ArchivedIssue;

    /**
     * The published issue of a site at a slug; `null` when none is published there.
     */
    public function published(string $site, string $slug): ?ArchivedIssue;

    /**
     * The issue shared by the link of a hash; `null` when no link has it.
     */
    public function byLinkHash(string $linkHash): ?ArchivedIssue;

    /**
     * Whether an issue of the site, public or not, holds the slug.
     */
    public function slugTaken(string $site, string $slug): bool;

    /**
     * A page of the published issues the filter matches, newest sent first.
     */
    public function page(ArchiveFilter $filter): IssuePage;

    /**
     * The keys and names of the lists the site has published issues of, by key.
     *
     * @return array<string, string>
     */
    public function lists(string $site): array;

    /**
     * The tags the site's published issues are filed under, sorted.
     *
     * @return list<string>
     */
    public function tags(string $site): array;
}
