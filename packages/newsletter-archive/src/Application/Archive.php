<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Application;

use DateTimeImmutable;
use Phil\NewsletterArchive\Application\Contract\WebVersionRenderer;
use Phil\NewsletterArchive\Application\Exception\NotArchivable;
use Phil\NewsletterArchive\Domain\Contract\ArchiveRepository;
use Phil\NewsletterArchive\Domain\Exception\InvalidArchiveValue;
use Phil\NewsletterArchive\Domain\Model\ArchivedIssue;
use Phil\NewsletterArchive\Domain\ValueObject\Tag;
use Phil\NewsletterBundle\Domain\Contract\CampaignRepository;
use Phil\NewsletterBundle\Domain\Contract\MailingListRepository;
use Phil\NewsletterBundle\Domain\Enum\CampaignStatus;
use Phil\NewsletterBundle\Domain\Model\Campaign;
use Phil\NewsletterBundle\Domain\Model\MailingList;
use Phil\Slug\InvalidSlug;
use Phil\Slug\Slugger;
use Psr\Clock\ClockInterface;

/**
 * What an editor does with the archive. Nothing enters it on its own: a sent campaign is published, or shared by a
 * link, when an editor says so, and its web version is frozen then.
 *
 * The slug of an issue comes from its subject, made by the slugger, and stays once given: `-2`, `-3`… set apart two
 * issues of a site with the same subject, and an issue past the twentieth of a subject takes a part of its campaign's
 * hash instead.
 */
final readonly class Archive
{
    private const int SLUG_ATTEMPTS = 20;

    public function __construct(
        private ArchiveRepository $issues,
        private CampaignRepository $campaigns,
        private MailingListRepository $lists,
        private WebVersionRenderer $renderer,
        private Slugger $slugger,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Publishes a sent campaign in the archive, its web version frozen as it renders now, filed under the tags given;
     * an issue published again without tags keeps its own.
     *
     * @param iterable<string> $tags
     *
     * @throws NotArchivable       when the campaign is unknown or not sent
     * @throws InvalidArchiveValue when a tag is not one
     */
    public function publish(string $campaignId, iterable $tags = []): ArchivedIssue
    {
        $issue = $this->freeze($campaignId, Tag::list($tags));
        $issue->publish($this->clock->now());

        $this->issues->save($issue);

        return $issue;
    }

    /**
     * Takes an issue out of the archive and revokes its link; the issue of a campaign never archived is left alone.
     */
    public function unpublish(string $campaignId): void
    {
        $issue = $this->issues->get($campaignId);

        if (!$issue instanceof ArchivedIssue) {
            return;
        }

        $issue->unpublish();
        $this->issues->save($issue);
    }

    /**
     * Shares a sent campaign by a new link, whether it is published or not: a link shared before stops working.
     *
     * @return non-empty-string the token of the link, given once: the archive keeps its hash alone
     *
     * @throws NotArchivable when the campaign is unknown or not sent
     */
    public function share(string $campaignId): string
    {
        $issue = $this->issues->get($campaignId) ?? $this->freeze($campaignId, null);
        $token = LinkTokens::generate();
        $issue->share(LinkTokens::hash($token), $this->clock->now());
        $this->issues->save($issue);

        return $token;
    }

    /**
     * Revokes the link of an issue: whoever holds it reads nothing more.
     */
    public function revokeLink(string $campaignId): void
    {
        $issue = $this->issues->get($campaignId);

        if (!$issue instanceof ArchivedIssue) {
            return;
        }

        $issue->revokeLink();
        $this->issues->save($issue);
    }

    /**
     * Files an archived issue under other tags.
     *
     * @param iterable<string> $tags
     *
     * @throws NotArchivable       when the campaign is not in the archive
     * @throws InvalidArchiveValue when a tag is not one
     */
    public function retag(string $campaignId, iterable $tags): void
    {
        $issue = $this->issues->get($campaignId) ?? throw NotArchivable::notArchived($campaignId);
        $issue->retag(Tag::list($tags));

        $this->issues->save($issue);
    }

    /**
     * The issue of a sent campaign, its web version rendered now: a new one, or the one kept, refreshed.
     *
     * @param list<Tag>|null $tags the tags of the issue; none keeps those of an issue kept
     */
    private function freeze(string $campaignId, ?array $tags): ArchivedIssue
    {
        $campaign = $this->campaigns->get($campaignId) ?? throw NotArchivable::unknown($campaignId);

        if (CampaignStatus::Sent !== $campaign->status || !$campaign->sentAt instanceof DateTimeImmutable) {
            throw NotArchivable::notSent($campaignId);
        }

        $list = $this->lists->get($campaign->listId);
        $html = $this->renderer->render($campaign, $list);
        $now = $this->clock->now();
        $issue = $this->issues->get($campaignId);

        if ($issue instanceof ArchivedIssue) {
            $issue->refresh($campaign->listId, $list->key ?? '', $this->listName($list), $campaign->subject, $campaign->preheader, $html, $now);

            if (null !== $tags && [] !== $tags) {
                $issue->retag($tags);
            }

            return $issue;
        }

        return new ArchivedIssue(
            $campaign->id,
            $campaign->site->key,
            $this->slug($campaign),
            $campaign->listId,
            $list->key ?? '',
            $this->listName($list),
            $campaign->subject,
            $campaign->preheader,
            $html,
            $campaign->sentAt,
            $now,
            $tags ?? [],
        );
    }

    private function listName(?MailingList $list): string
    {
        return $list instanceof MailingList ? $list->name : '';
    }

    private function slug(Campaign $campaign): string
    {
        try {
            $base = $this->slugger->slug($campaign->subject)->value;
        } catch (InvalidSlug) {
            $base = 'issue';
        }

        $base = rtrim(substr($base, 0, 200), '-');
        $slug = $base;

        for ($attempt = 2; $this->issues->slugTaken($campaign->site->key, $slug); ++$attempt) {
            if ($attempt > self::SLUG_ATTEMPTS) {
                return $base.'-'.substr(hash('sha256', $campaign->id), 0, 12);
            }

            $slug = $base.'-'.$attempt;
        }

        return $slug;
    }
}
