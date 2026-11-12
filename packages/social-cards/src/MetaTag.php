<?php

declare(strict_types=1);

namespace Phil\SocialCards;

/**
 * One tag of a page's head: a `<meta property>` of Open Graph, a `<meta name>` of X, of the fediverse or of the page's
 * description, or the `<link rel="canonical">` of its address.
 */
final readonly class MetaTag
{
    public function __construct(
        public MetaAttribute $attribute,
        public string $key,
        public string $content,
    ) {
    }

    public static function property(string $key, string $content): self
    {
        return new self(MetaAttribute::Property, $key, $content);
    }

    public static function name(string $key, string $content): self
    {
        return new self(MetaAttribute::Name, $key, $content);
    }

    public static function link(string $rel, string $href): self
    {
        return new self(MetaAttribute::Link, $rel, $href);
    }

    /**
     * The tag as HTML, its values escaped for a double-quoted attribute.
     */
    public function html(): string
    {
        $key = htmlspecialchars($this->key, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');
        $content = htmlspecialchars($this->content, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');

        return match ($this->attribute) {
            MetaAttribute::Link => \sprintf('<link rel="%s" href="%s">', $key, $content),
            default => \sprintf('<meta %s="%s" content="%s">', $this->attribute->value, $key, $content),
        };
    }
}
