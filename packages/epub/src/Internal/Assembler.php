<?php

declare(strict_types=1);

namespace Phil\Epub\Internal;

use Closure;
use Phil\Epub\Accessibility\AccessibilityFeature;
use Phil\Epub\Asset;
use Phil\Epub\AssetKind;
use Phil\Epub\Book;
use Phil\Epub\Chapter;
use Phil\Epub\Cover;
use Phil\Epub\Exception\InvalidBook;
use Phil\Epub\LandmarkType;
use Phil\Epub\Layout;
use Phil\Epub\MediaOverlay;

/**
 * Every file of the publication, in archive order, from a book: the chapters converted twice — once to learn the
 * identifiers of every document, once to write links that cross documents —, then the navigation, the EPUB 2 table
 * of contents when asked, the cover, the media overlays, the package document and the container.
 *
 * @internal
 */
final readonly class Assembler
{
    public const string PACKAGE = 'EPUB/';

    private const array RESERVED = ['nav.xhtml' => 'navigation document', 'toc.ncx' => 'EPUB 2 table of contents', 'cover.xhtml' => 'cover document', 'package.opf' => 'package document'];

    private const array LINK_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    private const array REMOTE_MEDIA = ['audio', 'video', 'source', 'track'];

    private XhtmlConverter $converter;

    public function __construct()
    {
        $this->converter = new XhtmlConverter();
    }

    /**
     * @return array<string, string> the bytes of each file, by its path in the archive
     *
     * @throws InvalidBook
     */
    public function assemble(Book $book): array
    {
        if ([] === $book->chapters) {
            throw InvalidBook::because('A book needs at least one chapter.');
        }

        if ($book->tocDepth < 1 || $book->tocDepth > 6) {
            throw InvalidBook::because(\sprintf('The depth of the table of contents is a heading level from 1 to 6; %d given.', $book->tocDepth));
        }

        $documents = $this->documents($book->chapters);
        $assets = $this->assets($book, $documents);
        $ids = $this->identifiers($documents);
        $converted = [];

        foreach ($documents as $path => $chapter) {
            $converted[$path] = $this->converter->convert(
                $chapter->html,
                $this->linker($book->urls, $path, $ids, $assets),
                $this->resourcer($book->urls, $path, $assets),
            );
        }

        return $this->files($book, $documents, $assets, $converted);
    }

    /**
     * @param list<Chapter> $chapters
     *
     * @return array<string, Chapter>
     *
     * @throws InvalidBook
     */
    private function documents(array $chapters): array
    {
        $documents = [];

        foreach ($chapters as $index => $chapter) {
            $path = $chapter->fileName ?? 'chapter-'.($index + 1).'.xhtml';
            Paths::check($path);
            $this->claim($path, $documents);
            $documents[$path] = $chapter;
        }

        return $documents;
    }

    /**
     * @param array<string, Chapter> $documents
     *
     * @return array<string, Asset>
     *
     * @throws InvalidBook
     */
    private function assets(Book $book, array $documents): array
    {
        $assets = [];
        $all = $book->assets;

        if ($book->cover instanceof Cover && !\in_array($book->cover->image, $all, true)) {
            $all[] = $book->cover->image;
        }

        foreach ($all as $asset) {
            $this->claim($asset->path, $documents + $assets);
            $assets[$asset->path] = $asset;
        }

        foreach ($all as $asset) {
            for ($fallback = $asset->fallback; $fallback instanceof Asset; $fallback = $fallback->fallback) {
                if (($assets[$fallback->path] ?? null) !== $fallback) {
                    $this->claim($fallback->path, $documents + $assets);
                    $assets[$fallback->path] = $fallback;
                }
            }
        }

        return $assets;
    }

    /**
     * @param array<string, mixed> $taken
     *
     * @throws InvalidBook
     */
    private function claim(string $path, array $taken): void
    {
        if (isset(self::RESERVED[$path])) {
            throw InvalidBook::because(\sprintf('The path "%s" is the %s\'s.', $path, self::RESERVED[$path]));
        }

        if (isset($taken[$path])) {
            throw InvalidBook::because(\sprintf('The path "%s" is used twice.', $path));
        }
    }

    /**
     * The identifiers of each document, read by a first conversion that keeps every address.
     *
     * @param array<string, Chapter> $documents
     *
     * @return array<string, array<string, true>>
     */
    private function identifiers(array $documents): array
    {
        $ids = [];

        foreach ($documents as $path => $chapter) {
            $converted = $this->converter->convert($chapter->html, static fn (string $url): string => $url, static fn (string $url): string => $url);
            $ids[$path] = array_fill_keys($converted->ids, true);
        }

        return $ids;
    }

    /**
     * @param (Closure(string): ?string)|null    $urls
     * @param array<string, array<string, true>> $ids
     * @param array<string, Asset>               $assets
     *
     * @return Closure(string): ?string
     */
    private function linker(?Closure $urls, string $path, array $ids, array $assets): Closure
    {
        return static function (string $url) use ($urls, $path, $ids, $assets): ?string {
            $url = $urls instanceof Closure ? $urls($url) : $url;

            if (null === $url || str_starts_with($url, '/')) {
                return null;
            }

            if (1 === preg_match('/^([a-z][a-z0-9+.-]*):/i', $url, $scheme)) {
                return \in_array(strtolower($scheme[1]), self::LINK_SCHEMES, true) ? $url : null;
            }

            $parts = explode('#', $url, 2);
            $target = explode('?', $parts[0])[0];
            $fragment = rawurldecode($parts[1] ?? '');

            if ('' === $target) {
                $owner = isset($ids[$path][$fragment]) ? $path : self::owner($ids, $fragment);

                return null === $owner ? null : ($owner === $path ? '' : Paths::relative($path, $owner)).'#'.$fragment;
            }

            $resolved = Paths::resolve($path, rawurldecode($target));

            if (isset($ids[$resolved])) {
                return Paths::relative($path, $resolved).(isset($ids[$resolved][$fragment]) ? '#'.$fragment : '');
            }

            return isset($assets[$resolved]) ? Paths::relative($path, $resolved) : null;
        };
    }

    /**
     * @param array<string, array<string, true>> $ids
     */
    private static function owner(array $ids, string $fragment): ?string
    {
        foreach ($ids as $path => $identifiers) {
            if (isset($identifiers[$fragment])) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param (Closure(string): ?string)|null $urls
     * @param array<string, Asset>            $assets
     *
     * @return Closure(string, string): ?string
     */
    private function resourcer(?Closure $urls, string $path, array $assets): Closure
    {
        return static function (string $url, string $element) use ($urls, $path, $assets): ?string {
            $url = $urls instanceof Closure ? $urls($url) : $url;

            if (null === $url || str_starts_with($url, '/')) {
                return null;
            }

            if (1 === preg_match('/^[a-z][a-z0-9+.-]*:/i', $url)) {
                return \in_array($element, self::REMOTE_MEDIA, true) && 1 === preg_match('#^https?://#i', $url) ? $url : null;
            }

            $resolved = Paths::resolve($path, rawurldecode(explode('#', explode('?', $url)[0])[0]));

            return isset($assets[$resolved]) ? Paths::relative($path, $resolved) : null;
        };
    }

    /**
     * @param array<string, Chapter>   $documents
     * @param array<string, Asset>     $assets
     * @param array<string, Converted> $converted
     *
     * @return array<string, string>
     *
     * @throws InvalidBook
     */
    private function files(Book $book, array $documents, array $assets, array $converted): array
    {
        $metadata = $book->metadata;
        $rendition = $book->rendition;
        $fixed = Layout::PrePaginated === $rendition->layout;
        $stylesheets = array_keys(array_filter($assets, static fn (Asset $asset): bool => AssetKind::Stylesheet === $asset->kind));
        $files = [];
        $items = [new ManifestItem('nav', 'nav.xhtml', 'application/xhtml+xml', ['nav'], $book->navigationInSpine ? true : null, spineProperties: $fixed && $book->navigationInSpine ? ['rendition:layout-reflowable'] : [])];
        $landmarks = [];
        $pages = [];

        if ($book->ncx) {
            $items[] = new ManifestItem('ncx', 'toc.ncx', 'application/x-dtbncx+xml');
        }

        if ($book->cover instanceof Cover) {
            $files['cover.xhtml'] = Xml::xhtml($metadata->language, $metadata->direction, $metadata->title, $stylesheets, 'cover', '<div class="epub-cover"><img src="'
                .XhtmlSerialization::attribute($book->cover->image->path).'" alt="'.XhtmlSerialization::attribute($book->cover->alt).'" role="doc-cover"/></div>', $fixed ? $rendition->viewport : null);
            array_unshift($items, new ManifestItem('cover', 'cover.xhtml', 'application/xhtml+xml', [], true));
            $landmarks[] = ['cover', $book->labels->cover, 'cover.xhtml'];
        }

        if ($book->navigationInSpine) {
            $landmarks[] = ['toc', $book->labels->tableOfContents, 'nav.xhtml#toc'];
        }

        $start = array_any($documents, static fn (Chapter $chapter): bool => LandmarkType::BodyMatter === $chapter->landmark);
        $index = 0;
        $titles = [];
        $overlays = [];
        $durations = [];

        foreach ($documents as $path => $chapter) {
            $content = $converted[$path];
            $title = $titles[$path] = $chapter->title ?? ($content->headings[0]->text ?? $metadata->title);
            $type = $chapter->landmark;
            [$viewport, $spineProperties] = PageLayout::of($book, $chapter, $path);

            if (null === $type && !$start) {
                $start = true;
                $landmarks[] = [LandmarkType::BodyMatter->value, $book->labels->start, $path];
            } elseif (null !== $type) {
                $landmarks[] = [$type->value, $title, $path];
            }

            $files[$path] = Xml::xhtml(
                $chapter->language ?? $metadata->language,
                $metadata->direction,
                $title,
                array_map(static fn (string $stylesheet): string => Paths::relative($path, $stylesheet), $stylesheets),
                $type?->value,
                $content->xhtml,
                $viewport,
            );

            foreach ($content->pages as $page) {
                $pages[] = [$path.'#'.$page->id, $page->label];
            }

            $overlay = null;

            if ($chapter->overlay instanceof MediaOverlay) {
                $overlay = 'overlay-'.(\count($overlays) + 1);
                $smil = substr($path, 0, -\strlen('.xhtml')).'.smil';
                $this->claim($smil, $documents + $assets + $overlays);
                Smil::check($chapter->overlay, $path, $content, $assets);
                $overlays[$smil] = Smil::write($chapter->overlay, $smil, $path);
                $durations[$overlay] = $chapter->overlay->duration();
            }

            $items[] = new ManifestItem('chapter-'.++$index, $path, 'application/xhtml+xml', array_keys(array_filter([
                'mathml' => $content->mathml,
                'remote-resources' => $content->remote,
                'svg' => $content->svg,
            ])), $chapter->linear, mediaOverlay: $overlay, spineProperties: $spineProperties);
        }

        $toc = null === $book->toc || [] === $book->toc ? HeadingToc::build($book, $documents, $converted, $titles) : $book->toc;
        $navigation = ['nav.xhtml' => Xml::xhtml($metadata->language, $metadata->direction, $book->labels->contents, $stylesheets, null, NavigationDocument::body($book->labels, $toc, $landmarks, $pages))];
        $files = $navigation + ($book->ncx ? ['toc.ncx' => Ncx::write($metadata, $toc, $pages)] : []) + $files;
        $obfuscated = [];
        $ids = [];

        foreach (array_keys($assets) as $position => $path) {
            $ids[$path] = 'asset-'.($position + 1);
        }

        foreach ($assets as $path => $asset) {
            $files[$path] = $asset->obfuscated ? FontObfuscation::apply($asset->bytes, $metadata->identifier->value) : $asset->bytes;

            if ($asset->obfuscated) {
                $obfuscated[] = self::PACKAGE.$path;
            }

            $items[] = new ManifestItem($ids[$path], $path, $asset->mediaType, $asset === $book->cover?->image ? ['cover-image'] : [], fallback: null === $asset->fallback ? null : $ids[$asset->fallback->path]);
        }

        $position = 0;

        foreach ($overlays as $path => $smil) {
            $files[$path] = $smil;
            $items[] = new ManifestItem('overlay-'.++$position, $path, 'application/smil+xml');
        }

        $narration = $book->narration;

        if ([] !== $overlays && [] === $stylesheets && (null !== $narration->activeClass || null !== $narration->playbackActiveClass)) {
            throw InvalidBook::because('The classes of the media overlays are styled by a stylesheet of the book, which has none.');
        }

        $features = [...[] === $pages ? [] : [AccessibilityFeature::PageNavigation], ...[] === $overlays ? [] : [AccessibilityFeature::SynchronizedAudioText]];
        $files['package.opf'] = PackageDocument::write($metadata, $items, PackageDocument::modes($assets), $features, $rendition, $narration, $durations, $book->ncx);
        $archive = ['mimetype' => 'application/epub+zip', 'META-INF/container.xml' => Xml::container(self::PACKAGE.'package.opf')];

        if ([] !== $obfuscated) {
            $archive['META-INF/encryption.xml'] = Xml::encryption($obfuscated);
        }

        foreach ($files as $path => $bytes) {
            $archive[self::PACKAGE.$path] = $bytes;
        }

        return $archive;
    }
}
