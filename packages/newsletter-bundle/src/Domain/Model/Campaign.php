<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Model;

use DateTimeImmutable;
use Phil\NewsletterBundle\Domain\Enum\CampaignStatus;
use Phil\NewsletterBundle\Domain\Enum\ClickTracking;
use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;
use Phil\NewsletterBundle\Domain\ValueObject\CampaignApproval;
use Phil\NewsletterBundle\Domain\ValueObject\Site;

/**
 * One message sent to a list, or to a segment of it: its subject, its preheader, its body in Markdown, the template
 * that lays it out, the sender profile it goes out with, and how its clicks are counted.
 *
 * It is edited while it is a draft, approved, scheduled, then sent once; a draft or a scheduled campaign may be
 * cancelled. An edit withdraws its approval: what goes out is what was approved.
 */
final class Campaign
{
    public private(set) CampaignStatus $status = CampaignStatus::Draft;

    public private(set) ?DateTimeImmutable $scheduledAt = null;

    public private(set) ?DateTimeImmutable $sentAt = null;

    public private(set) ?CampaignApproval $approval = null;

    /**
     * @param string|null $author the user identifier of who wrote it, which the four-eyes rule compares to the approver's
     */
    public function __construct(
        public readonly string $id,
        public readonly Site $site,
        public readonly string $listId,
        public private(set) string $subject,
        public private(set) string $body,
        public private(set) string $preheader = '',
        public private(set) ?string $segment = null,
        public private(set) ?string $template = null,
        public private(set) ?string $senderProfile = null,
        public private(set) ClickTracking $clickTracking = ClickTracking::Aggregate,
        public readonly ?string $author = null,
    ) {
        $this->checkSubject($subject);

        if (null !== $author && ('' === trim($author) || mb_strlen($author) > 191)) {
            throw InvalidNewsletterValue::because('The author of a campaign is not blank and holds at most 191 characters.');
        }
    }

    /**
     * Restores a campaign as a store keeps it.
     */
    public static function restore(self $campaign, CampaignStatus $status, ?DateTimeImmutable $scheduledAt, ?DateTimeImmutable $sentAt, ?CampaignApproval $approval = null): self
    {
        $campaign->status = $status;
        $campaign->scheduledAt = $scheduledAt;
        $campaign->sentAt = $sentAt;
        $campaign->approval = $approval;

        return $campaign;
    }

    public function edit(string $subject, string $body, string $preheader, ?string $segment, ?string $template, ?string $senderProfile, ClickTracking $clickTracking): void
    {
        if (CampaignStatus::Draft !== $this->status) {
            throw InvalidNewsletterValue::because('Only a draft campaign is edited.');
        }

        $this->checkSubject($subject);
        $this->subject = $subject;
        $this->body = $body;
        $this->preheader = $preheader;
        $this->segment = $segment;
        $this->template = $template;
        $this->senderProfile = $senderProfile;
        $this->clickTracking = $clickTracking;
        $this->approval = null;
    }

    /**
     * Approves the campaign for sending, as it stands.
     *
     * @param bool $fourEyes whether the approver must be someone other than its author; a campaign without an author
     *                       is then never approved
     *
     * @throws InvalidNewsletterValue when the campaign is neither a draft nor scheduled, or the four-eyes rule refuses the approver
     */
    public function approve(string $approver, DateTimeImmutable $at, bool $fourEyes): void
    {
        $this->expect([CampaignStatus::Draft, CampaignStatus::Scheduled], 'approved');

        if ($fourEyes && (null === $this->author || $approver === $this->author)) {
            throw InvalidNewsletterValue::because('Under the four-eyes rule, a campaign is approved by someone other than its author.');
        }

        $this->approval = new CampaignApproval($approver, $at);
    }

    public function withdrawApproval(): void
    {
        $this->expect([CampaignStatus::Draft, CampaignStatus::Scheduled], 'withdrawn from approval');
        $this->approval = null;
    }

    public function isApproved(): bool
    {
        return $this->approval instanceof CampaignApproval;
    }

    public function schedule(DateTimeImmutable $at): void
    {
        $this->expect([CampaignStatus::Draft, CampaignStatus::Scheduled], 'scheduled');
        $this->status = CampaignStatus::Scheduled;
        $this->scheduledAt = $at;
    }

    /**
     * @param bool $approvalRequired whether the campaign is sent only once approved
     *
     * @throws InvalidNewsletterValue when the campaign is neither a draft nor scheduled, or awaits its approval
     */
    public function start(bool $approvalRequired = true): void
    {
        $this->expect([CampaignStatus::Draft, CampaignStatus::Scheduled], 'sent');

        if ($approvalRequired && !$this->isApproved()) {
            throw InvalidNewsletterValue::because('A campaign is sent once it is approved.');
        }

        $this->status = CampaignStatus::Sending;
    }

    public function finish(DateTimeImmutable $now): void
    {
        $this->expect([CampaignStatus::Sending], 'finished');
        $this->status = CampaignStatus::Sent;
        $this->sentAt = $now;
    }

    public function cancel(): void
    {
        $this->expect([CampaignStatus::Draft, CampaignStatus::Scheduled], 'cancelled');
        $this->status = CampaignStatus::Cancelled;
    }

    /**
     * @param list<CampaignStatus> $allowed
     */
    private function expect(array $allowed, string $what): void
    {
        if (!\in_array($this->status, $allowed, true)) {
            throw InvalidNewsletterValue::because(\sprintf('A campaign that is %s cannot be %s.', $this->status->value, $what));
        }
    }

    private function checkSubject(string $subject): void
    {
        if ('' === trim($subject) || mb_strlen($subject) > 200 || 1 === preg_match('/[\x00-\x1F\x7F]/', $subject)) {
            throw InvalidNewsletterValue::because('The subject of a campaign is not blank, holds at most 200 characters and no control character.');
        }
    }
}
