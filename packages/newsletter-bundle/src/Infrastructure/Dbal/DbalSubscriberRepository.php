<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Override;
use Phil\NewsletterBundle\Domain\Contract\SubscriberRepository;
use Phil\NewsletterBundle\Domain\Enum\MembershipStatus;
use Phil\NewsletterBundle\Domain\Enum\SubscriberStatus;
use Phil\NewsletterBundle\Domain\Model\Membership;
use Phil\NewsletterBundle\Domain\Model\Subscriber;
use Phil\NewsletterBundle\Domain\ValueObject\Site;

/**
 * The subscribers in the table `phil_newsletter_subscriber`, their memberships in `phil_newsletter_membership`.
 */
final readonly class DbalSubscriberRepository implements SubscriberRepository
{
    private const string COLUMNS = 'id, site, email, external_id, status, created_at, confirmed_at, paused_until, soft_bounces';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function save(Subscriber $subscriber): void
    {
        Sql::upsert($this->connection, NewsletterSchema::SUBSCRIBERS, [
            'id' => $subscriber->id,
            'site' => $subscriber->site->key,
            'email' => $subscriber->email,
            'email_key' => $subscriber->emailKey(),
            'external_id' => $subscriber->externalId,
            'status' => $subscriber->status->value,
            'created_at' => $subscriber->createdAt->getTimestamp(),
            'confirmed_at' => $subscriber->confirmedAt?->getTimestamp(),
            'paused_until' => $subscriber->pausedUntil?->getTimestamp(),
            'soft_bounces' => $subscriber->softBounces,
        ], ['id']);
    }

    #[Override]
    public function get(string $id): ?Subscriber
    {
        return $this->one('id = ?', [$id]);
    }

    #[Override]
    public function byEmail(Site $site, string $emailKey): ?Subscriber
    {
        return $this->one('site = ? AND email_key = ?', [$site->key, $emailKey]);
    }

    #[Override]
    public function byExternalId(Site $site, string $externalId): ?Subscriber
    {
        return $this->one('site = ? AND external_id = ?', [$site->key, $externalId]);
    }

    #[Override]
    public function withExternalId(string $externalId): array
    {
        return array_map($this->subscriber(...), $this->connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s WHERE external_id = ? ORDER BY site', self::COLUMNS, NewsletterSchema::SUBSCRIBERS), [$externalId]));
    }

    #[Override]
    public function remove(string $id): void
    {
        $this->connection->delete(NewsletterSchema::MEMBERSHIPS, ['subscriber_id' => $id]);
        $this->connection->delete(NewsletterSchema::SUBSCRIBERS, ['id' => $id]);
    }

    #[Override]
    public function saveMembership(Membership $membership): void
    {
        Sql::upsert($this->connection, NewsletterSchema::MEMBERSHIPS, [
            'subscriber_id' => $membership->subscriberId,
            'list_id' => $membership->listId,
            'status' => $membership->status->value,
            'changed_at' => $membership->changedAt->getTimestamp(),
        ], ['subscriber_id', 'list_id']);
    }

    #[Override]
    public function membership(string $subscriberId, string $listId): ?Membership
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT subscriber_id, list_id, status, changed_at FROM %s WHERE subscriber_id = ? AND list_id = ?', NewsletterSchema::MEMBERSHIPS), [$subscriberId, $listId]);

        return false === $row ? null : $this->membershipOf($row);
    }

    #[Override]
    public function memberships(string $subscriberId): array
    {
        return array_map($this->membershipOf(...), $this->connection->fetchAllAssociative(\sprintf('SELECT subscriber_id, list_id, status, changed_at FROM %s WHERE subscriber_id = ? ORDER BY list_id', NewsletterSchema::MEMBERSHIPS), [$subscriberId]));
    }

    #[Override]
    public function recipients(string $listId, DateTimeImmutable $now, ?string $after, int $limit): array
    {
        $query = $this->connection->createQueryBuilder()
            ->select('m.subscriber_id')
            ->from(NewsletterSchema::MEMBERSHIPS, 'm')
            ->innerJoin('m', NewsletterSchema::SUBSCRIBERS, 's', 's.id = m.subscriber_id')
            ->where('m.list_id = :list')
            ->andWhere('m.status = :active')
            ->andWhere('s.status = :active OR (s.status = :paused AND s.paused_until <= :now)')
            ->orderBy('m.subscriber_id')
            ->setMaxResults(max(1, $limit))
            ->setParameter('list', $listId)
            ->setParameter('active', MembershipStatus::Active->value)
            ->setParameter('paused', SubscriberStatus::Paused->value)
            ->setParameter('now', $now->getTimestamp(), ParameterType::INTEGER);

        if (null !== $after) {
            $query->andWhere('m.subscriber_id > :after')->setParameter('after', $after);
        }

        return array_map(Sql::string(...), $query->executeQuery()->fetchFirstColumn());
    }

    /**
     * @param list<string> $parameters
     */
    private function one(string $where, array $parameters): ?Subscriber
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT %s FROM %s WHERE %s', self::COLUMNS, NewsletterSchema::SUBSCRIBERS, $where), $parameters);

        return false === $row ? null : $this->subscriber($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function subscriber(array $row): Subscriber
    {
        return Subscriber::restore(
            Sql::string($row['id']),
            Site::of(Sql::string($row['site'])),
            Sql::string($row['email']),
            Sql::nullableString($row['external_id']),
            SubscriberStatus::from(Sql::string($row['status'])),
            Sql::instant($row['created_at']),
            Sql::nullableInstant($row['confirmed_at']),
            Sql::nullableInstant($row['paused_until']),
            Sql::integer($row['soft_bounces']),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function membershipOf(array $row): Membership
    {
        return new Membership(Sql::string($row['subscriber_id']), Sql::string($row['list_id']), MembershipStatus::from(Sql::string($row['status'])), Sql::instant($row['changed_at']));
    }
}
