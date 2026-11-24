<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Markup;

use Dom\Element;
use Dom\HTMLDocument;
use Phil\SeoCheck\RenderedPage;
use ValueError;

use const Dom\HTML_NO_DEFAULT_NS;

/**
 * Reads what the rules need of a rendered page with the HTML5 parser of PHP, `Dom\HTMLDocument`, as a browser parses
 * it: a page that is not HTML gives markup that is empty, never an exception.
 *
 * Its cost is bounded: the page is cut at `$maxBytes`, at most `$maxElements` elements of each kind are read, and each
 * text is cut at `MAX_TEXT` characters. The character set is the one of the `Content-Type` header, else the one the page
 * declares, else UTF-8.
 */
final readonly class MarkupReader
{
    /** The most bytes of a page read, by default. */
    public const int MAX_BYTES = 5_000_000;

    /** The most elements of one kind read, by default: links, images, headings, `<meta>`. */
    public const int MAX_ELEMENTS = 5_000;

    /** The most characters of a text kept. */
    public const int MAX_TEXT = 1_000;

    public function __construct(
        private int $maxBytes = self::MAX_BYTES,
        private int $maxElements = self::MAX_ELEMENTS,
    ) {
    }

    public function read(RenderedPage $page): PageMarkup
    {
        $truncated = \strlen($page->html) > $this->maxBytes;
        $document = $this->parse($truncated ? substr($page->html, 0, $this->maxBytes) : $page->html, $this->charset($page));

        $meta = [];

        foreach ($this->elements($document, 'meta') as $element) {
            $name = strtolower(trim($element->getAttribute('name') ?? ''));
            $content = $element->getAttribute('content');

            if ('' !== $name && null !== $content) {
                $meta[$name][] = $content;
            }
        }

        $canonicals = [];
        $alternates = [];

        foreach ($this->elements($document, 'link') as $element) {
            $rel = self::tokens($element->getAttribute('rel') ?? '');
            $href = $element->getAttribute('href');

            if (null === $href) {
                continue;
            }

            if (\in_array('canonical', $rel, true)) {
                $canonicals[] = $href;
            }

            $hreflang = $element->getAttribute('hreflang');

            if (\in_array('alternate', $rel, true) && null !== $hreflang) {
                $alternates[] = new Alternate(trim($hreflang), $href);
            }
        }

        $links = [];

        foreach ($this->elements($document, 'a') as $element) {
            $links[] = new Link($element->getAttribute('href'), self::text($element->textContent));
        }

        $images = [];

        foreach ($this->elements($document, 'img') as $element) {
            $role = self::tokens($element->getAttribute('role') ?? '');
            $hidden = 'true' === strtolower(trim($element->getAttribute('aria-hidden') ?? '')) || \in_array('presentation', $role, true) || \in_array('none', $role, true);
            $images[] = new Image($element->getAttribute('src'), $element->getAttribute('alt'), $hidden);
        }

        $language = $document->documentElement?->getAttribute('lang');

        return new PageMarkup(
            array_map(static fn (Element $title): string => self::text($title->textContent), $this->elements($document, 'title')),
            $meta,
            null === $language ? null : trim($language),
            array_map(static fn (Element $heading): string => self::text($heading->textContent), $this->elements($document, 'h1')),
            $canonicals,
            $alternates,
            $links,
            $images,
            $truncated,
        );
    }

    /**
     * Text on one line: its runs of whitespace one space, trimmed, cut at `MAX_TEXT` characters.
     */
    public static function text(?string $text): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $text ?? '') ?? ''), 0, self::MAX_TEXT);
    }

    /**
     * The tokens of an attribute such as `rel` or `role`, in lower case.
     *
     * @return list<string>
     */
    public static function tokens(string $value): array
    {
        return array_values(array_filter(explode(' ', strtr(strtolower($value), "\t\n\f\r", '    ')), static fn (string $token): bool => '' !== $token));
    }

    /**
     * The first elements of one name, in document order, `$maxElements` at most.
     *
     * @return list<Element>
     */
    private function elements(HTMLDocument $document, string $name): array
    {
        $collection = $document->getElementsByTagName($name);
        $elements = [];
        $count = min($collection->length, $this->maxElements);

        for ($index = 0; $index < $count; ++$index) {
            /** @var Element $element an item of a collection, below its length */
            $element = $collection->item($index);
            $elements[] = $element;
        }

        return $elements;
    }

    private function parse(string $html, ?string $charset): HTMLDocument
    {
        $options = \LIBXML_NOERROR | HTML_NO_DEFAULT_NS;

        try {
            return HTMLDocument::createFromString($html, $options, $charset);
        } catch (ValueError) {
            return HTMLDocument::createFromString($html, $options);
        }
    }

    /**
     * The `charset` parameter of the `Content-Type` header, if the response declares one.
     */
    private function charset(RenderedPage $page): ?string
    {
        foreach ($page->header('Content-Type') as $type) {
            if (1 === preg_match('/;\s*charset\s*=\s*"?([A-Za-z0-9._:-]{1,40})"?/i', $type, $match)) {
                return $match[1];
            }
        }

        return null;
    }
}
