<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\AccessLinks\CampaignCounts;
use Phil\AccessLinks\Contract\InvitationStore;
use Phil\AccessLinks\Exception\InvalidConfiguration;
use Phil\AccessLinks\Invitation;
use Phil\AccessLinks\TokenHasher;
use Throwable;
use UnexpectedValueException;

/**
 * The invitations and the hashes of their links in two tables of a Doctrine DBAL connection, on PostgreSQL, MySQL,
 * MariaDB or SQLite; instants kept as Unix timestamps.
 *
 * Each change is one conditional `UPDATE`, the atomic step: an answer is recorded only where the invitation is open
 * and not closed at that moment; a reminder is claimed only where the count of reminders sent is still the one read,
 * so that of two workers one wins; a link is added only where the invitation has fewer links than allowed. The
 * uniqueness of a recipient per campaign is the table's.
 */
final readonly class DbalInvitationStore implements InvitationStore
{
    public const string INVITATION_TABLE = 'phil_access_link_invitation';

    public const string LINK_TABLE = 'phil_access_link';

    private const string COLUMNS = 'i.id, i.campaign, i.recipient, i.created_at, i.valid_from, i.closes_at, i.expires_at, i.answer, i.responded_at, i.answered_at, i.reminders_sent, i.next_reminder_at, i.revoked_at';

    /** @var non-empty-string */
    private string $invitationTable;

    /** @var non-empty-string */
    private string $linkTable;

    public function __construct(
        private Connection $connection,
        string $invitationTable = self::INVITATION_TABLE,
        string $linkTable = self::LINK_TABLE,
    ) {
        $this->invitationTable = $this->tableName($invitationTable);
        $this->linkTable = $this->tableName($linkTable);
    }

    /**
     * Adds both tables to a schema that lacks them: the one a migration builds, or the one the ORM generates, from a
     * listener of its `postGenerateSchema` event.
     */
    public function configureSchema(Schema $schema): Schema
    {
        $editor = $schema->edit();

        if (!$schema->hasTable($this->invitationTable)) {
            $editor->addTable($this->invitationTableDefinition());
        }

        if (!$schema->hasTable($this->linkTable)) {
            $editor->addTable($this->linkTableDefinition());
        }

        return $editor->create();
    }

    #[Override]
    public function add(Invitation $invitation, string $tokenHash): bool
    {
        $this->connection->beginTransaction();

        try {
            $this->connection->insert($this->invitationTable, [
                'id' => $invitation->id,
                'campaign' => $invitation->campaign,
                'recipient' => $invitation->recipient,
                'created_at' => $invitation->createdAt->getTimestamp(),
                'valid_from' => $invitation->validFrom->getTimestamp(),
                'closes_at' => $invitation->closesAt->getTimestamp(),
                'expires_at' => $invitation->expiresAt->getTimestamp(),
                'reminders_sent' => $invitation->remindersSent,
                'next_reminder_at' => $invitation->nextReminderAt?->getTimestamp(),
                'links_issued' => 1,
            ], [
                ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER,
                ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER,
                $invitation->nextReminderAt instanceof DateTimeImmutable ? ParameterType::INTEGER : ParameterType::NULL, ParameterType::INTEGER,
            ]);
            $this->insertLink($invitation->id, $tokenHash);
            $this->connection->commit();

            return true;
        } catch (UniqueConstraintViolationException) {
            $this->connection->rollBack();

            return false;
        } catch (Throwable $throwable) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            throw $throwable;
        }
    }

    #[Override]
    public function addLink(string $invitationId, string $tokenHash, int $maxLinks): bool
    {
        $this->connection->beginTransaction();

        try {
            if (1 > $this->connection->executeStatement(
                \sprintf('UPDATE %s SET links_issued = links_issued + 1 WHERE id = ? AND links_issued < ?', $this->invitationTable),
                [$invitationId, $maxLinks],
                [ParameterType::STRING, ParameterType::INTEGER],
            )) {
                $this->connection->rollBack();

                return false;
            }

            $this->insertLink($invitationId, $tokenHash);
            $this->connection->commit();

            return true;
        } catch (Throwable $throwable) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            throw $throwable;
        }
    }

    #[Override]
    public function get(string $invitationId): ?Invitation
    {
        $row = $this->connection->fetchAssociative(
            \sprintf('SELECT %s FROM %s i WHERE i.id = ?', self::COLUMNS, $this->invitationTable),
            [$invitationId],
            [ParameterType::STRING],
        );

        return false === $row ? null : $this->invitation($row);
    }

    #[Override]
    public function findByToken(array $candidates): ?Invitation
    {
        $row = $this->connection->fetchAssociative(
            \sprintf('SELECT %s FROM %s l INNER JOIN %s i ON i.id = l.invitation_id WHERE l.hash IN (?)', self::COLUMNS, $this->linkTable, $this->invitationTable),
            [$candidates],
            [ArrayParameterType::STRING],
        );

        return false === $row ? null : $this->invitation($row);
    }

    #[Override]
    public function respond(string $invitationId, string $answer, DateTimeImmutable $now): bool
    {
        $at = $now->getTimestamp();

        return 0 < $this->connection->executeStatement(
            \sprintf('UPDATE %s SET answer = ?, responded_at = COALESCE(responded_at, ?), answered_at = ?, next_reminder_at = NULL WHERE id = ? AND revoked_at IS NULL AND valid_from <= ? AND closes_at > ? AND expires_at > ?', $this->invitationTable),
            [$answer, $at, $at, $invitationId, $at, $at, $at],
            [ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER],
        );
    }

    #[Override]
    public function claimReminder(string $invitationId, int $sent, ?DateTimeImmutable $next, DateTimeImmutable $now): bool
    {
        $at = $now->getTimestamp();

        return 0 < $this->connection->executeStatement(
            \sprintf('UPDATE %s SET reminders_sent = reminders_sent + 1, next_reminder_at = ? WHERE id = ? AND reminders_sent = ? AND responded_at IS NULL AND revoked_at IS NULL AND closes_at > ? AND next_reminder_at <= ?', $this->invitationTable),
            [$next?->getTimestamp(), $invitationId, $sent, $at, $at],
            [$next instanceof DateTimeImmutable ? ParameterType::INTEGER : ParameterType::NULL, ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER],
        );
    }

    #[Override]
    public function due(DateTimeImmutable $now, int $limit): array
    {
        $at = $now->getTimestamp();

        return array_map($this->text(...), $this->connection->fetchFirstColumn(
            \sprintf('SELECT id FROM %s WHERE responded_at IS NULL AND revoked_at IS NULL AND next_reminder_at <= ? AND closes_at > ? ORDER BY next_reminder_at, id LIMIT %d', $this->invitationTable, max(1, $limit)),
            [$at, $at],
            [ParameterType::INTEGER, ParameterType::INTEGER],
        ));
    }

    #[Override]
    public function revoke(string $invitationId, DateTimeImmutable $now): bool
    {
        return 0 < $this->connection->executeStatement(
            \sprintf('UPDATE %s SET revoked_at = ? WHERE id = ? AND revoked_at IS NULL', $this->invitationTable),
            [$now->getTimestamp(), $invitationId],
            [ParameterType::INTEGER, ParameterType::STRING],
        );
    }

    #[Override]
    public function counts(string $campaign): CampaignCounts
    {
        $row = $this->connection->fetchAssociative(
            \sprintf('SELECT COUNT(*) AS invited, COUNT(responded_at) AS responded, COALESCE(SUM(CASE WHEN revoked_at IS NOT NULL AND responded_at IS NULL THEN 1 ELSE 0 END), 0) AS revoked FROM %s WHERE campaign = ?', $this->invitationTable),
            [$campaign],
            [ParameterType::STRING],
        );

        if (false === $row) {
            throw new UnexpectedValueException('The count of a campaign returned no row.');
        }

        return new CampaignCounts($this->integer($row['invited']), $this->integer($row['responded']), $this->integer($row['revoked']));
    }

    private function insertLink(string $invitationId, string $tokenHash): void
    {
        $this->connection->insert($this->linkTable, ['hash' => $tokenHash, 'invitation_id' => $invitationId], [ParameterType::STRING, ParameterType::STRING]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function invitation(array $row): Invitation
    {
        return new Invitation(
            $this->text($row['id']),
            $this->text($row['campaign']),
            $this->text($row['recipient']),
            $this->instant($row['created_at']),
            $this->instant($row['closes_at']),
            $this->instant($row['expires_at']),
            $this->instant($row['valid_from']),
            null === $row['answer'] ? null : $this->text($row['answer']),
            $this->optionalInstant($row['responded_at']),
            $this->optionalInstant($row['answered_at']),
            $this->integer($row['reminders_sent']),
            $this->optionalInstant($row['next_reminder_at']),
            $this->optionalInstant($row['revoked_at']),
        );
    }

    private function instant(mixed $value): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.$this->integer($value));
    }

    private function optionalInstant(mixed $value): ?DateTimeImmutable
    {
        return null === $value ? null : $this->instant($value);
    }

    private function integer(mixed $value): int
    {
        if (!is_numeric($value)) {
            throw new UnexpectedValueException('A column of the invitations is not a number.');
        }

        return (int) $value;
    }

    private function text(mixed $value): string
    {
        if (!\is_string($value)) {
            throw new UnexpectedValueException('A column of the invitations is not text.');
        }

        return $value;
    }

    /**
     * @return non-empty-string
     */
    private function tableName(string $name): string
    {
        if (1 !== preg_match('/^[a-z_][a-z0-9_]{0,62}$/D', $name, $matches)) {
            throw InvalidConfiguration::because(\sprintf('"%s" is not a table name: up to 63 lowercase letters, digits or "_", not starting with a digit.', $name));
        }

        return $matches[0];
    }

    /**
     * One row per invitation: its campaign and recipient, unique together, its moments, its answer, its reminders, the
     * next one indexed for the dispatcher, its revocation and how many links it has.
     */
    private function invitationTableDefinition(): Table
    {
        return Table::editor()
            ->setUnquotedName($this->invitationTable)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::STRING)->setLength(32)->create(),
                Column::editor()->setUnquotedName('campaign')->setTypeName(Types::STRING)->setLength(100)->create(),
                Column::editor()->setUnquotedName('recipient')->setTypeName(Types::STRING)->setLength(Invitation::MAX_RECIPIENT_LENGTH)->create(),
                Column::editor()->setUnquotedName('created_at')->setTypeName(Types::BIGINT)->create(),
                Column::editor()->setUnquotedName('valid_from')->setTypeName(Types::BIGINT)->create(),
                Column::editor()->setUnquotedName('closes_at')->setTypeName(Types::BIGINT)->create(),
                Column::editor()->setUnquotedName('expires_at')->setTypeName(Types::BIGINT)->create(),
                Column::editor()->setUnquotedName('answer')->setTypeName(Types::TEXT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('responded_at')->setTypeName(Types::BIGINT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('answered_at')->setTypeName(Types::BIGINT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('reminders_sent')->setTypeName(Types::INTEGER)->setDefaultValue(0)->create(),
                Column::editor()->setUnquotedName('next_reminder_at')->setTypeName(Types::BIGINT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('revoked_at')->setTypeName(Types::BIGINT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('links_issued')->setTypeName(Types::INTEGER)->setDefaultValue(1)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName($this->invitationTable.'_recipient')->setUnquotedColumnNames('campaign', 'recipient')->setType(IndexType::UNIQUE)->create(),
                Index::editor()->setUnquotedName($this->invitationTable.'_next_reminder')->setUnquotedColumnNames('next_reminder_at')->create(),
            )
            ->create();
    }

    /**
     * One row per link: the keyed hash of its token, and its invitation.
     */
    private function linkTableDefinition(): Table
    {
        return Table::editor()
            ->setUnquotedName($this->linkTable)
            ->setColumns(
                Column::editor()->setUnquotedName('hash')->setTypeName(Types::STRING)->setLength(TokenHasher::LENGTH)->create(),
                Column::editor()->setUnquotedName('invitation_id')->setTypeName(Types::STRING)->setLength(32)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('hash')->create())
            ->setIndexes(Index::editor()->setUnquotedName($this->linkTable.'_invitation_id')->setUnquotedColumnNames('invitation_id')->create())
            ->create();
    }
}
