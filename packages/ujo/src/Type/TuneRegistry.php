<?php

declare(strict_types=1);

namespace Phil\Ujo\Type;

use Phil\Ujo\Exception\UnknownBlockType;

/**
 * The tunes a block may carry, found by name or alias. Registering a tune under a name already taken replaces the
 * earlier one. A registry is a value; the class is not `readonly` only so that it can keep the default registry.
 */
final class TuneRegistry
{
    private static ?self $default = null;

    /** @var list<TuneType> */
    private readonly array $list;

    /** @var array<string, TuneType> every name and alias, to its tune */
    private readonly array $tunes;

    public function __construct(TuneType ...$tunes)
    {
        $list = [];
        $byName = [];

        foreach ($tunes as $tune) {
            $list[] = $tune;

            foreach ([$tune->name, ...$tune->aliases] as $name) {
                $byName[$name] = $tune;
            }
        }

        $this->list = $list;
        $this->tunes = $byName;
    }

    /**
     * Every tune the library reads, built once and shared.
     */
    public static function default(): self
    {
        return self::$default ??= new self(...Tunes::all());
    }

    public function with(TuneType ...$tunes): self
    {
        return new self(...$this->list, ...$tunes);
    }

    public function has(string $name): bool
    {
        return isset($this->tunes[$name]);
    }

    /**
     * @throws UnknownBlockType
     */
    public function get(string $name, string $path = 'tunes'): TuneType
    {
        return $this->tunes[$name] ?? throw UnknownBlockType::tune($name, $path);
    }
}
