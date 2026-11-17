<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use InvalidArgumentException;
use Override;
use Phil\LtiBundle\Application\Contract\DeepLinkingReturns;
use Phil\LtiBundle\Application\StoredDeepLinkingReturn;

/**
 * The deep linking responses the platform verified, in a table: their claims in JSON, of `MAX_PAYLOAD` bytes at most —
 * the token they came in is 64 KiB at most —, kept until they expire. The claims are those the tool picked for the
 * platform: content items, the data the platform put in its request, messages; no credential and no claim about a
 * person, so they are kept as they are.
 */
final readonly class DbalDeepLinkingReturns implements DeepLinkingReturns
{
    /**
     * The longest claims kept, in bytes of JSON: what a TEXT column of MySQL holds.
     */
    public const int MAX_PAYLOAD = 65_535;

    public function __construct(
        private Connection $connection,
        private LtiTables $tables,
    ) {
    }

    #[Override]
    public function save(StoredDeepLinkingReturn $return): void
    {
        $payload = json_encode($return->claims, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        if (\strlen($payload) > self::MAX_PAYLOAD) {
            throw new InvalidArgumentException(\sprintf('The claims of a deep linking response are kept up to %d bytes of JSON.', self::MAX_PAYLOAD));
        }

        try {
            $this->connection->transactional(function (Connection $connection) use ($return, $payload): void {
                $connection->insert($this->tables->deepLinkingReturns, [
                    'id' => $return->key,
                    'client_id' => $return->clientId,
                    'payload' => $payload,
                    'received_at' => $return->receivedAt->getTimestamp(),
                    'expires_at' => $return->expiresAt->getTimestamp(),
                    'handled_at' => $return->handledAt?->getTimestamp(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // The response is kept already, under its key.
        }
    }

    #[Override]
    public function find(string $key, DateTimeImmutable $now): ?StoredDeepLinkingReturn
    {
        $row = $this->connection->fetchAssociative(
            \sprintf('SELECT * FROM %s WHERE id = ? AND expires_at > ?', $this->tables->deepLinkingReturns),
            [$key, $now->getTimestamp()],
            [ParameterType::STRING, ParameterType::INTEGER],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    #[Override]
    public function markHandled(string $key, DateTimeImmutable $at): void
    {
        $this->connection->update($this->tables->deepLinkingReturns, ['handled_at' => $at->getTimestamp()], ['id' => $key]);
    }

    #[Override]
    public function unhandled(DateTimeImmutable $before, DateTimeImmutable $now, int $limit): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->tables->deepLinkingReturns)
            ->where('handled_at IS NULL', 'received_at <= :before', 'expires_at > :now')
            ->setParameter('before', $before->getTimestamp(), ParameterType::INTEGER)
            ->setParameter('now', $now->getTimestamp(), ParameterType::INTEGER)
            ->orderBy('received_at')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map($this->hydrate(...), $rows);
    }

    #[Override]
    public function purge(DateTimeImmutable $now): int
    {
        return (int) $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE expires_at <= ?', $this->tables->deepLinkingReturns), [$now->getTimestamp()]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): StoredDeepLinkingReturn
    {
        $claims = json_decode(Row::string($row, 'payload'), true, 64, \JSON_THROW_ON_ERROR);
        $handled = Row::optionalInt($row, 'handled_at');

        return new StoredDeepLinkingReturn(
            Row::string($row, 'id'),
            Row::string($row, 'client_id'),
            \is_array($claims) ? $claims : [],
            new DateTimeImmutable('@'.Row::int($row, 'received_at')),
            new DateTimeImmutable('@'.Row::int($row, 'expires_at')),
            null === $handled ? null : new DateTimeImmutable('@'.$handled),
        );
    }
}
