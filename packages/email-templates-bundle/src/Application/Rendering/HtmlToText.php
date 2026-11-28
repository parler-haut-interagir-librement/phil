<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application\Rendering;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;

/**
 * The plain-text part of an email, written from its HTML: paragraphs and headings separated by a blank line, a link
 * followed by its address, a list item by a dash or its number, the cells of a data table separated by « | », the
 * text of an image by its alternative text. What is not read — the head, the styles, an element marked
 * `data-text="skip"` or `hidden`, as the preheader — is left out.
 *
 * The HTML is read by the HTML5 parser of PHP, as a browser reads it, whatever its shape.
 */
final class HtmlToText
{
    private const array SKIPPED = ['head', 'style', 'script', 'title', 'template', 'noscript', 'svg', 'math'];

    private const array BLOCKS = ['p', 'div', 'section', 'article', 'header', 'footer', 'main', 'aside', 'nav', 'center', 'blockquote', 'table', 'tbody', 'thead', 'tfoot', 'form', 'fieldset', 'figure', 'figcaption', 'address', 'dl', 'dd', 'dt'];

    private const array HEADINGS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    private string $out = '';

    private bool $pre = false;

    private function __construct()
    {
    }

    public static function convert(string $html): string
    {
        if ('' === trim($html)) {
            return '';
        }

        $document = HTMLDocument::createFromString($html, \LIBXML_NOERROR | \LIBXML_COMPACT, 'UTF-8');
        $converter = new self();
        $converter->walk($document->body ?? $document);

        return self::tidy($converter->out);
    }

    private function walk(Node $node): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof Text) {
                $this->text($child->data);
            } elseif ($child instanceof Element) {
                $this->element($child);
            }
        }
    }

    private function element(Element $element): void
    {
        $name = strtolower($element->localName);

        if (\in_array($name, self::SKIPPED, true) || $element->hasAttribute('hidden') || 'skip' === $element->getAttribute('data-text')) {
            return;
        }

        match (true) {
            'br' === $name => $this->out .= "\n",
            'hr' === $name => $this->block('----'),
            'img' === $name => $this->image($element),
            'a' === $name => $this->link($element),
            'li' === $name => $this->item($element),
            'pre' === $name => $this->preformatted($element),
            'tr' === $name => $this->row($element),
            'ul' === $name, 'ol' === $name => $this->list($element),
            \in_array($name, self::HEADINGS, true) => $this->heading($element),
            \in_array($name, self::BLOCKS, true) => $this->paragraph($element),
            default => $this->walk($element),
        };
    }

    private function text(string $data): void
    {
        if ($this->pre) {
            $this->out .= $data;

            return;
        }

        $collapsed = preg_replace('/[ \t\r\n\f]+/', ' ', $data) ?? $data;

        if (str_starts_with($collapsed, ' ') && ('' === $this->out || str_ends_with($this->out, "\n") || str_ends_with($this->out, ' '))) {
            $collapsed = ltrim($collapsed, ' ');
        }

        $this->out .= $collapsed;
    }

    private function block(string $text): void
    {
        $this->breakParagraph();
        $this->out .= $text;
        $this->breakParagraph();
    }

    private function paragraph(Element $element): void
    {
        $this->breakParagraph();
        $this->walk($element);
        $this->breakParagraph();
    }

    private function heading(Element $element): void
    {
        $this->breakParagraph();
        $this->walk($element);
        $this->breakParagraph();
    }

    private function list(Element $element): void
    {
        $this->breakParagraph();
        $this->walk($element);
        $this->breakParagraph();
    }

    private function item(Element $element): void
    {
        $this->breakLine();
        $parent = $element->parentElement;
        $marker = '- ';

        if ($parent instanceof Element && 'ol' === strtolower($parent->localName)) {
            $position = 1;

            for ($sibling = $element->previousElementSibling; $sibling instanceof Element; $sibling = $sibling->previousElementSibling) {
                if ('li' === strtolower($sibling->localName)) {
                    ++$position;
                }
            }

            $marker = $position.'. ';
        }

        $this->out .= $marker;
        $this->walk($element);
        $this->breakLine();
    }

    private function row(Element $row): void
    {
        $table = $row->closest('table');
        $layout = $table instanceof Element && 'presentation' === $table->getAttribute('role');
        $first = true;

        foreach ($row->children as $cell) {
            if (!\in_array(strtolower($cell->localName), ['td', 'th'], true)) {
                continue;
            }

            if ($layout) {
                $this->walk($cell);
                $this->breakLine();

                continue;
            }

            if (!$first) {
                $this->out = rtrim($this->out, ' ').' | ';
            }

            $first = false;
            $this->walk($cell);
        }

        $this->breakLine();
    }

    private function link(Element $element): void
    {
        $before = \strlen($this->out);
        $this->walk($element);
        $label = trim(substr($this->out, $before));
        $href = trim($element->getAttribute('href') ?? '');

        if (1 === preg_match('#^(?:https?:|mailto:|tel:)#i', $href)) {
            $target = 1 === preg_match('/^mailto:/i', $href) ? substr($href, 7) : $href;

            if ('' === $label) {
                $this->out .= $target;
            } elseif ($label !== $target) {
                $this->out .= ' ('.$target.')';
            }
        }
    }

    private function image(Element $element): void
    {
        $alt = trim($element->getAttribute('alt') ?? '');

        if ('' !== $alt) {
            $this->text('['.$alt.']');
        }
    }

    private function preformatted(Element $element): void
    {
        $this->breakParagraph();
        $this->pre = true;
        $this->walk($element);
        $this->pre = false;
        $this->breakParagraph();
    }

    private function breakLine(): void
    {
        $this->out = rtrim($this->out, ' ');

        if ('' !== $this->out && !str_ends_with($this->out, "\n")) {
            $this->out .= "\n";
        }
    }

    private function breakParagraph(): void
    {
        $this->breakLine();

        if ('' !== $this->out && !str_ends_with($this->out, "\n\n")) {
            $this->out .= "\n";
        }
    }

    private static function tidy(string $text): string
    {
        $lines = array_map(static fn (string $line): string => rtrim($line, " \t"), explode("\n", $text));
        $joined = preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)) ?? '';

        return trim($joined, "\n ")."\n";
    }
}
