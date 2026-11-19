<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Rtf;

/**
 * The text and properties of a destination that is not body text — a style of the style sheet, a field of the
 * document information, a field instruction, a picture, a list marker —, read until its group closes.
 *
 * @internal
 */
final class RtfCapture
{
    public string $text = '';

    /** @var array<string, int|string> */
    public array $properties = [];

    public function __construct(
        public readonly string $name,
        public readonly ?self $parent = null,
    ) {
    }

    /**
     * The value of a shape property read, when it is the description of a picture, as the picture's text
     * alternative.
     */
    public function describePicture(): void
    {
        $picture = $this->parent?->parent;

        if ('wzDescription' === ($this->parent->properties['sn'] ?? null) && 'pict' === $picture?->name) {
            $picture->properties['description'] = trim($this->text);
        }
    }
}
