<?php

declare(strict_types=1);

namespace Phil\Typography\Html;

use Closure;
use Dom\Element;
use Dom\Node;
use Dom\Text;
use Phil\Typography\Text\Run;

/**
 * Walks a DOM tree and gives its text, block by block, to a fixer of runs: the text nodes between two block boundaries
 * — a paragraph, a heading, a list item, a cell, a line break — form one run, the text of a protected element is a
 * locked part of it, and comments are left out. Each text node is then written back. When fixers are given by
 * language, an element whose `lang` names another language than the one around it has its text fixed apart, by the
 * fixer of its language — or by the one around it when its language has none.
 *
 * @internal
 */
final readonly class Blocks
{
    /**
     * The elements that end a run of text where they open and where they close.
     */
    public const array BOUNDARIES = [
        'address', 'article', 'aside', 'blockquote', 'body', 'br', 'caption', 'dd', 'details', 'dialog', 'div', 'dl',
        'dt', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head',
        'header', 'hgroup', 'hr', 'html', 'legend', 'li', 'main', 'nav', 'ol', 'option', 'p', 'section', 'summary',
        'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'title', 'tr', 'ul',
    ];

    /**
     * @param array<string, true>                    $protected the names of the protected elements
     * @param Closure(Run): Run                      $fix
     * @param ?Closure(string): (?Closure(Run): Run) $languages the fixer of a language, `null` when it has none
     */
    private function __construct(
        private array $protected,
        private Closure $fix,
        private ?Closure $languages = null,
        private string $language = '',
    ) {
    }

    /**
     * @param list<string>                           $protected the names of the elements whose text is never changed
     * @param Closure(Run): Run                      $fix       the fixer of the language of the root
     * @param ?Closure(string): (?Closure(Run): Run) $languages the fixer of another language, `null` when it has none
     */
    public static function fix(Node $root, array $protected, Closure $fix, ?Closure $languages = null, string $language = ''): void
    {
        $blocks = new self(array_fill_keys(array_map(strtolower(...), $protected), true), $fix, $languages, $language);
        $blocks->walk($root, $run = new Collector());
        $blocks->flush($run);
    }

    private function walk(Node $node, Collector $run): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof Text) {
                $run->add($child);
            } elseif ($child instanceof Element) {
                $this->element($child, $run);
            }
        }
    }

    private function element(Element $element, Collector $run): void
    {
        $name = $element->localName;

        $language = $element->getAttribute('lang');
        $fix = !$this->languages instanceof Closure || null === $language || $language === $this->language ? null : ($this->languages)($language);

        if (isset($this->protected[$name])) {
            $run->lock($element->textContent ?? '');
        } elseif (null !== $fix) {
            $this->flush($run);
            $passage = new self($this->protected, $fix, $this->languages, $language);
            $passage->walk($element, $inner = new Collector());
            $passage->flush($inner);
        } elseif (\in_array($name, self::BOUNDARIES, true)) {
            $this->flush($run);
            $this->walk($element, $run);
            $this->flush($run);
        } else {
            $this->walk($element, $run);
        }
    }

    private function flush(Collector $run): void
    {
        $nodes = $run->take();
        $texts = array_map(static fn (Text|string $part): string => $part instanceof Text ? $part->data : $part, $nodes);
        $fixed = ($this->fix)(new Run($texts, array_map(\is_string(...), $nodes)));

        foreach ($nodes as $index => $node) {
            if ($node instanceof Text) {
                $node->data = $fixed->texts[$index];
            }
        }
    }
}
