<?php

declare(strict_types=1);

namespace Phil\PrintPreflight;

use Phil\Fonts\EmbeddingPermissions;
use Phil\Fonts\Font;

/**
 * A font the document prints with: its name, the embedding permissions it declares, whether the file embeds it and
 * whether it embeds only the glyphs it uses.
 */
final readonly class UsedFont
{
    public function __construct(
        public string $name,
        public EmbeddingPermissions $permissions = new EmbeddingPermissions(),
        public bool $embedded = true,
        public bool $subset = false,
    ) {
    }

    /**
     * The font as `phil/fonts` reads it from its file, named by its full name, or by its family without one.
     */
    public static function fromFont(Font $font, bool $embedded = true, bool $subset = false): self
    {
        return new self($font->fullName() ?? $font->family(), $font->embedding, $embedded, $subset);
    }
}
