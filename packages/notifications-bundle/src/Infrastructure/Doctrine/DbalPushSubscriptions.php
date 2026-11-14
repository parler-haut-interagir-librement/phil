<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\NotificationsBundle\Application\Contract\PushSubscriptions;
use Phil\NotificationsBundle\Application\Exception\InvalidPushSubscription;
use Phil\NotificationsBundle\Application\WebPush\PushSubscription;
use Phil\NotificationsBundle\Domain\Identifiers;
use Psr\Clock\ClockInterface;

/**
 * The push subscriptions in a table, a row per endpoint, keyed by its hash: an endpoint subscribed again moves to its
 * last recipient. A recipient keeps their latest subscriptions, ten by default; a row that is no longer a valid
 * subscription is left out when read.
 */
final readonly class DbalPushSubscriptions implements PushSubscriptions
{
    public const int LIMIT = 10;

    private const string TABLE = NotificationTables::PUSH_SUBSCRIPTIONS;

    /**
     * @param positive-int $limit how many subscriptions a recipient keeps at most
     */
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
        private int $limit = self::LIMIT,
    ) {
    }

    #[Override]
    public function subscribe(string $recipient, PushSubscription $subscription): void
    {
        $row = [
            'recipient' => Identifiers::id($recipient, 'recipient'),
            'endpoint' => $subscription->endpoint,
            'public_key' => $subscription->publicKey,
            'auth_token' => $subscription->authToken,
            'content_encoding' => $subscription->contentEncoding,
            'subscribed_at' => $this->clock->now(),
        ];
        $types = ['subscribed_at' => Types::DATETIME_UTC_IMMUTABLE];
        $id = $subscription->id();

        try {
            $this->connection->transactional(function (Connection $connection) use ($id, $row, $types, $recipient): void {
                $connection->delete(self::TABLE, ['id' => $id]);
                $connection->insert(self::TABLE, ['id' => $id, ...$row], $types);

                // The others of the recipient beyond the limit, the oldest; the one just kept is never among them.
                $others = $connection->fetchFirstColumn(
                    \sprintf('SELECT id FROM %s WHERE recipient = ? AND id <> ? ORDER BY subscribed_at DESC, id', self::TABLE),
                    [$recipient, $id],
                );

                foreach (\array_slice($others, $this->limit - 1) as $older) {
                    $connection->delete(self::TABLE, ['id' => $older]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Another request inserted the same endpoint between the delete and the insert: the row is taken over.
            $this->connection->update(self::TABLE, $row, ['id' => $id], $types);
        }
    }

    #[Override]
    public function of(string $recipient): array
    {
        $rows = $this->connection->fetchAllAssociative(
            \sprintf('SELECT endpoint, public_key, auth_token, content_encoding FROM %s WHERE recipient = ? ORDER BY subscribed_at DESC, id', self::TABLE),
            [$recipient],
        );
        $subscriptions = [];

        foreach ($rows as $row) {
            $stored = new StoredRow($row, 'push subscription');

            try {
                $subscriptions[] = PushSubscription::of($stored->string('endpoint'), $stored->string('public_key'), $stored->string('auth_token'), $stored->string('content_encoding'));
            } catch (InvalidPushSubscription) {
                continue;
            }
        }

        return $subscriptions;
    }

    #[Override]
    public function unsubscribe(string $recipient, string $endpoint): bool
    {
        return 0 < $this->connection->delete(self::TABLE, ['id' => PushSubscription::idOf($endpoint), 'recipient' => $recipient]);
    }

    #[Override]
    public function forget(string $endpoint): void
    {
        $this->connection->delete(self::TABLE, ['id' => PushSubscription::idOf($endpoint)]);
    }

    #[Override]
    public function forgetRecipient(string $recipient): void
    {
        $this->connection->delete(self::TABLE, ['recipient' => $recipient]);
    }
}
