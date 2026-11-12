<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Infrastructure\Doctrine;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Override;
use Phil\FormsAutosave\Application\Contract\DraftStore;
use Phil\FormsAutosave\Domain\Exception\InvalidDraft;
use Phil\FormsAutosave\Domain\Model\Draft;
use Phil\FormsAutosave\Domain\ValueObject\DraftKey;

/**
 * The drafts in the table `phil_form_draft`, by plain queries on a Doctrine connection: an insertion that meets the
 * primary key of a draft saved meanwhile fails, a replacement is an `UPDATE` on the revision read, so that a race is
 * decided by the database and the entity manager stays open. Times are kept in UTC.
 */
final readonly class DoctrineDraftStore implements DraftStore
{
    private const string FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @throws InvalidDraft when the row is not that of a draft
     */
    #[Override]
    public function find(string $owner, DraftKey $key): ?Draft
    {
        $row = $this->connection->fetchAssociative(
            'SELECT revision, payload, saved_at, expires_at FROM '.Draft::TABLE.' WHERE owner = ? AND draft_key = ?',
            [$owner, $key->value],
        );

        if (false === $row) {
            return null;
        }

        if (!is_numeric($row['revision'])) {
            throw InvalidDraft::because('The revision of a stored draft is a number.');
        }

        return Draft::restore($owner, $key, (int) $row['revision'], $this->text($row, 'payload'), $this->time($row, 'saved_at'), $this->time($row, 'expires_at'));
    }

    #[Override]
    public function insert(Draft $draft): bool
    {
        try {
            $this->connection->insert(Draft::TABLE, [
                'owner' => $draft->owner(),
                'draft_key' => $draft->key()->value,
                ...$this->columns($draft),
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    #[Override]
    public function replace(Draft $read, Draft $next): bool
    {
        return 1 === (int) $this->connection->update(Draft::TABLE, $this->columns($next), [
            'owner' => $read->owner(),
            'draft_key' => $read->key()->value,
            'revision' => $read->revision(),
        ]);
    }

    #[Override]
    public function delete(string $owner, DraftKey $key): void
    {
        $this->connection->delete(Draft::TABLE, ['owner' => $owner, 'draft_key' => $key->value]);
    }

    #[Override]
    public function purge(DateTimeImmutable $now): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE FROM '.Draft::TABLE.' WHERE expires_at <= ?',
            [$this->format($now)],
        );
    }

    /**
     * @return array{revision: int, payload: string, saved_at: string, expires_at: string}
     */
    private function columns(Draft $draft): array
    {
        return [
            'revision' => $draft->revision(),
            'payload' => $draft->payload(),
            'saved_at' => $this->format($draft->savedAt()),
            'expires_at' => $this->format($draft->expiresAt()),
        ];
    }

    private function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::FORMAT);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws InvalidDraft when the column is not a text
     */
    private function text(array $row, string $column): string
    {
        $value = $row[$column] ?? null;

        return \is_string($value) ? $value : throw InvalidDraft::because(\sprintf('The column "%s" of a stored draft is a text.', $column));
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws InvalidDraft when the column is not a time
     */
    private function time(array $row, string $column): DateTimeImmutable
    {
        $time = DateTimeImmutable::createFromFormat(self::FORMAT, $this->text($row, $column), new DateTimeZone('UTC'));

        return false === $time ? throw InvalidDraft::because(\sprintf('The column "%s" of a stored draft is a time.', $column)) : $time;
    }
}
