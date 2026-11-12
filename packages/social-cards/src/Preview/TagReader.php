<?php

declare(strict_types=1);

namespace Phil\SocialCards\Preview;

use Dom\Element;
use Dom\HTMLDocument;
use Phil\SocialCards\Text;
use ValueError;

use const Dom\HTML_NO_DEFAULT_NS;

/**
 * Reads the tags of a page nobody here wrote: its `<title>`, its canonical link and its `<meta>` tags, parsed by the
 * HTML5 parser of PHP.
 *
 * Its cost is bounded: the page is cut at `$maxBytes` and at the end of its head, at most `MAX_TAGS` tags are read, at
 * most `MAX_VALUES` contents are kept for one key, and each content is put on one line and cut at `MAX_LENGTH`
 * characters. A page that is not HTML gives tags that are empty, never an exception.
 */
final readonly class TagReader
{
    public const int MAX_TAGS = 300;

    public const int MAX_VALUES = 10;

    public const int MAX_LENGTH = 2048;

    public const int MAX_KEY = 100;

    /** The most bytes of a page read, by default. */
    public const int MAX_BYTES = 524_288;

    public function __construct(
        private int $maxBytes = self::MAX_BYTES,
    ) {
    }

    /**
     * @param string      $url     the page's address, which relative links are resolved against
     * @param string|null $charset the character set the server declared, if any
     */
    public function read(string $html, string $url, ?string $charset = null): PageTags
    {
        $html = substr($html, 0, $this->maxBytes);
        $end = stripos($html, '</head');

        if (false !== $end) {
            $html = substr($html, 0, $end);
        }

        $document = $this->parse($html, $charset);
        $meta = [];
        $tags = $document->getElementsByTagName('meta');
        $count = min($tags->length, self::MAX_TAGS);

        for ($index = 0; $index < $count; ++$index) {
            /** @var Element $tag an item of a collection, below its length */
            $tag = $tags->item($index);
            $key = strtolower(trim($tag->getAttribute('property') ?? $tag->getAttribute('name') ?? ''));
            $content = Text::line($tag->getAttribute('content'), self::MAX_LENGTH);

            if ('' === $key || \strlen($key) > self::MAX_KEY || null === $content || \count($meta[$key] ?? []) >= self::MAX_VALUES) {
                continue;
            }

            $meta[$key][] = $content;
        }

        return new PageTags($url, $this->title($document), $this->canonical($document, $url), $meta);
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

    private function title(HTMLDocument $document): ?string
    {
        $title = $document->getElementsByTagName('title')->item(0);

        return $title instanceof Element ? Text::line($title->textContent, self::MAX_LENGTH) : null;
    }

    private function canonical(HTMLDocument $document, string $url): ?string
    {
        $links = $document->getElementsByTagName('link');
        $count = min($links->length, self::MAX_TAGS);

        for ($index = 0; $index < $count; ++$index) {
            /** @var Element $link an item of a collection, below its length */
            $link = $links->item($index);

            if (1 === preg_match('/(?:^|\s)canonical(?:\s|$)/i', (string) $link->getAttribute('rel'))) {
                return Text::resolve($link->getAttribute('href'), $url);
            }
        }

        return null;
    }
}
