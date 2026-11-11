<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Wiki;

/**
 * The wiki a text is written in, and the delimiters of its extension tags: `<note>` in MediaWiki and DokuWiki, whose
 * extensions are tags, and `<<note>>` in Creole, whose extensions are placeholders.
 *
 * @internal
 */
enum Dialect
{
    case MediaWiki;
    case DokuWiki;
    case Creole;

    public function opener(): string
    {
        return self::Creole === $this ? '<<' : '<';
    }

    public function closer(): string
    {
        return self::Creole === $this ? '>>' : '>';
    }

    /**
     * The closing tag of a name, as written.
     */
    public function closing(string $name): string
    {
        return $this->opener().'/'.$name.$this->closer();
    }
}
