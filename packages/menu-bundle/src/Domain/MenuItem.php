<?php

declare(strict_types=1);

namespace Phil\MenuBundle\Domain;

use Closure;
use DateTimeImmutable;

/**
 * An entry of a menu: its label and where it leads — a route or an address —, what a card shows of it — an icon, a
 * description, how many things it holds and how many are new, when it last changed —, the HTML attributes the
 * application gives it, its children, and whether it is the current page or leads to it.
 *
 * Immutable: each `with…()` gives a new item. Decorators of the application change items this way.
 */
final readonly class MenuItem
{
    /** The schemes an address may have; an address without one is on the site. */
    public const array SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * @param array<string, scalar> $routeParameters
     * @param array<string, string> $attributes
     * @param list<self>            $children
     */
    private function __construct(
        public string $key,
        public string $label,
        public ?string $url = null,
        public ?string $route = null,
        public array $routeParameters = [],
        public ?string $icon = null,
        public ?string $description = null,
        public ?int $count = null,
        public ?int $newCount = null,
        public ?DateTimeImmutable $updatedAt = null,
        public array $attributes = [],
        public array $children = [],
        public bool $current = false,
        public bool $currentAncestor = false,
    ) {
    }

    /**
     * @param string $key   unique among its siblings: letters, digits, `_`, `-` and `.`
     * @param string $label as shown, or the translation key of the menu's domain
     */
    public static function of(string $key, string $label): self
    {
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,99}$/', $key)) {
            throw InvalidMenu::because(\sprintf('"%s" is not a key of a menu item: up to 100 letters, digits, "_", "-" and ".".', mb_substr($key, 0, 100)));
        }

        if ('' === trim($label)) {
            throw InvalidMenu::because(\sprintf('The menu item "%s" has no label.', $key));
        }

        return new self($key, $label);
    }

    public function withLabel(string $label): self
    {
        self::of($this->key, $label);

        return clone ($this, ['label' => $label]);
    }

    /**
     * An address on the site — a path, `/games`, or a reference relative to the page, `#top`, `?page=2` — or on the
     * web, `https:` or `http:`, or one of `mailto:` and `tel:`. Any other scheme, `javascript:` or `data:` among them,
     * is refused, and so are spaces and control characters, which browsers drop from a scheme before reading it.
     */
    public function withUrl(string $url): self
    {
        if ('' === $url || 1 === preg_match('/[\x00-\x20\x7F]/', $url)) {
            throw InvalidMenu::because(\sprintf('The address of the menu item "%s" is empty or holds a space or a control character.', $this->key));
        }

        if (1 === preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):/', $url, $scheme) && !\in_array(strtolower($scheme[1]), self::SCHEMES, true)) {
            throw InvalidMenu::because(\sprintf('The address of the menu item "%s" is on the site, or its scheme is one of %s: "%s" is not.', $this->key, implode(', ', self::SCHEMES), substr($scheme[1], 0, 20)));
        }

        return clone ($this, ['url' => $url, 'route' => null, 'routeParameters' => []]);
    }

    /**
     * @param array<string, scalar> $parameters
     */
    public function withRoute(string $route, array $parameters = []): self
    {
        return clone ($this, ['route' => $route, 'routeParameters' => $parameters, 'url' => null]);
    }

    public function withIcon(?string $icon): self
    {
        return clone ($this, ['icon' => $icon]);
    }

    public function withDescription(?string $description): self
    {
        return clone ($this, ['description' => $description]);
    }

    /**
     * How many things the item leads to, and how many of them are new; `null` for none shown.
     */
    public function withCount(?int $count, ?int $newCount = null): self
    {
        if ((null !== $count && $count < 0) || (null !== $newCount && ($newCount < 0 || $newCount > ($count ?? $newCount)))) {
            throw InvalidMenu::because(\sprintf('The counts of the menu item "%s" are zero or more, and the new ones no more than all.', $this->key));
        }

        return clone ($this, ['count' => $count, 'newCount' => $newCount]);
    }

    public function withUpdatedAt(?DateTimeImmutable $updatedAt): self
    {
        return clone ($this, ['updatedAt' => $updatedAt]);
    }

    /**
     * An HTML attribute of the item's element; `null` takes it away. Its name is a letter, then letters, digits, `_`,
     * `-`, `.` and `:`. A name that starts with `on` is refused: event handlers, `onclick` and the others, all do, and
     * their value would run as a script.
     */
    public function withAttribute(string $name, ?string $value): self
    {
        if (1 !== preg_match('/^[A-Za-z][A-Za-z0-9_.:-]{0,99}$/', $name) || 0 === strncasecmp($name, 'on', 2)) {
            throw InvalidMenu::because(\sprintf('"%s" is not an attribute a menu item may have: a letter, then letters, digits, "_", "-", "." and ":", and no event handler.', mb_substr($name, 0, 100)));
        }

        $attributes = $this->attributes;
        unset($attributes[$name]);

        return clone ($this, ['attributes' => null === $value ? $attributes : [...$attributes, $name => $value]]);
    }

    /**
     * Classes added to the item's, each once.
     */
    public function withClass(string ...$classes): self
    {
        $all = array_filter(explode(' ', implode(' ', [$this->attributes['class'] ?? '', ...$classes])), static fn (string $class): bool => '' !== $class);

        return $this->withAttribute('class', implode(' ', array_unique($all)));
    }

    public function withChildren(self ...$children): self
    {
        $keys = array_map(static fn (self $child): string => $child->key, $children);

        if (\count($keys) !== \count(array_unique($keys))) {
            throw InvalidMenu::because(\sprintf('The children of the menu item "%s" have the same key twice.', $this->key));
        }

        return clone ($this, ['children' => array_values($children)]);
    }

    public function withChild(self $child): self
    {
        return $this->withChildren(...$this->children, ...[$child]);
    }

    public function markCurrent(): self
    {
        return clone ($this, ['current' => true]);
    }

    public function markCurrentAncestor(): self
    {
        return clone ($this, ['currentAncestor' => true]);
    }

    public function isActive(): bool
    {
        return $this->current || $this->currentAncestor;
    }

    /**
     * The item, or one of its descendants, by key.
     */
    public function find(string $key): ?self
    {
        if ($key === $this->key) {
            return $this;
        }

        foreach ($this->children as $child) {
            $found = $child->find($key);

            if ($found instanceof self) {
                return $found;
            }
        }

        return null;
    }

    /**
     * The item with its children changed first, then itself. An item whose children come back the same is passed on
     * as it is.
     *
     * @param Closure(self): self $change
     */
    public function map(Closure $change): self
    {
        $children = [];
        $changed = false;
        $renamed = false;

        foreach ($this->children as $child) {
            $mapped = $child->map($change);
            $children[] = $mapped;
            $changed = $changed || $mapped !== $child;
            $renamed = $renamed || $mapped->key !== $child->key;
        }

        // Children whose keys did not change are still unique: only a renamed one is checked again.
        $item = match (true) {
            $renamed => $this->withChildren(...$children),
            $changed => clone ($this, ['children' => $children]),
            default => $this,
        };

        return $change($item);
    }
}
