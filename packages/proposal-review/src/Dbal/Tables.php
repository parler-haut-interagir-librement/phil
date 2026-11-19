<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Dbal;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Types\Types;
use JsonException;
use Phil\ProposalReview\Exception\InvalidInput;
use UnexpectedValueException;

/**
 * What the four stores share of their tables: a prefix checked before it reaches SQL, identifier columns compared byte
 * for byte, instants written as text in UTC, JSON written and read back, and the values of a row checked as they are
 * read.
 *
 * @internal
 */
final readonly class Tables
{
    public const string PREFIX = 'phil_review_';

    /** Instants as text: sortable, exact to the microsecond, the same on every platform. */
    private const string INSTANT = 'Y-m-d\TH:i:s.u\Z';

    /**
     * @return non-empty-string
     *
     * @throws InvalidInput when the prefix is not up to 40 lowercase letters, digits or "_"
     */
    public static function prefix(string $prefix): string
    {
        if (1 !== preg_match('/^[a-z_][a-z0-9_]{0,39}$/D', $prefix, $matches)) {
            throw InvalidInput::because('A table prefix is up to 40 lowercase letters, digits or "_", not starting with a digit.');
        }

        return $matches[0];
    }

    /**
     * An identifier as a database keeps it: valid UTF-8 that does not end with a space, which MySQL's collations
     * would ignore when they compare.
     *
     * @throws InvalidInput
     */
    public static function identifier(string $id): string
    {
        if (!self::isStorable($id)) {
            throw InvalidInput::because('Identifiers kept in a database are valid UTF-8 and do not end with a space.');
        }

        return $id;
    }

    /**
     * Whether an identifier could have been kept: a read for one that could not finds nothing, instead of the rows that
     * MySQL's collations would match without its last spaces.
     */
    public static function isStorable(string $id): bool
    {
        return 1 === preg_match('//u', $id) && !str_ends_with($id, ' ');
    }

    /**
     * A column of identifiers, 255 characters at most, compared byte for byte.
     *
     * @param non-empty-string $name
     */
    public static function identifierColumn(string $name, AbstractPlatform $platform): Column
    {
        $column = Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength(255);

        if ($platform instanceof AbstractMySQLPlatform) {
            $column->setCharset('utf8mb4')->setCollation('utf8mb4_bin');
        }

        return $column->create();
    }

    /**
     * A column of short ASCII words: a phase, a decision, the kind of a conflict, an instant.
     *
     * @param non-empty-string $name
     */
    public static function wordColumn(string $name, int $length, AbstractPlatform $platform, bool $notNull = true): Column
    {
        $column = Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->setNotNull($notNull);

        if ($platform instanceof AbstractMySQLPlatform) {
            $column->setCharset('ascii')->setCollation('ascii_bin');
        }

        return $column->create();
    }

    /**
     * @param non-empty-string $name
     */
    public static function textColumn(string $name): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::TEXT)->create();
    }

    /**
     * @param non-empty-string $name
     */
    public static function instantColumn(string $name, AbstractPlatform $platform, bool $notNull = true): Column
    {
        return self::wordColumn($name, 27, $platform, $notNull);
    }

    /**
     * @param non-empty-list<non-empty-string> $columns
     */
    public static function primaryKey(array $columns): PrimaryKeyConstraint
    {
        return PrimaryKeyConstraint::editor()->setUnquotedColumnNames(...$columns)->create();
    }

    /**
     * @throws InvalidInput when the instant falls outside the years 1 to 9999
     */
    public static function instant(DateTimeInterface $instant): string
    {
        $text = DateTimeImmutable::createFromInterface($instant)->setTimezone(new DateTimeZone('UTC'))->format(self::INSTANT);

        if (1 !== preg_match('/^\d{4}-/', $text) || str_starts_with($text, '0000')) {
            throw InvalidInput::because('Instants kept in a database fall within the years 1 to 9999.');
        }

        return $text;
    }

    public static function readInstant(mixed $value, string $what): DateTimeImmutable
    {
        $text = self::text($value, $what);
        $instant = DateTimeImmutable::createFromFormat('!'.self::INSTANT, $text, new DateTimeZone('UTC'));

        if (false === $instant || $instant->format(self::INSTANT) !== $text) {
            throw new UnexpectedValueException(\sprintf('The stored %s is not an instant.', $what));
        }

        return $instant;
    }

    /**
     * @param array<mixed> $value
     */
    public static function encode(array $value): string
    {
        return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * @return array<mixed>
     */
    public static function decode(mixed $value, string $what): array
    {
        try {
            $decoded = json_decode(self::text($value, $what), true, 8, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new UnexpectedValueException(\sprintf('The stored %s is not JSON.', $what), 0, $jsonException);
        }

        return self::array($decoded, $what);
    }

    public static function text(mixed $value, string $what): string
    {
        return \is_string($value) ? $value : throw new UnexpectedValueException(\sprintf('The stored %s is not text.', $what));
    }

    public static function integer(mixed $value, string $what): int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && 1 === preg_match('/^-?\d{1,18}$/D', $value)) {
            return (int) $value;
        }

        throw new UnexpectedValueException(\sprintf('The stored %s is not a whole number.', $what));
    }

    public static function boolean(mixed $value, string $what): bool
    {
        return \is_bool($value) ? $value : throw new UnexpectedValueException(\sprintf('The stored %s is not true or false.', $what));
    }

    /**
     * @return array<mixed>
     */
    public static function array(mixed $value, string $what): array
    {
        return \is_array($value) ? $value : throw new UnexpectedValueException(\sprintf('The stored %s is not a list or an object.', $what));
    }

    /**
     * The values of a list, each checked by the reader given.
     *
     * @template T
     *
     * @param callable(mixed): T $read
     *
     * @return list<T>
     */
    public static function each(mixed $value, string $what, callable $read): array
    {
        $values = self::array($value, $what);

        if (!array_is_list($values)) {
            throw new UnexpectedValueException(\sprintf('The stored %s is not a list.', $what));
        }

        return array_map($read, $values);
    }

    /**
     * @template T
     *
     * @param list<T>             $items
     * @param callable(T): string $key
     *
     * @return list<T>
     */
    public static function sortBy(array $items, callable $key): array
    {
        usort($items, static fn (mixed $a, mixed $b): int => strcmp($key($a), $key($b)));

        return $items;
    }
}
