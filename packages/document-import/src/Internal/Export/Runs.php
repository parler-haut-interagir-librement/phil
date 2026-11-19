<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Export;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;
use Phil\DocumentImport\Internal\Mark;

/**
 * The inline HTML of a UJO text read as the runs a word processor writes: pieces of text with their character
 * formatting and link, line breaks, and footnote references. A link keeps an address of `http`, `https`, `mailto` or
 * `tel`; any other address, and any element without formatting, keeps only its text.
 *
 * @internal
 */
final class Runs
{
    private const array MARKS = [
        'b' => Mark::Bold, 'strong' => Mark::Bold, 'i' => Mark::Italic, 'em' => Mark::Italic, 'u' => Mark::Underline,
        's' => Mark::Strikethrough, 'del' => Mark::Strikethrough, 'strike' => Mark::Strikethrough, 'mark' => Mark::Marker,
        'code' => Mark::Code, 'kbd' => Mark::Code, 'sup' => Mark::Superscript, 'sub' => Mark::Subscript,
    ];

    /**
     * @return list<Run>
     */
    public static function of(string $html): array
    {
        if ('' === trim($html)) {
            return [];
        }

        $body = HTMLDocument::createEmpty()->createElement('body');
        $body->innerHTML = $html;

        $runs = [];
        self::walk($body, [], null, $runs);

        return $runs;
    }

    /**
     * The text of inline HTML, without its markup.
     */
    public static function text(string $html): string
    {
        return implode('', array_map(static fn (Run $run): string => $run->text, self::of($html)));
    }

    /**
     * @param list<Mark> $marks
     * @param list<Run>  $runs
     */
    private static function walk(Node $parent, array $marks, ?string $link, array &$runs): void
    {
        foreach ($parent->childNodes as $node) {
            if ($node instanceof Text) {
                $text = (string) preg_replace('/\s+/u', ' ', $node->data);

                if ('' !== $text) {
                    $runs[] = new Run($text, $marks, $link);
                }

                continue;
            }

            if ($node instanceof Element) {
                self::element($node, $marks, $link, $runs);
            }
        }
    }

    /**
     * @param list<Mark> $marks
     * @param list<Run>  $runs
     */
    private static function element(Element $element, array $marks, ?string $link, array &$runs): void
    {
        // The HTML parser gives the names of HTML elements in lower case.
        $name = $element->localName;
        $note = $element->getAttribute('data-footnote') ?? '';

        if ('sup' === $name && '' !== $note) {
            $runs[] = new Run(trim($element->textContent ?? ''), [Mark::Superscript], null, note: $note);

            return;
        }

        if ('br' === $name) {
            $runs[] = new Run('', $marks, $link, true);
        }

        if ('a' === $name) {
            $href = trim($element->getAttribute('href') ?? '');
            $link = 1 === preg_match('/^(https?:\/\/|mailto:|tel:)/i', $href) ? $href : $link;
        }

        $mark = self::MARKS[$name] ?? null;
        self::walk($element, null === $mark || \in_array($mark, $marks, true) ? $marks : [...$marks, $mark], $link, $runs);
    }
}
