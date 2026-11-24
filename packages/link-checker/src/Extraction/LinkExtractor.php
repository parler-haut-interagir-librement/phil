<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Extraction;

use Dom\Element;
use Dom\HTMLDocument;
use Phil\LinkChecker\Exception\InvalidOption;
use Phil\LinkChecker\LinkStatus;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;
use Uri\WhatWg\Url as WhatWgUrl;

/**
 * The links of an HTML content, in document order: `a[href]`, `img[src]` and `img[srcset]`, `link[href]`,
 * `iframe[src]`, `source[src]` and `source[srcset]`. The content is parsed by PHP's HTML5 parser, `Dom\HTMLDocument`,
 * as a browser parses it, malformed or not; each address is resolved against the page's address — or its first
 * `<base href>` — by the WHATWG URL Standard, then read by phil/url.
 *
 * Left out: the addresses of another scheme than `http` and `https` (`mailto:`, `tel:`, `javascript:`, `data:`), an
 * empty `href` and a lone `#`, which point at the page itself, and the `link` elements whose `rel` is `preconnect` or
 * `dns-prefetch`, which name an origin, not a resource.
 *
 * A content is read up to `$maxBytes`, and at most `$maxLinks` links are kept: past either, the result says it was
 * truncated.
 */
final readonly class LinkExtractor
{
    public const int MAX_BYTES = 64 * 1024 * 1024;

    public const int MAX_LINKS = 100_000;

    /** One selector per element: a list of `img[src], img[srcset]` would give an image with both twice on some builds. */
    private const string SELECTOR = 'a[href], img, link[href], iframe[src], source';

    public function __construct(
        public int $maxBytes = 4 * 1024 * 1024,
        public int $maxLinks = 2_000,
    ) {
        if ($maxBytes < 1 || $maxBytes > self::MAX_BYTES) {
            throw InvalidOption::outOfRange('The bytes of a content', $maxBytes, 1, self::MAX_BYTES);
        }

        if ($maxLinks < 1 || $maxLinks > self::MAX_LINKS) {
            throw InvalidOption::outOfRange('The links of a content', $maxLinks, 1, self::MAX_LINKS);
        }
    }

    public function extract(string $html, Url $page): ExtractedLinks
    {
        $truncated = \strlen($html) > $this->maxBytes;
        $document = HTMLDocument::createFromString(substr($html, 0, $this->maxBytes), \LIBXML_NOERROR, 'UTF-8');
        $base = $this->base($document, WhatWgUrl::parse($page->toString()));
        $links = [];

        foreach ($document->querySelectorAll(self::SELECTOR) as $element) {
            $name = $element->localName;

            if ('link' === $name && $this->onlyNamesAnOrigin($element)) {
                continue;
            }

            foreach ($this->attributes($name) as $attribute) {
                $value = $element->getAttribute($attribute);

                if (null === $value) {
                    continue;
                }

                $hrefs = 'srcset' === $attribute ? SrcsetParser::urls($value, $this->maxLinks + 1) : [$value];

                foreach ($hrefs as $href) {
                    $link = $this->link($href, $base, $element, $attribute);

                    if (!$link instanceof Link) {
                        continue;
                    }

                    if (\count($links) >= $this->maxLinks) {
                        return new ExtractedLinks($links, true);
                    }

                    $links[] = $link;
                }
            }
        }

        return new ExtractedLinks($links, $truncated);
    }

    /**
     * The attributes read on an element the selector found, in this order.
     *
     * @return list<string>
     */
    private function attributes(string $element): array
    {
        return match ($element) {
            'a', 'link' => ['href'],
            'img', 'source' => ['src', 'srcset'],
            default => ['src'],
        };
    }

    /**
     * The address relative links resolve against: the first `<base href>` of the document, when it resolves to a web
     * address, or the page's.
     */
    private function base(HTMLDocument $document, ?WhatWgUrl $page): ?WhatWgUrl
    {
        $href = $document->querySelector('base[href]')?->getAttribute('href');
        $base = null === $href ? null : WhatWgUrl::parse($href, $page);

        return $base instanceof WhatWgUrl && \in_array($base->getScheme(), ['http', 'https'], true) ? $base : $page;
    }

    private function onlyNamesAnOrigin(Element $element): bool
    {
        $rel = array_filter(explode(' ', strtr(strtolower((string) $element->getAttribute('rel')), "\t\n\f\r", '    ')), static fn (string $token): bool => '' !== $token);

        return [] !== $rel && [] === array_diff($rel, ['preconnect', 'dns-prefetch']);
    }

    private function link(string $href, ?WhatWgUrl $base, Element $element, string $attribute): ?Link
    {
        $href = trim($href, " \t\n\r\f");

        if ('' === $href || '#' === $href) {
            return null;
        }

        $text = $this->text($element);
        $written = $this->clean($href);
        $resolved = WhatWgUrl::parse($href, $base);

        if (!$resolved instanceof WhatWgUrl) {
            return new Link($written, null, $element->localName, $attribute, $text, LinkStatus::Invalid);
        }

        if (!\in_array($resolved->getScheme(), ['http', 'https'], true)) {
            return null;
        }

        try {
            return new Link($written, Url::fromString($resolved->toAsciiString()), $element->localName, $attribute, $text);
        } catch (UrlException) {
            return new Link($written, null, $element->localName, $attribute, $text, LinkStatus::Refused);
        }
    }

    /**
     * The words a reader sees for the link: the text of a link, or the alternative text of its image; the alternative
     * text or the title of an image or a frame; the `rel` of a `link` element.
     */
    private function text(Element $element): string
    {
        $text = match ($element->localName) {
            'a' => $this->linkText($element),
            'link' => (string) $element->getAttribute('rel'),
            default => (string) ($element->getAttribute('alt') ?? $element->getAttribute('title')),
        };

        return $this->clean($text);
    }

    private function linkText(Element $element): string
    {
        $text = trim((string) $element->textContent);

        return '' !== $text ? $text : (string) ($element->querySelector('img[alt]')?->getAttribute('alt') ?? $element->getAttribute('title'));
    }

    /**
     * The text on one line: whitespace and control characters collapsed to single spaces, cut to `Link::MAX_TEXT`
     * characters.
     */
    private function clean(string $text): string
    {
        $text = trim((string) preg_replace('/[\s\p{C}]+/u', ' ', $text));

        return mb_strlen($text) > Link::MAX_TEXT ? mb_substr($text, 0, Link::MAX_TEXT - 1).'…' : $text;
    }
}
