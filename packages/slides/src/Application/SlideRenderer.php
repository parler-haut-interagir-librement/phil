<?php

declare(strict_types=1);

namespace Phil\Slides\Application;

use Phil\Slides\Domain\ValueObject\Slide;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\UjoException;
use Phil\Ujo\Ujo;

/**
 * The HTML of a slide, by the renderer of `phil/ujo` the application configured: what the audience sees, checked and
 * purified, and the speaker's notes apart, for the presenter only. A plain-text note becomes a paragraph, its lines
 * kept.
 */
final readonly class SlideRenderer
{
    public function __construct(
        private Ujo $ujo,
    ) {
    }

    /**
     * The HTML the audience sees, without any note.
     *
     * @throws UjoException when a block breaks the rules of the configured renderer
     */
    public function content(Slide $slide): string
    {
        return $this->ujo->toHtml(new Document($slide->content));
    }

    /**
     * The HTML of the speaker's notes: the blocks of the notes containers, then the plain-text notes; empty without any.
     *
     * @throws UjoException when a block breaks the rules of the configured renderer
     */
    public function notes(Slide $slide): string
    {
        $texts = [];

        foreach ($slide->noteTexts as $index => $text) {
            $texts[] = new Block('phil-slides-note-'.$index, 'paragraph', ['text' => str_replace("\n", "<br>\n", htmlspecialchars($text, \ENT_QUOTES))]);
        }

        return $this->ujo->toHtml(new Document([...$slide->notes, ...$texts]));
    }
}
