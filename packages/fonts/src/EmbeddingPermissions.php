<?php

declare(strict_types=1);

namespace Phil\Fonts;

/**
 * The embedding permissions of a face, read from the `fsType` field of its `OS/2` table.
 *
 * When several usage bits are set, which only fonts older than `OS/2` version 3 may do, the least restrictive one wins,
 * as the OpenType specification asks. A face without an `OS/2` table declares nothing, which reads as installable.
 */
final readonly class EmbeddingPermissions
{
    private const int RESTRICTED = 0x0002;

    private const int PREVIEW_AND_PRINT = 0x0004;

    private const int EDITABLE = 0x0008;

    private const int NO_SUBSETTING = 0x0100;

    private const int BITMAP_ONLY = 0x0200;

    public EmbeddingUsage $usage;

    public function __construct(
        public int $fsType = 0,
    ) {
        $this->usage = match (true) {
            0 !== ($fsType & self::EDITABLE) => EmbeddingUsage::Editable,
            0 !== ($fsType & self::PREVIEW_AND_PRINT) => EmbeddingUsage::PreviewAndPrint,
            0 !== ($fsType & self::RESTRICTED) => EmbeddingUsage::Restricted,
            default => EmbeddingUsage::Installable,
        };
    }

    /**
     * Whether a document may embed only the glyphs it uses; when not, it must embed the whole font.
     */
    public function allowsSubsetting(): bool
    {
        return 0 === ($this->fsType & self::NO_SUBSETTING);
    }

    /**
     * Whether a document may embed only the bitmaps of the font, never its outlines.
     */
    public function bitmapOnly(): bool
    {
        return 0 !== ($this->fsType & self::BITMAP_ONLY);
    }

    /**
     * Whether a document — a PDF, an EPUB — may embed the outlines of the font: its usage allows embedding and it is
     * not limited to bitmaps.
     */
    public function allowsOutlineEmbedding(): bool
    {
        return $this->usage->allowsEmbedding() && !$this->bitmapOnly();
    }
}
