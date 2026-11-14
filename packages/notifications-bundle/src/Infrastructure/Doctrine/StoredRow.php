<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use JsonException;
use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;

/**
 * The columns of a stored row, read with their types: a driver hands back text, and sometimes integers, which are
 * checked before they reach the domain.
 */
final readonly class StoredRow
{
    /**
     * @param array<string, mixed> $row
     */
    public function __construct(
        private array $row,
        private string $what,
    ) {
    }

    /**
     * @throws InvalidNotification when the column is not text
     */
    public function string(string $column): string
    {
        $value = $this->row[$column] ?? null;

        if (!\is_string($value)) {
            throw InvalidNotification::because(\sprintf('The column "%s" of a stored %s is a text.', $column, $this->what));
        }

        return $value;
    }

    public function nullableString(string $column): ?string
    {
        return null === ($this->row[$column] ?? null) ? null : $this->string($column);
    }

    /**
     * @throws InvalidNotification when the column is not a whole number
     */
    public function int(string $column): int
    {
        $value = $this->row[$column] ?? null;

        if (\is_string($value) && 1 === preg_match('/^-?\d{1,18}$/D', $value)) {
            return (int) $value;
        }

        if (!\is_int($value)) {
            throw InvalidNotification::because(\sprintf('The column "%s" of a stored %s is a whole number.', $column, $this->what));
        }

        return $value;
    }

    /**
     * An instant of a `datetime_utc_immutable` column, in the connection platform's own format, read in UTC. Unlike
     * the DBAL type, nothing else is guessed: a column of any other shape is refused.
     *
     * @throws InvalidNotification when the column is not a date and time
     */
    public function date(string $column, AbstractPlatform $platform): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat($platform->getDateTimeFormatString(), $this->string($column), new DateTimeZone('UTC'));

        if (false === $date) {
            throw InvalidNotification::because(\sprintf('The column "%s" of a stored %s is a date and time.', $column, $this->what));
        }

        return $date;
    }

    public function nullableDate(string $column, AbstractPlatform $platform): ?DateTimeImmutable
    {
        return null === ($this->row[$column] ?? null) ? null : $this->date($column, $platform);
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws InvalidNotification when the column is not a JSON object
     */
    public function json(string $column): array
    {
        try {
            $decoded = json_decode($this->string($column), true, 3, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $decoded = null;
        }

        if (!\is_array($decoded)) {
            throw InvalidNotification::because(\sprintf('The column "%s" of a stored %s is a JSON object.', $column, $this->what));
        }

        return $decoded;
    }
}
