<?php

declare(strict_types=1);

namespace Phil\Ujo\Type;

use Phil\Ujo\Exception\UnknownBlockType;

/**
 * The block types a document may hold, found by name or alias. Registering a type under a name already taken replaces
 * the earlier one. A registry is a value; the class is not `readonly` only so that it can keep the default registry.
 */
final class BlockTypeRegistry
{
    private static ?self $default = null;

    /** @var array<string, BlockType> */
    private readonly array $types;

    /** @var array<string, string> every name and alias, to its type's name */
    private readonly array $names;

    public function __construct(BlockType ...$types)
    {
        $byName = [];
        $names = [];

        foreach ($types as $type) {
            $byName[$type->name] = $type;
        }

        foreach ($byName as $type) {
            foreach ([$type->name, ...$type->aliases] as $name) {
                $names[$name] = $type->name;
            }
        }

        $this->types = $byName;
        $this->names = $names;
    }

    /**
     * Every official Editor.js tool, the community tools read and written, the video and sound players, and the page
     * and game types, without `raw`:
     * a raw HTML block is accepted only by a registry that adds `StandardTypes::raw()`. A registry is a value: the
     * default one is built once and shared.
     */
    public static function default(): self
    {
        return self::$default ??= new self(...StandardTypes::all(), ...CommunityTypes::all(), ...MediaTypes::all(), ...LayoutTypes::all());
    }

    public function with(BlockType ...$types): self
    {
        return new self(...array_values($this->types), ...$types);
    }

    public function without(string ...$names): self
    {
        $kept = [];

        foreach ($this->types as $type) {
            if (!\in_array($type->name, $names, true)) {
                $kept[] = $type;
            }
        }

        return new self(...$kept);
    }

    public function has(string $name): bool
    {
        return isset($this->names[$name]);
    }

    /**
     * The type of a name or alias.
     *
     * @throws UnknownBlockType
     */
    public function get(string $name, string $path = 'block'): BlockType
    {
        return $this->types[$this->names[$name] ?? ''] ?? throw UnknownBlockType::block($name, $path);
    }

    /**
     * @return list<string> the canonical names, in the order registered
     */
    public function names(): array
    {
        return array_keys($this->types);
    }
}
