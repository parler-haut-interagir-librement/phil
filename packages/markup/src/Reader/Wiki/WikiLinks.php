<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Wiki;

use Phil\Markup\Lexer\Slugger;

/**
 * Where the pages and the media a wiki text names are found: a link to a page, `[[Main Page#History]]`, goes to
 * `{pages}Main_Page#history`, a file, `[[File:map.png]]` or `{{map.png}}`, to `{media}map.png`. A web address is kept
 * as written. The paths start with `/` by default, so that a name holding `:` — a namespace — is never read as a
 * scheme.
 */
final readonly class WikiLinks
{
    /** The beginnings of a web or e-mail address. */
    private const array SCHEMES = ['http://', 'https://', 'www.', 'mailto:'];

    public function __construct(
        public string $pages = '/',
        public string $media = '/',
    ) {
    }

    /**
     * The address of a page, its section an anchor as the readers make them.
     */
    public function page(string $target): string
    {
        if (self::isAddress($target)) {
            return $this->address($target);
        }

        $hash = strpos($target, '#');
        $page = false === $hash ? $target : substr($target, 0, $hash);
        $section = false === $hash ? '' : '#'.new Slugger()->slug(substr($target, $hash));

        return ('' === trim($page) ? '' : $this->pages.$this->path($page)).$section;
    }

    public function media(string $name): string
    {
        return self::isAddress($name) ? $this->address($name) : $this->media.$this->path($name);
    }

    /**
     * Whether a target is a web address — `http://`, `https://`, `www.` — or an e-mail address, `mailto:`.
     */
    public static function isAddress(string $target): bool
    {
        return self::isAddressAt(ltrim($target), 0);
    }

    /**
     * Whether a web or e-mail address starts at a position of a text, in any case.
     */
    public static function isAddressAt(string $text, int $at): bool
    {
        return array_any(self::SCHEMES, static fn (string $scheme): bool => 0 === substr_compare($text, $scheme, $at, \strlen($scheme), true));
    }

    private function address(string $target): string
    {
        $target = trim($target);

        return str_starts_with(strtolower($target), 'www.') ? 'https://'.$target : $target;
    }

    /**
     * A name as a path: its spaces underscores, every character that is not safe in a path encoded, `/` and `:` kept.
     */
    private function path(string $name): string
    {
        return str_replace(['%2F', '%3A'], ['/', ':'], rawurlencode(str_replace(' ', '_', trim($name))));
    }
}
