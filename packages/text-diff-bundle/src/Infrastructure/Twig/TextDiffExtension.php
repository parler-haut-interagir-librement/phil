<?php

declare(strict_types=1);

namespace Phil\TextDiffBundle\Infrastructure\Twig;

use InvalidArgumentException;
use Phil\TextDiff\Exception\InvalidText;
use Phil\TextDiff\TextDiff;
use Phil\TextDiff\Tokenizer;
use Phil\Ujo\Document;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Ujo;
use Stringable;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Twig\Attribute\AsTwigFilter;
use Twig\Markup;

/**
 * Differences in templates, marked safe:
 *
 * - `{{ old|text_diff(new) }}` compares two texts by the configured tokenizer, or by the one named —
 *   `{{ old|text_diff(new, 'lines') }}` —, as HTML with `<ins>` and `<del>`;
 * - `{{ old|text_diff_document(new) }}` compares two UJO documents, given as documents or as their JSON, block by
 *   block, each block rendered by the application's UJO renderer.
 *
 * Plain text is always escaped. A comparison of HTML reads as markup a `Twig\Markup` — what a macro or a captured
 * `{% set %}` block gives, or a value the application marked safe —, and any other value through the configured HTML
 * sanitizer; without a sanitizer, that value is escaped first, so that its tags are shown as text and never run.
 * `null` stands for an empty text.
 */
final readonly class TextDiffExtension
{
    public function __construct(
        private TextDiff $differ,
        private Tokenizer $tokenizer = Tokenizer::Words,
        private ?Ujo $ujo = null,
        private ?HtmlRenderer $renderer = null,
        private ?HtmlSanitizerInterface $sanitizer = null,
    ) {
    }

    /**
     * @param string|null $by `words`, `lines`, `paragraphs` or `html`; null for the configured tokenizer
     *
     * @throws InvalidArgumentException when `$by` names no tokenizer
     * @throws InvalidText              when a text is not valid UTF-8
     */
    #[AsTwigFilter(name: 'text_diff', isSafe: ['html'])]
    public function textDiff(string|Stringable|null $old, string|Stringable|null $new, ?string $by = null): string
    {
        $tokenizer = null === $by ? $this->tokenizer : (Tokenizer::tryFrom($by) ?? throw new InvalidArgumentException(\sprintf('The text_diff filter compares by words, lines, paragraphs or html; not by "%s".', $by)));

        return $this->differ->toHtml($this->differ->compare($this->text($old, $tokenizer), $this->text($new, $tokenizer), $tokenizer));
    }

    /**
     * @throws InvalidText when a text field is not valid UTF-8
     */
    #[AsTwigFilter(name: 'text_diff_document', isSafe: ['html'])]
    public function documentDiff(Document|string $old, Document|string $new): string
    {
        $ujo = $this->ujo ?? new Ujo();

        return $this->differ->documentToHtml($this->differ->documents($this->document($ujo, $old), $this->document($ujo, $new)), $this->renderer);
    }

    private function text(string|Stringable|null $value, Tokenizer $tokenizer): string
    {
        $text = null === $value ? '' : (\is_string($value) ? $value : $value->__toString());

        if (!$tokenizer->isMarkup() || $value instanceof Markup) {
            return $text;
        }

        return $this->sanitizer?->sanitize($text) ?? htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }

    private function document(Ujo $ujo, Document|string $document): Document
    {
        return \is_string($document) ? $ujo->decode($document) : $document;
    }
}
