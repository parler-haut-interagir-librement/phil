<?php

declare(strict_types=1);

namespace Phil\DocumentImport;

use Override;
use Phil\DocumentImport\Contract\MediaSource;

/**
 * A media source over images already in memory, by their address — the media of an import, so that an imported
 * document exports again with its images.
 */
final readonly class MediaMap implements MediaSource
{
    /**
     * @param array<string, Media|string> $media the image, or its bytes, at each address
     */
    public function __construct(private array $media = [])
    {
    }

    #[Override]
    public function fetch(string $url): ?string
    {
        $media = $this->media[$url] ?? null;

        return $media instanceof Media ? $media->contents : $media;
    }
}
