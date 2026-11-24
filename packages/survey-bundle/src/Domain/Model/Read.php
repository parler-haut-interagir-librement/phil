<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Domain\Model;

use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;

/**
 * Reads the members of a decoded questionnaire, refusing a missing member, a value of the wrong type or a member it
 * does not know with InvalidQuestionnaire, never a TypeError: the JSON may come from anyone.
 *
 * @internal
 */
final class Read
{
    private function __construct()
    {
    }

    /**
     * @param list<string> $known
     *
     * @return array<string, mixed>
     *
     * @throws InvalidQuestionnaire when the data is a list, or holds a member not known
     */
    public static function map(mixed $data, string $what, array $known): array
    {
        if (!\is_array($data) || ([] !== $data && array_is_list($data))) {
            throw InvalidQuestionnaire::because(\sprintf('%s is an object.', ucfirst($what)));
        }

        foreach (array_keys($data) as $key) {
            if (!\in_array($key, $known, true)) {
                throw InvalidQuestionnaire::because(\sprintf('%s has no member "%s".', ucfirst($what), mb_substr((string) $key, 0, 40)));
            }
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function string(array $data, string $key, string $what): string
    {
        $value = $data[$key] ?? null;

        if (!\is_string($value)) {
            throw InvalidQuestionnaire::because(\sprintf('The "%s" of %s is a text.', $key, $what));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function optionalString(array $data, string $key, string $what): ?string
    {
        return null === ($data[$key] ?? null) ? null : self::string($data, $key, $what);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function optionalInt(array $data, string $key, string $what): ?int
    {
        $value = $data[$key] ?? null;

        if (null !== $value && !\is_int($value)) {
            throw InvalidQuestionnaire::because(\sprintf('The "%s" of %s is a whole number.', $key, $what));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function bool(array $data, string $key, string $what, bool $default): bool
    {
        $value = $data[$key] ?? $default;

        if (!\is_bool($value)) {
            throw InvalidQuestionnaire::because(\sprintf('The "%s" of %s is true or false.', $key, $what));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<mixed>
     */
    public static function list(array $data, string $key, string $what): array
    {
        $value = $data[$key] ?? [];

        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidQuestionnaire::because(\sprintf('The "%s" of %s is a list.', $key, $what));
        }

        return $value;
    }

    /**
     * A condition: one JsonLogic operation, as an object, or none.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>|null
     */
    public static function condition(array $data, string $key, string $what): ?array
    {
        $value = $data[$key] ?? null;

        if (null === $value) {
            return null;
        }

        if (!\is_array($value) || 1 !== \count($value) || array_is_list($value)) {
            throw InvalidQuestionnaire::because(\sprintf('The "%s" of %s is one operation, such as {"==": [{"var": "team"}, "blue"]}.', $key, $what));
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * A name of a section, a question, an ending or a quota: 1 to 64 lower-case letters, digits or "_", from a letter.
     */
    public static function name(string $name, string $what): string
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $name)) {
            throw InvalidQuestionnaire::because(\sprintf('The name of %s is 1 to 64 lower-case letters, digits or "_", starting with a letter; "%s" is not.', $what, mb_substr($name, 0, 80)));
        }

        return $name;
    }

    /**
     * A text shown to respondents: trimmed, 1 to `$max` characters of valid UTF-8, or empty when allowed.
     */
    public static function text(string $text, string $what, int $max, bool $optional = false): string
    {
        $text = trim($text);

        if (!mb_check_encoding($text, 'UTF-8') || mb_strlen($text) > $max || (!$optional && '' === $text)) {
            throw InvalidQuestionnaire::because(\sprintf('%s is %d to %d characters.', ucfirst($what), $optional ? 0 : 1, $max));
        }

        return $text;
    }
}
