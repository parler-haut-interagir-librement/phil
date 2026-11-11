<?php

declare(strict_types=1);

namespace Phil\Fonts;

/**
 * The embedding licence a face declares in the low bits of its `OS/2` `fsType`, from the least restrictive to the most.
 */
enum EmbeddingUsage: string
{
    /** Bit 0 clear everywhere: the font may be embedded, and installed for good on the reader's system. */
    case Installable = 'installable';

    /** Bit 3: embedded and used to edit the document, then removed with it. */
    case Editable = 'editable';

    /** Bit 2: embedded to view and print the document, which stays read-only. */
    case PreviewAndPrint = 'preview-and-print';

    /** Bit 1: never embedded without the owner's own permission. */
    case Restricted = 'restricted';

    /**
     * Whether a document may carry the font at all.
     */
    public function allowsEmbedding(): bool
    {
        return self::Restricted !== $this;
    }

    /**
     * Whether a document that carries the font may still be edited with it.
     */
    public function allowsEditing(): bool
    {
        return self::Installable === $this || self::Editable === $this;
    }
}
