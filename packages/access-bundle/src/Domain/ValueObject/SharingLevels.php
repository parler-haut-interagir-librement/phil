<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Domain\ValueObject;

use Phil\AccessBundle\Domain\Exception\InvalidAccess;

/**
 * The sharing levels of the site, the widest first: each level includes those after it, so that a manager edits,
 * comments and views. The last level is the one an item's visibility opens.
 */
final readonly class SharingLevels
{
    public const array DEFAULT = ['manage', 'edit', 'comment', 'view'];

    /** The relations an item already has, which no level may take. */
    public const array RESERVED = ['owner', 'space', 'visibility'];

    /**
     * @param non-empty-list<string> $names
     */
    private function __construct(
        public array $names,
    ) {
    }

    /**
     * @param list<string> $names
     */
    public static function of(array $names): self
    {
        if ([] === $names || \count($names) > 16) {
            throw InvalidAccess::because('A site has 1 to 16 sharing levels.');
        }

        foreach ($names as $name) {
            if (1 !== preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $name) || \in_array($name, self::RESERVED, true)) {
                throw InvalidAccess::because(\sprintf('A sharing level is 1 to 32 lowercase letters, digits or "_", starting with a letter, and none of %s; "%s" is not.', implode(', ', self::RESERVED), $name));
            }
        }

        if (\count(array_unique($names)) !== \count($names)) {
            throw InvalidAccess::because('A sharing level is named once.');
        }

        return new self($names);
    }

    public function has(string $name): bool
    {
        return \in_array($name, $this->names, true);
    }

    public function assert(string $name): string
    {
        if (!$this->has($name)) {
            throw InvalidAccess::because(\sprintf('"%s" is not a sharing level of the site; they are %s.', $name, implode(', ', $this->names)));
        }

        return $name;
    }

    /**
     * The level an item's visibility opens: the narrowest.
     */
    public function narrowest(): string
    {
        return $this->names[\count($this->names) - 1];
    }

    /**
     * The level just wider than `$name`, or null for the widest.
     */
    public function wider(string $name): ?string
    {
        $index = array_search($this->assert($name), $this->names, true);

        return 0 === $index ? null : $this->names[(int) $index - 1];
    }
}
