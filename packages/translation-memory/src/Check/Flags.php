<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Check;

use Phil\TranslationMemory\Exception\InvalidConfiguration;

/**
 * The flags of a segment, which tune its checks: `ignore-<check>` dismisses a check — `ignore-same` for a name kept
 * as it is —, and a flag with a value sets one — `max-size:240`, the widest a target may be drawn, in pixels.
 *
 * Written as text, flags are separated by commas, white space around them left out: `ignore-tags, max-size:240`. A
 * name is a lower case letter and up to 31 more lower case letters, digits and hyphens; a value, 1 to 32 letters,
 * digits, dots, underscores and hyphens; a segment holds at most 32 flags. The same name given twice keeps the last
 * value.
 */
final readonly class Flags
{
    public const int MAX_FLAGS = 32;

    private const string FLAG = '/^([a-z][a-z0-9-]{0,31})(?::([A-Za-z0-9._-]{1,32}))?$/D';

    /**
     * @var array<string, ?string> the value of each flag by its name, `null` for a flag without value
     */
    private array $flags;

    /**
     * @param iterable<string> $flags
     *
     * @throws InvalidConfiguration when a flag is not a name with an optional value, or there are more than 32
     */
    public function __construct(iterable $flags = [])
    {
        $parsed = [];

        foreach ($flags as $flag) {
            if (1 !== preg_match(self::FLAG, $flag, $match)) {
                throw InvalidConfiguration::because(\sprintf('"%s" is not a flag such as "ignore-tags" or "max-size:240".', $flag));
            }

            unset($parsed[$match[1]]);
            $parsed[$match[1]] = $match[2] ?? null;
        }

        if (\count($parsed) > self::MAX_FLAGS) {
            throw InvalidConfiguration::because(\sprintf('A segment holds at most %d flags.', self::MAX_FLAGS));
        }

        $this->flags = $parsed;
    }

    /**
     * The flags of a text, separated by commas.
     *
     * @throws InvalidConfiguration
     */
    public static function parse(string $flags): self
    {
        return new self(array_filter(array_map(trim(...), explode(',', $flags)), static fn (string $flag): bool => '' !== $flag));
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->flags);
    }

    /**
     * The value of a flag; `null` when it is missing or has none.
     */
    public function value(string $name): ?string
    {
        return $this->flags[$name] ?? null;
    }

    /**
     * Whether the flags dismiss a check: `ignore-<check>`.
     */
    public function dismisses(string $check): bool
    {
        return $this->has('ignore-'.$check);
    }

    /**
     * The flags with one more, or another value of one.
     *
     * @throws InvalidConfiguration
     */
    public function with(string $flag): self
    {
        return new self([...$this->all(), $flag]);
    }

    /**
     * The flags without one, by its name.
     */
    public function without(string $name): self
    {
        return new self(array_filter($this->all(), static fn (string $flag): bool => explode(':', $flag)[0] !== $name));
    }

    /**
     * Each flag as written, in order.
     *
     * @return list<string>
     */
    public function all(): array
    {
        $all = [];

        foreach ($this->flags as $name => $value) {
            $all[] = null === $value ? $name : $name.':'.$value;
        }

        return $all;
    }

    /**
     * The flags as text, separated by a comma and a space.
     */
    public function toString(): string
    {
        return implode(', ', $this->all());
    }
}
