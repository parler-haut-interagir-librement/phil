<?php

declare(strict_types=1);

namespace Phil\Markup\Contract;

/**
 * Where a reader puts the elements it extracts from a source to keep them as files — an inline SVG drawing —, so
 * that the document names them by address: a media library, a storage of the site.
 *
 * The content is purified by the reader before it is stored: an SVG drawing has gone through `phil/svg-sanitizer`.
 */
interface ExtractedElementStore
{
    /**
     * Stores a file and gives its address — `http`, `https` or relative —; `null` when it is refused.
     *
     * @param string $content   the file's content
     * @param string $mediaType its media type, `image/svg+xml`
     * @param string $name      a name for the file, with its extension
     */
    public function store(string $content, string $mediaType, string $name): ?string;
}
