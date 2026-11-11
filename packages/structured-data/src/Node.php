<?php

declare(strict_types=1);

namespace Phil\StructuredData;

use BackedEnum;
use DateTimeInterface;
use Phil\StructuredData\Exception\InvalidStructuredData;

/**
 * A node of a schema.org graph: its types, its `@id` when it has one, and its properties. A node never changes:
 * each `with…()` gives a new one.
 *
 * A property holds a text, a number, a boolean, a `LanguageString`, a `Reference`, another node, or a list of them.
 * A date is kept as ISO 8601 text and a backed enum as its value; `null` and an empty list leave the property out. A
 * property name or a type is a schema.org term (`name`, `LocalBusiness`) or an IRI, and starts with a letter: a name
 * never starts with `@`, the keywords of JSON-LD being the node's own, nor with a digit.
 *
 * Nodes nest 32 levels deep at most, so that a node read from a hostile page cannot exhaust the stack.
 *
 * @phpstan-type Value scalar|LanguageString|Reference|Node|list<scalar|LanguageString|Reference|Node>
 */
final readonly class Node
{
    public const int MAX_DEPTH = 32;

    private const string TERM = '/^[\p{L}_][^\x00-\x20"<>\\\\{}|^`\x7f]{0,255}$/Du';

    /**
     * How deep the nodes nest under this one, itself counted.
     */
    public int $depth;

    /**
     * Whether a node with an `@id` nests under this one: the graph lifts it to its top.
     */
    public bool $nestsIdentified;

    /**
     * @param list<string>         $types
     * @param array<string, Value> $properties
     */
    private function __construct(
        public array $types,
        public ?string $id,
        private array $properties,
    ) {
        $depth = 1;
        $nestsIdentified = false;

        foreach ($properties as $value) {
            foreach (\is_array($value) ? $value : [$value] as $item) {
                if ($item instanceof self) {
                    $depth = max($depth, $item->depth + 1);
                    $nestsIdentified = $nestsIdentified || null !== $item->id || $item->nestsIdentified;
                }
            }
        }

        if ($depth > self::MAX_DEPTH) {
            throw InvalidStructuredData::because(\sprintf('Nodes nest %d levels deep at most.', self::MAX_DEPTH));
        }

        $this->depth = $depth;
        $this->nestsIdentified = $nestsIdentified;
    }

    /**
     * A node of the given type or types, with its properties: a `null` property is left out.
     *
     * @param string|list<string>  $type
     * @param array<string, mixed> $properties
     *
     * @throws InvalidStructuredData when a type, a property name, a value or the identifier is refused
     */
    public static function of(string|array $type, array $properties = [], ?string $id = null): self
    {
        $types = self::types(\is_string($type) ? [$type] : $type);

        if ([] === $types) {
            throw InvalidStructuredData::because('A node built with of() has at least one type.');
        }

        return new self($types, null === $id ? null : Id::check($id), self::checkProperties($properties));
    }

    /**
     * A node read from schema.org data, as JSON-LD decodes it or as a package gives it: `@var` and `@id` become the
     * node's, `@context` is dropped, a map with a `@var` or other properties becomes a nested node, a map holding only
     * an `@id` a reference, a map with `@value` and `@language` a `LanguageString`.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidStructuredData when the data is no node, or nests too deep
     */
    public static function fromArray(array $data): self
    {
        return self::read($data);
    }

    /**
     * @return array<string, Value>
     */
    public function properties(): array
    {
        return $this->properties;
    }

    public function get(string $property): mixed
    {
        return $this->properties[$property] ?? null;
    }

    public function has(string $property): bool
    {
        return isset($this->properties[$property]);
    }

    public function isA(string $type): bool
    {
        return \in_array($type, $this->types, true);
    }

    /**
     * The node with the property set to the value; `null` or an empty list removes it.
     *
     * @throws InvalidStructuredData
     */
    public function with(string $property, mixed $value): self
    {
        return $this->withProperties([$property => $value]);
    }

    /**
     * The node with each property set, or removed when its value is `null` or an empty list.
     *
     * @param array<string, mixed> $properties
     *
     * @throws InvalidStructuredData
     */
    public function withProperties(array $properties): self
    {
        $kept = $this->properties;

        foreach ($properties as $name => $value) {
            unset($kept[$name]);
        }

        return new self($this->types, $this->id, [...$kept, ...self::checkProperties($properties)]);
    }

    public function without(string $property): self
    {
        $properties = $this->properties;
        unset($properties[$property]);

        return new self($this->types, $this->id, $properties);
    }

    /**
     * @throws InvalidStructuredData
     */
    public function withId(?string $id): self
    {
        return new self($this->types, null === $id ? null : Id::check($id), $this->properties);
    }

    /**
     * The node with the types added after its own, each once.
     *
     * @throws InvalidStructuredData
     */
    public function withType(string ...$types): self
    {
        return new self(self::types([...$this->types, ...$types]), $this->id, $this->properties);
    }

    /**
     * A reference to this node, which has an `@id`.
     *
     * @throws InvalidStructuredData when the node has no `@id`
     */
    public function reference(): Reference
    {
        return null === $this->id ? throw InvalidStructuredData::because('Only a node with an @id can be referred to.') : new Reference($this->id);
    }

    /**
     * This node and another one that describes the same thing: the types of both, each once; the properties of both,
     * the other's value replacing this one's where both have one. The other's `@id` is kept when this node has none.
     */
    public function mergedWith(self $other): self
    {
        return new self(self::types([...$this->types, ...$other->types]), $this->id ?? $other->id, [...$this->properties, ...$other->properties]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = match (\count($this->types)) {
            0 => [],
            1 => ['@type' => $this->types[0]],
            default => ['@type' => $this->types],
        };

        if (null !== $this->id) {
            $data['@id'] = $this->id;
        }

        foreach ($this->properties as $name => $value) {
            $data[$name] = \is_array($value) ? array_map($this->export(...), $value) : $this->export($value);
        }

        return $data;
    }

    private function export(mixed $value): mixed
    {
        return $value instanceof self || $value instanceof Reference || $value instanceof LanguageString ? $value->toArray() : $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function read(array $data): self
    {
        $type = $data['@type'] ?? [];
        $id = $data['@id'] ?? null;

        if (!\is_string($type) && (!\is_array($type) || !array_is_list($type)) || (null !== $id && !\is_string($id))) {
            throw InvalidStructuredData::because('The @type of a node is a text or a list of texts, and its @id a text.');
        }

        unset($data['@type'], $data['@id'], $data['@context']);

        return new self(self::types(\is_string($type) ? [$type] : $type), null === $id ? null : Id::check($id), self::checkProperties($data));
    }

    /**
     * @param array<mixed> $types
     *
     * @return list<string>
     */
    private static function types(array $types): array
    {
        $checked = [];

        foreach ($types as $type) {
            if (!\is_string($type) || 1 !== preg_match(self::TERM, $type)) {
                throw InvalidStructuredData::because(\sprintf('A type is a schema.org term or an IRI; %s is not.', \is_string($type) ? '"'.$type.'"' : get_debug_type($type)));
            }

            $checked[$type] = $type;
        }

        return array_values($checked);
    }

    /**
     * @param array<array-key, mixed> $properties
     *
     * @return array<string, Value>
     */
    private static function checkProperties(array $properties): array
    {
        $checked = [];

        foreach ($properties as $name => $value) {
            $name = (string) $name;

            if (1 !== preg_match(self::TERM, $name)) {
                throw InvalidStructuredData::because(\sprintf('A property is named by a schema.org term or an IRI, never by a JSON-LD keyword; "%s" is not.', $name));
            }

            $value = self::value($value);

            if (null !== $value && [] !== $value) {
                $checked[$name] = $value;
            }
        }

        return $checked;
    }

    /**
     * @return Value|null
     */
    private static function value(mixed $value): mixed
    {
        return match (true) {
            null === $value, \is_string($value), \is_int($value), \is_bool($value) => $value,
            \is_float($value) => is_finite($value) ? $value : throw InvalidStructuredData::because('A number of a node is finite.'),
            $value instanceof self, $value instanceof Reference, $value instanceof LanguageString => $value,
            $value instanceof DateTimeInterface => $value->format(\DATE_ATOM),
            $value instanceof BackedEnum => $value->value,
            \is_array($value) && array_is_list($value) => self::list($value),
            \is_array($value) => self::map($value),
            default => throw InvalidStructuredData::because(\sprintf('A property holds a text, a number, a boolean, a date, a backed enum, a language string, a reference, a node or a list of them; %s is not.', get_debug_type($value))),
        };
    }

    /**
     * @param list<mixed> $values
     *
     * @return list<scalar|LanguageString|Reference|Node>
     */
    private static function list(array $values): array
    {
        $list = [];

        foreach ($values as $value) {
            $value = self::value($value);

            if (\is_array($value)) {
                throw InvalidStructuredData::because('A list of a property holds values, not other lists.');
            }

            if (null !== $value) {
                $list[] = $value;
            }
        }

        return $list;
    }

    /**
     * @param array<array-key, mixed> $map
     */
    private static function map(array $map): string|LanguageString|Reference|self
    {
        if (\array_key_exists('@value', $map)) {
            $value = $map['@value'];
            $language = $map['@language'] ?? null;

            if (!\is_string($value) || (null !== $language && !\is_string($language)) || [] !== array_diff(array_keys($map), ['@value', '@language', '@type'])) {
                throw InvalidStructuredData::because('A value object holds a text in @value, maybe its @language, and nothing else.');
            }

            return null === $language ? $value : new LanguageString($value, $language);
        }

        if (['@id'] === array_keys($map) && \is_string($map['@id'])) {
            return new Reference($map['@id']);
        }

        return self::read($map);
    }
}
