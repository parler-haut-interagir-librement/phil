<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Database;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Override;
use Phil\PrivacyBundle\Application\Contract\AccountDeletionStore;
use Phil\PrivacyBundle\Application\Contract\RetentionPurger;
use Phil\PrivacyBundle\Domain\AccountDeletion\AccountDeletion;
use Phil\PrivacyBundle\Domain\AccountDeletion\DeletionPlace;
use Phil\PrivacyBundle\Domain\Retention\PurgeOrder;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;

/**
 * The account deletions in `phil_privacy_account_deletion`, through DBAL, every date in UTC.
 *
 * It answers for the store of the same name in a retention schedule: a line on it removes the requests closed before
 * the cutoff, never one that is open.
 */
final readonly class DbalAccountDeletions implements AccountDeletionStore, RetentionPurger
{
    private const string COLUMNS = 'id, subject, place, requested_at, effective_at, closed_at, export_reference';

    private const array OPEN = [DeletionPlace::Requested->value, DeletionPlace::Exported->value];

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function find(string $id): ?AccountDeletion
    {
        $row = $this->connection->fetchAssociative('SELECT '.self::COLUMNS.' FROM '.PrivacyTables::DELETIONS.' WHERE id = ?', [$id]);

        return false === $row ? null : $this->hydrate($row);
    }

    #[Override]
    public function openFor(DataSubject $subject): ?AccountDeletion
    {
        $row = $this->connection->fetchAssociative(
            'SELECT '.self::COLUMNS.' FROM '.PrivacyTables::DELETIONS.' WHERE subject = ? AND place IN (?) ORDER BY requested_at DESC',
            [$subject->value, self::OPEN],
            [ParameterType::STRING, ArrayParameterType::STRING],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    #[Override]
    public function save(AccountDeletion $deletion): void
    {
        $values = [
            'subject' => $deletion->subject->value,
            'place' => $deletion->getPlace()->value,
            'requested_at' => $this->text($deletion->requestedAt),
            'effective_at' => $this->text($deletion->effectiveAt),
            'closed_at' => UtcMoments::optionalText($this->connection, $deletion->closedAt()),
            'export_reference' => $deletion->exportReference(),
        ];

        // Under the lock of the person, so that nobody inserts the row between the read and the write.
        if (false === $this->connection->fetchOne('SELECT 1 FROM '.PrivacyTables::DELETIONS.' WHERE id = ?', [$deletion->id])) {
            $this->connection->insert(PrivacyTables::DELETIONS, ['id' => $deletion->id] + $values);
        } else {
            $this->connection->update(PrivacyTables::DELETIONS, $values, ['id' => $deletion->id]);
        }
    }

    #[Override]
    public function dueIdentifiers(DateTimeImmutable $now, int $limit): array
    {
        return array_values(array_filter($this->connection->fetchFirstColumn(
            $this->connection->getDatabasePlatform()->modifyLimitQuery('SELECT id FROM '.PrivacyTables::DELETIONS.' WHERE place IN (?) AND effective_at <= ? ORDER BY effective_at', $limit),
            [self::OPEN, $this->text($now)],
            [ArrayParameterType::STRING, ParameterType::STRING],
        ), is_string(...)));
    }

    #[Override]
    public function store(): string
    {
        return PrivacyTables::DELETIONS;
    }

    #[Override]
    public function purge(PurgeOrder $order): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE FROM '.PrivacyTables::DELETIONS.' WHERE closed_at IS NOT NULL AND closed_at < ?',
            [$this->text($order->cutoff)],
        );
    }

    private function text(DateTimeImmutable $moment): string
    {
        return UtcMoments::text($this->connection, $moment);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): AccountDeletion
    {
        return new AccountDeletion(
            $this->string($row, 'id'),
            DataSubject::fromString($this->string($row, 'subject')),
            DeletionPlace::from($this->string($row, 'place')),
            UtcMoments::read($this->connection, $row['requested_at'], 'requested_at'),
            UtcMoments::read($this->connection, $row['effective_at'], 'effective_at'),
            UtcMoments::readOptional($this->connection, $row['closed_at'], 'closed_at'),
            \is_string($row['export_reference']) ? $row['export_reference'] : null,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function string(array $row, string $column): string
    {
        return \is_string($row[$column]) ? $row[$column] : '';
    }
}
