<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Override;
use Phil\PrivacyBundle\Application\Contract\EmailTrackingStore;
use Phil\PrivacyBundle\Domain\EmailTracking\EmailTrackingChoice;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;

/**
 * The members' choices about tracked emails in `phil_privacy_email_tracking`, through DBAL: one row per member, found
 * by its primary key, its date in UTC.
 */
final readonly class DbalEmailTrackingConsents implements EmailTrackingStore
{
    private const array TYPES = ['opens' => ParameterType::BOOLEAN, 'clicks' => ParameterType::BOOLEAN];

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function find(DataSubject $subject): ?EmailTrackingChoice
    {
        $row = $this->connection->fetchAssociative(
            'SELECT version, opens, clicks, decided_at FROM '.PrivacyTables::EMAIL_TRACKING.' WHERE subject = ?',
            [$subject->value],
        );

        if (false === $row) {
            return null;
        }

        return new EmailTrackingChoice(
            $subject,
            \is_string($row['version']) ? $row['version'] : '',
            (bool) $row['opens'],
            (bool) $row['clicks'],
            UtcMoments::read($this->connection, $row['decided_at'], 'decided_at'),
        );
    }

    #[Override]
    public function save(EmailTrackingChoice $choice): void
    {
        $values = [
            'version' => $choice->version,
            'opens' => $choice->opens,
            'clicks' => $choice->clicks,
            'decided_at' => UtcMoments::text($this->connection, $choice->decidedAt),
        ];
        $where = ['subject' => $choice->subject->value];

        if (0 < $this->connection->update(PrivacyTables::EMAIL_TRACKING, $values, $where, self::TYPES)) {
            return;
        }

        try {
            $this->connection->insert(PrivacyTables::EMAIL_TRACKING, $where + $values, self::TYPES);
        } catch (UniqueConstraintViolationException) {
            // Another request of the same member inserted the row in between, or the database counts only the rows an
            // update changed: the last choice wins either way.
            $this->connection->update(PrivacyTables::EMAIL_TRACKING, $values, $where, self::TYPES);
        }
    }

    #[Override]
    public function forget(DataSubject $subject): void
    {
        $this->connection->delete(PrivacyTables::EMAIL_TRACKING, ['subject' => $subject->value]);
    }

    #[Override]
    public function store(): string
    {
        return PrivacyTables::EMAIL_TRACKING;
    }
}
