<?php

declare(strict_types=1);

namespace Phil\MenuBundle\Application;

use Phil\MenuBundle\Domain\InvalidMenu;
use Phil\MenuBundle\Domain\Menu;
use Phil\MenuBundle\Domain\MenuItem;

/**
 * The menus of the application: built, decorated, then their current item and its ancestors marked.
 *
 * An item is current when its route is the visitor's and its parameters are among the visitor's, or when its address
 * is on the site — no host — and its path is the visitor's. An address on another site is never current.
 */
final readonly class Menus
{
    /** @var array<string, MenuBuilder> */
    private array $builders;

    /**
     * @param iterable<MenuBuilder>   $builders
     * @param iterable<MenuDecorator> $decorators in the order they run
     */
    public function __construct(iterable $builders = [], private iterable $decorators = [])
    {
        $byName = [];

        foreach ($builders as $builder) {
            $byName[$builder->name()] = isset($byName[$builder->name()]) ? throw InvalidMenu::because(\sprintf('Two builders make the menu "%s".', $builder->name())) : $builder;
        }

        $this->builders = $byName;
    }

    public function get(string $name, MenuContext $context = new MenuContext()): Menu
    {
        $builder = $this->builders[$name] ?? throw InvalidMenu::because(\sprintf('No builder makes the menu "%s"; the menus are: %s.', $name, implode(', ', $this->names())));
        $menu = $builder->build($context);

        foreach ($this->decorators as $decorator) {
            if ($decorator->supports($name)) {
                $menu = $menu->map(static fn (MenuItem $item): MenuItem => $decorator->decorate($item, $context));
            }
        }

        return $menu->map(static fn (MenuItem $item): MenuItem => match (true) {
            self::isCurrent($item, $context) => $item->markCurrent(),
            array_any($item->children, static fn (MenuItem $child): bool => $child->isActive()) => $item->markCurrentAncestor(),
            default => $item,
        });
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        $names = array_keys($this->builders);
        sort($names);

        return array_map(strval(...), $names);
    }

    /**
     * A parameter of the item and the visitor's, alike as texts: a route's parameters are texts, an item's may be numbers.
     */
    private static function same(mixed $item, mixed $visitor): bool
    {
        return \is_scalar($item) && \is_scalar($visitor) && (string) $item === (string) $visitor;
    }

    private static function isCurrent(MenuItem $item, MenuContext $context): bool
    {
        if (null !== $item->route) {
            return $item->route === $context->route
                && array_all($item->routeParameters, static fn (mixed $value, string $name): bool => self::same($value, $context->routeParameters[$name] ?? null));
        }

        if (null === $item->url || null === $context->path) {
            return false;
        }

        $parts = parse_url($item->url);

        return \is_array($parts) && !isset($parts['host']) && ($parts['path'] ?? null) === $context->path;
    }
}
