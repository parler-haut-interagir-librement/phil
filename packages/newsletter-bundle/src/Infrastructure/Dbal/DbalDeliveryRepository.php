<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Dbal;

use Doctrine\DBAL\Connection;
use Override;
use Phil\NewsletterBundle\Domain\Contract\DeliveryRepository;
use Phil\NewsletterBundle\Domain\Enum\DeliveryStatus;
use Phil\NewsletterBundle\Domain\Model\Delivery;

/**
 * The deliveries in the table `phil_newsletter_delivery`, each created once by an insert that leaves a row of the
 * same id alone.
 */
final readonly class DbalDeliveryRepository implements DeliveryRepository
{
    private const string COLUMNS = 'id, subscriber_id, campaign_id, template, status, attempts, created_at, sent_at';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function create(Delivery $delivery): bool
    {
        return Sql::insertUnlessPresent($this->connection, NewsletterSchema::DELIVERIES, $this->row($delivery), ['id']);
    }

    #[Override]
    public function save(Delivery $delivery): void
    {
        Sql::upsert($this->connection, NewsletterSchema::DELIVERIES, $this->row($delivery), ['id']);
    }

    #[Override]
    public function get(string $id): ?Delivery
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT %s FROM %s WHERE id = ?', self::COLUMNS, NewsletterSchema::DELIVERIES), [$id]);

        return false === $row ? null : $this->delivery($row);
    }

    #[Override]
    public function ofSubscriber(string $subscriberId): array
    {
        return array_map($this->delivery(...), $this->connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s WHERE subscriber_id = ? ORDER BY created_at, id', self::COLUMNS, NewsletterSchema::DELIVERIES), [$subscriberId]));
    }

    #[Override]
    public function countByStatus(string $campaignId): array
    {
        $counts = [];

        foreach ($this->connection->fetchAllAssociative(\sprintf('SELECT status, COUNT(*) AS total FROM %s WHERE campaign_id = ? GROUP BY status ORDER BY status', NewsletterSchema::DELIVERIES), [$campaignId]) as $row) {
            $counts[DeliveryStatus::from(Sql::string($row['status']))->value] = Sql::integer($row['total']);
        }

        return $counts;
    }

    #[Override]
    public function removeOfSubscriber(string $subscriberId): int
    {
        return (int) $this->connection->delete(NewsletterSchema::DELIVERIES, ['subscriber_id' => $subscriberId]);
    }

    /**
     * @return array<string, int|string|null>
     */
    private function row(Delivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'subscriber_id' => $delivery->subscriberId,
            'campaign_id' => $delivery->campaignId,
            'template' => $delivery->template,
            'status' => $delivery->status->value,
            'attempts' => $delivery->attempts,
            'created_at' => $delivery->createdAt->getTimestamp(),
            'sent_at' => $delivery->sentAt?->getTimestamp(),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function delivery(array $row): Delivery
    {
        return Delivery::restore(
            new Delivery(Sql::string($row['id']), Sql::string($row['subscriber_id']), Sql::nullableString($row['campaign_id']), Sql::nullableString($row['template']), Sql::instant($row['created_at'])),
            DeliveryStatus::from(Sql::string($row['status'])),
            Sql::integer($row['attempts']),
            Sql::nullableInstant($row['sent_at']),
        );
    }
}
