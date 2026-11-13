<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Domain\ValueObject;

use Phil\StatusPageBundle\Domain\Exception\InvalidStatusRecord;

/**
 * The checks every text of a record goes through: identifiers, one-line names, and longer messages.
 *
 * @internal
 */
final readonly class Text
{
    /**
     * A lower-case identifier of 1 to 64 letters, digits, "-" or "_", starting with a letter or a digit: it becomes the
     * fragment of an address and the key of a JSON object.
     *
     * @return non-empty-string
     */
    public static function id(string $value, string $field): string
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $value)) {
            throw InvalidStatusRecord::because(\sprintf('The %s is 1 to 64 lower-case letters, digits, "-" or "_", starting with a letter or a digit.', $field));
        }

        return $value;
    }

    /**
     * One line of 1 to `$length` characters, its spaces collapsed.
     *
     * @return non-empty-string
     */
    public static function line(string $value, string $field, int $length = 160): string
    {
        // Text that is not UTF-8 makes preg_replace() return null, which is the empty line refused below.
        $line = trim((string) preg_replace('/\s+/u', ' ', $value));

        if ('' === $line || mb_strlen($line) > $length || 1 === preg_match('/[\x00-\x1F\x7F]/', $line)) {
            throw InvalidStatusRecord::because(\sprintf('The %s is one line of 1 to %d characters of UTF-8, without control characters.', $field, $length));
        }

        return $line;
    }

    /**
     * A message of 1 to `$length` characters, its lines kept, trimmed.
     *
     * @return non-empty-string
     */
    public static function message(string $value, string $field, int $length = 4000): string
    {
        $message = trim(str_replace(["\r\n", "\r"], "\n", $value));

        if ('' === $message || mb_strlen($message) > $length || 1 !== preg_match('//u', $message) || 1 === preg_match('/[\x00-\x08\x0B-\x1F\x7F]/', $message)) {
            throw InvalidStatusRecord::because(\sprintf('The %s is 1 to %d characters of UTF-8, without control characters.', $field, $length));
        }

        return $message;
    }

    /**
     * Identifiers, each once, in the order given.
     *
     * @param list<string> $values
     *
     * @return list<non-empty-string>
     */
    public static function ids(array $values, string $field): array
    {
        $ids = [];

        foreach ($values as $value) {
            $id = self::id($value, $field);

            if (\in_array($id, $ids, true)) {
                throw InvalidStatusRecord::because(\sprintf('Each %s is given once.', $field));
            }

            $ids[] = $id;
        }

        return $ids;
    }
}
