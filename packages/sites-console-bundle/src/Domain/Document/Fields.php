<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Document;

use Phil\SitesConsoleBundle\Domain\Exception\MalformedDocument;

/**
 * The members of a decoded document, read by type, each refusal naming the member.
 */
final readonly class Fields
{
    /**
     * The members of an object; a member named by digits, such as "5", has an integer key, as PHP gives it.
     *
     * @return array<array-key, mixed>
     */
    public static function object(mixed $value, string $name): array
    {
        if (!\is_array($value) || ([] !== $value && array_is_list($value))) {
            throw MalformedDocument::because(\sprintf('"%s" is an object.', $name));
        }

        return $value;
    }

    /**
     * @return list<mixed>
     */
    public static function list(mixed $value, string $name, int $max): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw MalformedDocument::because(\sprintf('"%s" is a list.', $name));
        }

        if (\count($value) > $max) {
            throw MalformedDocument::because(\sprintf('"%s" holds at most %d items.', $name, $max));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $object
     */
    public static function string(array $object, string $name, int $maxLength): string
    {
        $value = $object[$name] ?? null;

        if (!\is_string($value) || '' === $value || mb_strlen($value) > $maxLength || 1 === preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw MalformedDocument::because(\sprintf('"%s" is a text of 1 to %d characters without control characters.', $name, $maxLength));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $object
     */
    public static function optionalString(array $object, string $name, int $maxLength): string
    {
        return null === ($object[$name] ?? null) || '' === $object[$name] ? '' : self::string($object, $name, $maxLength);
    }
}
