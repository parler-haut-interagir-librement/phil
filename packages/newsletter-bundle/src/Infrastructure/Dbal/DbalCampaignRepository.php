<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Override;
use Phil\NewsletterBundle\Domain\Contract\CampaignRepository;
use Phil\NewsletterBundle\Domain\Enum\CampaignStatus;
use Phil\NewsletterBundle\Domain\Enum\ClickTracking;
use Phil\NewsletterBundle\Domain\Model\Campaign;
use Phil\NewsletterBundle\Domain\ValueObject\CampaignApproval;
use Phil\NewsletterBundle\Domain\ValueObject\Site;

/**
 * The campaigns in the table `phil_newsletter_campaign`, with their author and approval, the links they track in `phil_newsletter_link`.
 */
final readonly class DbalCampaignRepository implements CampaignRepository
{
    private const string COLUMNS = 'id, site, list_id, subject, body, preheader, segment, template, sender_profile, click_tracking, status, scheduled_at, sent_at, author, approved_by, approved_at';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function save(Campaign $campaign): void
    {
        Sql::upsert($this->connection, NewsletterSchema::CAMPAIGNS, [
            'id' => $campaign->id,
            'site' => $campaign->site->key,
            'list_id' => $campaign->listId,
            'subject' => $campaign->subject,
            'body' => $campaign->body,
            'preheader' => $campaign->preheader,
            'segment' => $campaign->segment,
            'template' => $campaign->template,
            'sender_profile' => $campaign->senderProfile,
            'click_tracking' => $campaign->clickTracking->value,
            'status' => $campaign->status->value,
            'scheduled_at' => $campaign->scheduledAt?->getTimestamp(),
            'sent_at' => $campaign->sentAt?->getTimestamp(),
            'author' => $campaign->author,
            'approved_by' => $campaign->approval?->approver,
            'approved_at' => $campaign->approval?->approvedAt->getTimestamp(),
        ], ['id']);
    }

    #[Override]
    public function get(string $id): ?Campaign
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT %s FROM %s WHERE id = ?', self::COLUMNS, NewsletterSchema::CAMPAIGNS), [$id]);

        return false === $row ? null : $this->campaign($row);
    }

    #[Override]
    public function due(DateTimeImmutable $now): array
    {
        return array_map($this->campaign(...), $this->connection->fetchAllAssociative(
            \sprintf('SELECT %s FROM %s WHERE status = ? AND scheduled_at <= ? ORDER BY scheduled_at, id', self::COLUMNS, NewsletterSchema::CAMPAIGNS),
            [CampaignStatus::Scheduled->value, $now->getTimestamp()],
            [ParameterType::STRING, ParameterType::INTEGER],
        ));
    }

    #[Override]
    public function linkIndex(string $campaignId, string $url): int
    {
        $hash = hash('sha256', $url);
        $known = $this->connection->fetchOne(\sprintf('SELECT link_index FROM %s WHERE campaign_id = ? AND url_hash = ?', NewsletterSchema::LINKS), [$campaignId, $hash]);

        if (false !== $known) {
            return Sql::integer($known);
        }

        $next = Sql::integer($this->connection->fetchOne(\sprintf('SELECT COALESCE(MAX(link_index) + 1, 0) FROM %s WHERE campaign_id = ?', NewsletterSchema::LINKS), [$campaignId]));
        Sql::insertUnlessPresent($this->connection, NewsletterSchema::LINKS, ['campaign_id' => $campaignId, 'link_index' => $next, 'url_hash' => $hash, 'url' => $url], ['campaign_id', 'link_index']);

        return Sql::integer($this->connection->fetchOne(\sprintf('SELECT link_index FROM %s WHERE campaign_id = ? AND url_hash = ?', NewsletterSchema::LINKS), [$campaignId, $hash]));
    }

    #[Override]
    public function link(string $campaignId, int $index): ?string
    {
        $url = $this->connection->fetchOne(\sprintf('SELECT url FROM %s WHERE campaign_id = ? AND link_index = ?', NewsletterSchema::LINKS), [$campaignId, $index], [ParameterType::STRING, ParameterType::INTEGER]);

        return false === $url ? null : Sql::string($url);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function campaign(array $row): Campaign
    {
        $approver = Sql::nullableString($row['approved_by']);

        return Campaign::restore(
            new Campaign(
                Sql::string($row['id']),
                Site::of(Sql::string($row['site'])),
                Sql::string($row['list_id']),
                Sql::string($row['subject']),
                Sql::string($row['body']),
                Sql::string($row['preheader']),
                Sql::nullableString($row['segment']),
                Sql::nullableString($row['template']),
                Sql::nullableString($row['sender_profile']),
                ClickTracking::from(Sql::string($row['click_tracking'])),
                Sql::nullableString($row['author']),
            ),
            CampaignStatus::from(Sql::string($row['status'])),
            Sql::nullableInstant($row['scheduled_at']),
            Sql::nullableInstant($row['sent_at']),
            null === $approver ? null : new CampaignApproval($approver, Sql::instant($row['approved_at'])),
        );
    }
}
