<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;

/**
 * Rewrites the PDF to make it smaller without losing quality. Each pass runs unless it is turned off.
 */
final readonly class Optimize implements Stage
{
    /**
     * @param bool $objectStreams    packs objects into compressed object streams
     * @param bool $dedup            stores identical streams once
     * @param bool $recompressImages recompresses images without losing quality
     * @param bool $subsetFonts      keeps only the glyphs the document shows
     */
    public function __construct(
        public bool $objectStreams = true,
        public bool $dedup = true,
        public bool $recompressImages = true,
        public bool $subsetFonts = true,
    ) {
    }

    #[Override]
    public function operation(): Operation
    {
        return Operation::Optimize;
    }

    #[Override]
    public function options(): array
    {
        return array_filter([
            'objectStreams' => $this->objectStreams,
            'dedup' => $this->dedup,
            'recompressImages' => $this->recompressImages,
            'subsetFonts' => $this->subsetFonts,
        ], static fn (bool $on): bool => !$on);
    }

    #[Override]
    public function files(): array
    {
        return [];
    }
}
