<?php

declare(strict_types=1);

namespace Phil\Contact\JSContact;

use Phil\Contact\Exception\UnreadableJSContact;
use Phil\Contact\JSContactNotice;
use stdClass;

/**
 * One reading of a JSContact card: the strict, bounded access to its JSON values, and the notices of what is not kept.
 * A reading is made for one card and dropped with it.
 *
 * @internal
 */
final class Reading
{
    private const string ID = '/^[A-Za-z0-9_-]{1,255}$/';

    /**
     * @var list<JSContactNotice>
     */
    public private(set) array $notices = [];

    public function __construct(
        private readonly int $maxEntries,
    ) {
    }

    public function notice(string $pointer, string $reason): void
    {
        $this->notices[] = new JSContactNotice($pointer, $reason);
    }

    /**
     * An object as an array, of the given `@var` when it has one.
     *
     * @return array<string, mixed>
     */
    public function object(mixed $value, string $pointer, ?string $type = null): array
    {
        if (!$value instanceof stdClass) {
            throw UnreadableJSContact::because(('' === $pointer ? 'the card' : $pointer).' is not an object');
        }

        /** @var array<string, mixed> $object a numeric key is an integer, which the callers turn into a text */
        $object = get_object_vars($value);

        if (null !== $type && $type !== ($object['@type'] ?? $type)) {
            throw UnreadableJSContact::because(\sprintf('%s is not of @type %s', $pointer, $type));
        }

        return $object;
    }

    /**
     * A map of entries keyed by their `Id`, each an object of the given `@var` when it has one; none when the property
     * is missing.
     *
     * @param array<string, mixed> $card
     *
     * @return array<string, array<string, mixed>>
     */
    public function map(array $card, string $property, string $type): array
    {
        if (!\array_key_exists($property, $card)) {
            return [];
        }

        $map = $this->object($card[$property], '/'.$property);
        $this->bounded($map, '/'.$property);
        $entries = [];

        // A numeric key is an integer in a PHP array.
        foreach (array_map(strval(...), array_keys($map)) as $id) {
            if (1 !== preg_match(self::ID, $id)) {
                throw UnreadableJSContact::because(\sprintf('/%s holds a key that is no Id', $property));
            }

            $entries[$id] = $this->object($map[$id], '/'.$property.'/'.$id, $type);
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $object
     *
     * @return list<mixed>
     */
    public function list(array $object, string $key, string $pointer): array
    {
        $list = $object[$key] ?? [];

        if (!\is_array($list) || !array_is_list($list)) {
            throw UnreadableJSContact::because(\sprintf('%s/%s is not a list', $pointer, $key));
        }

        $this->bounded($list, $pointer.'/'.$key);

        return $list;
    }

    /**
     * @param array<string, mixed> $object
     */
    public function string(array $object, string $key, string $pointer): ?string
    {
        $value = $object[$key] ?? null;

        if (null !== $value && !\is_string($value)) {
            throw UnreadableJSContact::because(\sprintf('%s/%s is not a text', $pointer, $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $object
     */
    public function required(array $object, string $key, string $pointer): string
    {
        return $this->string($object, $key, $pointer) ?? throw UnreadableJSContact::because(\sprintf('%s has no %s', $pointer, $key));
    }

    /**
     * A notice for each property of an object outside the known ones.
     *
     * @param array<string, mixed> $object
     * @param list<string>         $known
     */
    public function unknown(array $object, array $known, string $pointer): void
    {
        foreach (array_diff(array_map(strval(...), array_keys($object)), $known) as $property) {
            $this->notice($pointer.'/'.self::escape($property), 'the card does not keep this property');
        }
    }

    /**
     * A key as a JSON pointer writes it (RFC 6901).
     */
    public static function escape(string $key): string
    {
        return strtr($key, ['~' => '~0', '/' => '~1']);
    }

    /**
     * @param array<array-key, mixed> $entries
     */
    private function bounded(array $entries, string $pointer): void
    {
        if (\count($entries) > $this->maxEntries) {
            throw UnreadableJSContact::because(\sprintf('%s holds more than %d entries', $pointer, $this->maxEntries));
        }
    }
}
