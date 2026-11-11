<?php

declare(strict_types=1);

namespace Phil\Slug;

/**
 * The named sluggers of an application, and the one used when no name is given.
 */
final readonly class Sluggers
{
    /**
     * @param array<string, Slugger> $sluggers
     *
     * @throws UnknownSlugger when no slugger has the default name
     */
    public function __construct(
        private array $sluggers,
        private string $default,
    ) {
        if (!isset($sluggers[$default])) {
            throw UnknownSlugger::named($default, $this->names());
        }
    }

    /**
     * The slugger of that name, or the default one.
     *
     * @throws UnknownSlugger when no slugger has that name
     */
    public function get(?string $name = null): Slugger
    {
        $name ??= $this->default;

        return $this->sluggers[$name] ?? throw UnknownSlugger::named($name, $this->names());
    }

    /**
     * The slug of a text, made by the slugger of that name or the default one.
     *
     * @throws InvalidSlug    when the text holds nothing to make a slug of
     * @throws UnknownSlugger when no slugger has that name
     */
    public function slug(string $text, ?string $name = null): Slug
    {
        return $this->get($name)->slug($text);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->sluggers);
    }
}
