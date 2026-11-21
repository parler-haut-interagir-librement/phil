<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Content;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;
use Override;
use Symfony\Component\Mime\HtmlToTextConverter\HtmlToTextConverterInterface;

/**
 * Writes the text part of a message from its HTML, keeping what a reader of the text needs to act: the address of
 * every web link, written between angle brackets after its words — the unsubscription, the preferences and the
 * confirmation among them —, the alternative text of an image, a dash before a list item, a blank line between
 * blocks. What a reader does not see is left out: the head, the styles, the scripts, and an element marked `hidden`
 * or styled `display: none`, as the preheader.
 *
 * The HTML is read by PHP's HTML5 parser, as a browser reads it; entities come out decoded.
 */
final class TextPart implements HtmlToTextConverterInterface
{
    private const array SKIPPED = ['head', 'title', 'style', 'script', 'template', 'noscript', 'svg', 'math'];

    private const array BLOCKS = ['address', 'article', 'aside', 'blockquote', 'center', 'dd', 'div', 'dl', 'dt', 'figcaption', 'figure', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'main', 'nav', 'ol', 'p', 'pre', 'section', 'table', 'tr', 'ul'];

    private const string HIDDEN = '/(?:^|;)\s*display\s*:\s*none\b/i';

    #[Override]
    public function convert(string $html, string $charset): string
    {
        if ('' === trim($html)) {
            return '';
        }

        $document = HTMLDocument::createFromString($html, \LIBXML_NOERROR, $charset);
        $text = $this->children($document->body ?? $document);
        $lines = array_map(static fn (string $line): string => trim($line, " \t"), explode("\n", $text));

        return trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
    }

    private function children(Node $node): string
    {
        $text = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof Text) {
                $text .= (string) preg_replace('/[ \t\r\n\f]+/', ' ', $child->data);
            } elseif ($child instanceof Element) {
                $text .= $this->element($child);
            }
        }

        return $text;
    }

    private function element(Element $element): string
    {
        $name = strtolower($element->localName);

        if (\in_array($name, self::SKIPPED, true) || $element->hasAttribute('hidden') || 1 === preg_match(self::HIDDEN, $element->getAttribute('style') ?? '')) {
            return '';
        }

        return match (true) {
            'br' === $name => "\n",
            'img' === $name => trim($element->getAttribute('alt') ?? ''),
            'a' === $name => $this->link($element),
            'li' === $name => "\n- ".trim($this->children($element)),
            'td' === $name, 'th' === $name => $this->children($element).' ',
            \in_array($name, self::BLOCKS, true) => "\n\n".$this->children($element)."\n\n",
            default => $this->children($element),
        };
    }

    /**
     * The words of a link, then its address when it leads to a page; the address alone when the words are the address
     * or there are none.
     */
    private function link(Element $element): string
    {
        $words = trim($this->children($element));
        $href = trim($element->getAttribute('href') ?? '');

        if (1 !== preg_match('~^https?://\S+$~i', $href) || $words === $href) {
            return $words;
        }

        return '' === $words ? $href : $words.' <'.$href.'>';
    }
}
