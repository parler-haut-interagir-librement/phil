<?php

declare(strict_types=1);

namespace Phil\MenuBundle\Domain;

use Closure;

/**
 * A named menu: its items, and the translation domain of their labels — `null` when the labels are shown as they are.
 */
final readonly class Menu
{
    /**
     * @param list<MenuItem> $items
     */
    private function __construct(
        public string $name,
        public array $items,
        public ?string $translationDomain,
    ) {
    }

    /**
     * @param array<MenuItem> $items in their order
     */
    public static function of(string $name, array $items = [], ?string $translationDomain = null): self
    {
        $keys = array_map(static fn (MenuItem $item): string => $item->key, $items);

        if (\count($keys) !== \count(array_unique($keys))) {
            throw InvalidMenu::because(\sprintf('The items of the menu "%s" have the same key twice.', $name));
        }

        return new self($name, array_values($items), $translationDomain);
    }

    /**
     * An item at any depth, by key.
     */
    public function find(string $key): ?MenuItem
    {
        foreach ($this->items as $item) {
            $found = $item->find($key);

            if ($found instanceof MenuItem) {
                return $found;
            }
        }

        return null;
    }

    /**
     * The items from the top to the current one: what a breadcrumb shows; empty when no item is current.
     *
     * @return list<MenuItem>
     */
    public function breadcrumb(): array
    {
        $trail = [];
        $level = $this->items;

        while (null !== ($item = array_find($level, static fn (MenuItem $candidate): bool => $candidate->isActive()))) {
            $trail[] = $item;
            $level = $item->current ? [] : $item->children;
        }

        return $trail;
    }

    /**
     * The menu with each item changed, children first.
     *
     * @param Closure(MenuItem): MenuItem $change
     */
    public function map(Closure $change): self
    {
        return new self($this->name, array_map(static fn (MenuItem $item): MenuItem => $item->map($change), $this->items), $this->translationDomain);
    }
}
