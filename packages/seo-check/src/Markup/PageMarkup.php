<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Markup;

/**
 * What the rules read of a page's HTML, read once: its titles, its `<meta>` tags by name, the language of its
 * document, its first-level headings, its canonical and alternate links, its links and its images, each in the order of
 * the page. Texts have their spaces collapsed; attributes are kept as written.
 */
final readonly class PageMarkup
{
    /**
     * @param list<string>                $titles     the text of each `<title>`
     * @param array<string, list<string>> $meta       the `content` of each `<meta>` by its `name`, in lower case
     * @param list<string>                $headings   the text of each `<h1>`
     * @param list<string>                $canonicals the `href` of each `<link rel="canonical">`
     * @param list<Alternate>             $alternates
     * @param list<Link>                  $links
     * @param list<Image>                 $images
     * @param bool                        $truncated  whether the page was longer than the reader reads
     */
    public function __construct(
        public array $titles = [],
        public array $meta = [],
        public ?string $language = null,
        public array $headings = [],
        public array $canonicals = [],
        public array $alternates = [],
        public array $links = [],
        public array $images = [],
        public bool $truncated = false,
    ) {
    }

    /**
     * Every `content` of a `<meta>` of this name, in the order of the page.
     *
     * @return list<string>
     */
    public function meta(string $name): array
    {
        return $this->meta[strtolower($name)] ?? [];
    }
}
