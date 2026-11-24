<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Application;

use Phil\NewsletterArchive\Application\Contract\IssueUrls;
use Phil\NewsletterArchive\Domain\Contract\ArchiveRepository;
use Phil\NewsletterArchive\Domain\Model\ArchivedIssue;
use Phil\NewsletterArchive\Domain\ValueObject\ArchiveFilter;
use Phil\NewsletterArchive\Domain\ValueObject\Tag;

/**
 * The latest published issues of a site as feed entries, for a feed to read: the archive builds no feed itself.
 */
final readonly class IssueFeed
{
    public function __construct(
        private ArchiveRepository $issues,
        private IssueUrls $urls,
    ) {
    }

    /**
     * @param int<1, 100> $limit
     *
     * @return list<FeedItem> newest sent first
     */
    public function latest(string $site, int $limit = 20, ?string $listKey = null, ?string $tag = null): array
    {
        $page = $this->issues->page(new ArchiveFilter($site, $listKey, null === $tag ? null : Tag::of($tag), 1, $limit));

        return array_map(fn (ArchivedIssue $issue): FeedItem => new FeedItem(
            $issue->campaignId,
            $issue->subject,
            $this->urls->issue($issue),
            $issue->preheader,
            $issue->html,
            $issue->sentAt,
            $issue->tagValues(),
        ), $page->issues);
    }
}
