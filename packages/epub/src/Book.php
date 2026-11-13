<?php

declare(strict_types=1);

namespace Phil\Epub;

use Closure;

/**
 * A book to write as an EPUB 3 publication: its metadata, its chapters in reading order, its stylesheets, images and
 * fonts, its cover, and how its navigation is made — a table of contents built from the headings of its chapters down
 * to `$tocDepth`, or given, the labels of the navigation document, and whether that document is also in the reading
 * order.
 *
 * `$urls` maps each address found in the chapters before the writer reads it: it returns the address to use, a path
 * relative to the chapter or an absolute URL, or null to drop it — the application maps its own media addresses to
 * the paths of the images it added.
 *
 * `$rendition` lays the book out, reflowable or pre-paginated; `$narration` gives the narrators and classes of the
 * media overlays of its chapters; `$ncx` adds the EPUB 2 table of contents, `toc.ncx`, for older reading systems.
 *
 * A book is a value: each `with` method returns a new one.
 */
final readonly class Book
{
    /**
     * @param list<Chapter>                   $chapters
     * @param list<Asset>                     $assets
     * @param list<TocEntry>|null             $toc
     * @param (Closure(string): ?string)|null $urls
     */
    public function __construct(
        public Metadata $metadata,
        public array $chapters = [],
        public array $assets = [],
        public ?Cover $cover = null,
        public ?array $toc = null,
        public int $tocDepth = 3,
        public NavigationLabels $labels = new NavigationLabels(),
        public bool $navigationInSpine = false,
        public ?Closure $urls = null,
        public Rendition $rendition = new Rendition(),
        public Narration $narration = new Narration(),
        public bool $ncx = false,
    ) {
    }

    public function withChapter(Chapter ...$chapters): self
    {
        return $this->with(chapters: [...$this->chapters, ...array_values($chapters)]);
    }

    public function withAsset(Asset ...$assets): self
    {
        return $this->with(assets: [...$this->assets, ...array_values($assets)]);
    }

    public function withCover(Cover $cover): self
    {
        return $this->with(cover: $cover);
    }

    /**
     * @param list<TocEntry> $toc
     */
    public function withToc(array $toc): self
    {
        return $this->with(toc: $toc);
    }

    public function withTocDepth(int $depth): self
    {
        return $this->with(tocDepth: $depth);
    }

    public function withLabels(NavigationLabels $labels): self
    {
        return $this->with(labels: $labels);
    }

    public function withNavigationInSpine(bool $inSpine = true): self
    {
        return $this->with(navigationInSpine: $inSpine);
    }

    /**
     * @param Closure(string): ?string $urls
     */
    public function withUrls(Closure $urls): self
    {
        return $this->with(urls: $urls);
    }

    public function withRendition(Rendition $rendition): self
    {
        return $this->with(rendition: $rendition);
    }

    public function withNarration(Narration $narration): self
    {
        return $this->with(narration: $narration);
    }

    /**
     * The book with, or without, the EPUB 2 table of contents `toc.ncx` that older reading systems read.
     */
    public function withNcx(bool $ncx = true): self
    {
        return $this->with(ncx: $ncx);
    }

    /**
     * @param list<Chapter>|null              $chapters
     * @param list<Asset>|null                $assets
     * @param list<TocEntry>|null             $toc
     * @param (Closure(string): ?string)|null $urls
     */
    private function with(
        ?array $chapters = null,
        ?array $assets = null,
        ?Cover $cover = null,
        ?array $toc = null,
        ?int $tocDepth = null,
        ?NavigationLabels $labels = null,
        ?bool $navigationInSpine = null,
        ?Closure $urls = null,
        ?Rendition $rendition = null,
        ?Narration $narration = null,
        ?bool $ncx = null,
    ): self {
        return new self(
            $this->metadata,
            $chapters ?? $this->chapters,
            $assets ?? $this->assets,
            $cover ?? $this->cover,
            $toc ?? $this->toc,
            $tocDepth ?? $this->tocDepth,
            $labels ?? $this->labels,
            $navigationInSpine ?? $this->navigationInSpine,
            $urls ?? $this->urls,
            $rendition ?? $this->rendition,
            $narration ?? $this->narration,
            $ncx ?? $this->ncx,
        );
    }
}
