<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes\Text;

use Phil\FootnotesSidenotes\Internal\ReferenceMarkup;
use Phil\FootnotesSidenotes\Note;
use Phil\FootnotesSidenotes\Resolution;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Html\InlineSanitizer;
use Phil\Ujo\Text\PlainText;

/**
 * A document with its notes as plain text, for a search index or an accessibility check: each reference written `[1]`
 * in the text, and the notes, one per line, after the last block. A note stands once, where a reader looks for it.
 */
final readonly class NotesPlainText
{
    public function __construct(
        private PlainText $text = new PlainText(),
    ) {
    }

    public function convert(Document $document, Resolution $resolution): string
    {
        $text = $this->text
            ->with('footnote', static fn (): string => '')
            ->convert($document->withBlocks(array_map(fn (Block $block): Block => $this->marked($block, $resolution), $document->blocks)));
        $notes = implode("\n", array_map($this->note(...), $resolution->notes));

        return implode("\n\n", array_filter([$text, $notes], static fn (string $part): bool => '' !== $part));
    }

    private function note(Note $note): string
    {
        return '['.$note->label.'] '.ReferenceMarkup::collapse($this->text->text(ReferenceMarkup::strip($note->html)));
    }

    private function marked(Block $block, Resolution $resolution): Block
    {
        if (isset(ReferenceMarkup::LITERAL_TYPES[$block->type])) {
            return $block;
        }

        /** @var array<array-key, mixed> $data */
        $data = $this->value($block->data, $resolution);

        return $block->withData($data);
    }

    private function value(mixed $value, Resolution $resolution): mixed
    {
        return match (true) {
            $value instanceof Block => $this->marked($value, $resolution),
            \is_array($value) => array_map(fn (mixed $item): mixed => $this->value($item, $resolution), $value),
            \is_string($value) => ReferenceMarkup::replace($value, static fn (string $id): string => self::mark($resolution->note($id))),
            default => $value,
        };
    }

    private static function mark(?Note $note): string
    {
        return $note instanceof Note ? InlineSanitizer::escape('['.$note->label.']') : '';
    }
}
