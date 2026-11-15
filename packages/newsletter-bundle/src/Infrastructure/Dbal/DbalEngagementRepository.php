<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Dbal;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Override;
use Phil\NewsletterBundle\Domain\Contract\EngagementRepository;
use Phil\NewsletterBundle\Domain\Enum\UnsubscribeReason;
use Phil\NewsletterBundle\Domain\ValueObject\Site;

/**
 * The clicks in `phil_newsletter_click` (a total per link) and `phil_newsletter_recipient_click` (per recipient, when a
 * campaign chose it), the reasons to leave in `phil_newsletter_unsubscribe_reason`, a total per site, list, reason and
 * UTC day. A count is an insert of a zero row unless it is there, then one `UPDATE` that adds one, so that no click is
 * lost to another at the same moment.
 */
final readonly class DbalEngagementRepository implements EngagementRepository
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function countClick(string $campaignId, int $link): void
    {
        Sql::insertUnlessPresent($this->connection, NewsletterSchema::CLICKS, ['campaign_id' => $campaignId, 'link_index' => $link, 'clicks' => 0], ['campaign_id', 'link_index']);
        $this->connection->executeStatement(\sprintf('UPDATE %s SET clicks = clicks + 1 WHERE campaign_id = ? AND link_index = ?', NewsletterSchema::CLICKS), [$campaignId, $link], [ParameterType::STRING, ParameterType::INTEGER]);
    }

    #[Override]
    public function clicks(string $campaignId): array
    {
        $clicks = [];

        foreach ($this->connection->fetchAllAssociative(\sprintf('SELECT link_index, clicks FROM %s WHERE campaign_id = ? ORDER BY link_index', NewsletterSchema::CLICKS), [$campaignId]) as $row) {
            $clicks[Sql::integer($row['link_index'])] = Sql::integer($row['clicks']);
        }

        return $clicks;
    }

    #[Override]
    public function recordRecipientClick(string $campaignId, int $link, string $subscriberId, DateTimeImmutable $at): void
    {
        Sql::insertUnlessPresent($this->connection, NewsletterSchema::RECIPIENT_CLICKS, [
            'subscriber_id' => $subscriberId,
            'campaign_id' => $campaignId,
            'link_index' => $link,
            'clicked_at' => $at->getTimestamp(),
        ], ['subscriber_id', 'campaign_id', 'link_index', 'clicked_at']);
    }

    #[Override]
    public function recipientClicks(string $subscriberId): array
    {
        return array_map(static fn (array $row): array => [
            'campaign' => Sql::string($row['campaign_id']),
            'link' => Sql::integer($row['link_index']),
            'at' => Sql::instant($row['clicked_at']),
        ], $this->connection->fetchAllAssociative(\sprintf('SELECT campaign_id, link_index, clicked_at FROM %s WHERE subscriber_id = ? ORDER BY clicked_at, campaign_id, link_index', NewsletterSchema::RECIPIENT_CLICKS), [$subscriberId]));
    }

    #[Override]
    public function forgetRecipientClicks(string $subscriberId): int
    {
        return (int) $this->connection->delete(NewsletterSchema::RECIPIENT_CLICKS, ['subscriber_id' => $subscriberId]);
    }

    #[Override]
    public function countUnsubscribeReason(Site $site, ?string $listId, UnsubscribeReason $reason, DateTimeImmutable $day): void
    {
        $key = ['site' => $site->key, 'list_id' => $listId ?? '', 'reason' => $reason->value, 'day' => $this->day($day)];
        Sql::insertUnlessPresent($this->connection, NewsletterSchema::REASONS, [...$key, 'total' => 0], array_keys($key));
        $this->connection->executeStatement(
            \sprintf('UPDATE %s SET total = total + 1 WHERE site = ? AND list_id = ? AND reason = ? AND day = ?', NewsletterSchema::REASONS),
            array_values($key),
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER],
        );
    }

    #[Override]
    public function unsubscribeReasons(Site $site, DateTimeImmutable $since): array
    {
        $reasons = [];

        foreach ($this->connection->fetchAllAssociative(
            \sprintf('SELECT reason, SUM(total) AS total FROM %s WHERE site = ? AND day >= ? GROUP BY reason ORDER BY reason', NewsletterSchema::REASONS),
            [$site->key, $this->day($since)],
            [ParameterType::STRING, ParameterType::INTEGER],
        ) as $row) {
            $reasons[UnsubscribeReason::from(Sql::string($row['reason']))->value] = Sql::integer($row['total']);
        }

        return $reasons;
    }

    private function day(DateTimeImmutable $at): int
    {
        return $at->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0)->getTimestamp();
    }
}
