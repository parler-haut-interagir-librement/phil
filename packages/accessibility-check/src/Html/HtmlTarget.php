<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Html;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Text;
use Phil\AccessibilityCheck\Exception\InvalidContent;
use Phil\AccessibilityCheck\Exception\LimitExceeded;
use Phil\AccessibilityCheck\Location;
use Phil\AccessibilityCheck\Policy;
use WeakMap;

/**
 * An HTML content as the rules read it: parsed by the HTML parser of PHP, its elements indexed once in document
 * order, by name and by identifier, with what the rules ask of an element — where it is, its language, whether it is
 * hidden, its inline style, its accessible name.
 *
 * The content is refused before it is parsed when it is larger than the policy allows or is not UTF-8, and while it is
 * indexed when its elements nest too deep or are too many; the index is built without recursion. A target belongs to
 * one check and is never shared.
 */
final readonly class HtmlTarget
{
    private const string FULL_DOCUMENT = '/^(?:\xEF\xBB\xBF)?\s*(?:<!--.*?-->\s*)*<(?:!doctype|html)\b/is';

    /** The elements whose content is never shown. */
    private const array NEVER_SHOWN = ['head', 'script', 'style', 'template', 'noscript'];

    /** The elements laid out as blocks, whose text is apart from the text around it. */
    private const array BLOCKS = [
        'address', 'article', 'aside', 'blockquote', 'br', 'dd', 'details', 'div', 'dl', 'dt', 'figcaption', 'figure',
        'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'li', 'main', 'nav', 'ol', 'p', 'pre', 'section',
        'summary', 'table', 'td', 'th', 'tr', 'ul',
    ];

    /**
     * @param list<Element>                           $elements
     * @param array<string, list<Element>>            $byName
     * @param array<string, list<Element>>            $byId
     * @param WeakMap<Element, bool>                  $hidden
     * @param WeakMap<Element, array<string, string>> $styles
     */
    private function __construct(
        public HTMLDocument $document,
        public Policy $policy,
        public bool $isFullDocument,
        public ?string $language,
        private ?Location $origin,
        private array $elements,
        private array $byName,
        private array $byId,
        private WeakMap $hidden,
        private WeakMap $styles,
    ) {
    }

    /**
     * Parses and indexes an HTML content, a whole document or a fragment.
     *
     * @param ?string   $language the language of the content when it does not say its own
     * @param ?Location $origin   the UJO block the HTML is a field of; `null` for an HTML content
     *
     * @throws LimitExceeded  when the content is larger, deeper or holds more elements than the policy allows
     * @throws InvalidContent when it is not UTF-8
     */
    public static function parse(string $html, Policy $policy, ?string $language = null, ?Location $origin = null): self
    {
        if (\strlen($html) > $policy->maxBytes) {
            throw LimitExceeded::size(\strlen($html), $policy->maxBytes);
        }

        if (!mb_check_encoding($html, 'UTF-8')) {
            throw InvalidContent::notUtf8();
        }

        $document = HTMLDocument::createFromString($html, \LIBXML_NOERROR, 'UTF-8');
        $elements = [];
        $byName = [];
        $byId = [];
        /** @var WeakMap<Element, bool> $hidden */
        $hidden = new WeakMap();
        /** @var WeakMap<Element, array<string, string>> $styles */
        $styles = new WeakMap();
        /** @var list<array{Element, int, bool}> $stack */
        $stack = [];

        for ($child = $document->lastElementChild; $child instanceof Element; $child = $child->previousElementSibling) {
            $stack[] = [$child, 1, false];
        }

        while ([] !== $stack) {
            [$element, $depth, $parentHidden] = array_pop($stack);

            if ($depth > $policy->maxDepth) {
                throw LimitExceeded::depth($policy->maxDepth);
            }

            $elements[] = $element;

            if (\count($elements) > $policy->maxElements) {
                throw LimitExceeded::count($policy->maxElements);
            }

            $name = $element->localName;
            $byName[$name][] = $element;
            $id = $element->getAttribute('id');

            if (null !== $id && '' !== $id) {
                $byId[$id][] = $element;
            }

            $style = $styles[$element] = self::declarations($element->getAttribute('style'));
            $isHidden = $hidden[$element] = $parentHidden || self::hides($element, $name, $style);

            for ($child = $element->lastElementChild; null !== $child; $child = $child->previousElementSibling) {
                $stack[] = [$child, $depth + 1, $isHidden];
            }
        }

        return new self($document, $policy, 1 === preg_match(self::FULL_DOCUMENT, $html), $language, $origin, $elements, $byName, $byId, $hidden, $styles);
    }

    /**
     * The elements of these names, name after name, each in document order; every element, in document order, without a
     * name.
     *
     * @return list<Element>
     */
    public function elements(string ...$names): array
    {
        if ([] === $names) {
            return $this->elements;
        }

        $elements = [];

        foreach ($names as $name) {
            array_push($elements, ...$this->byName[$name] ?? []);
        }

        return $elements;
    }

    /**
     * The elements of each identifier, in document order.
     *
     * @return array<string, list<Element>>
     */
    public function identifiers(): array
    {
        return $this->byId;
    }

    /**
     * Whether the HTML is a field of a UJO block, rather than an HTML content of its own.
     */
    public function isUjoField(): bool
    {
        return $this->origin instanceof Location;
    }

    /**
     * Where the element is: its line and path in an HTML content, the block and field it belongs to and its path in
     * a UJO field.
     */
    public function locate(Element $element): Location
    {
        $path = $this->path($element);
        $snippet = $this->snippet($element);

        if (!$this->origin instanceof Location) {
            return new Location($element->getLineNo() + 1, $path, $snippet);
        }

        return new Location(null, $path, $snippet, $this->origin->block, $this->origin->blockType, $this->origin->pointer);
    }

    /**
     * The language of the element: the `lang` of the nearest element that has one, else the language of the content;
     * `null` when it is unknown, `lang=""` included.
     */
    public function languageOf(Element $element): ?string
    {
        for ($node = $element; $node instanceof Element; $node = $node->parentElement) {
            $lang = $node->getAttribute('lang');

            if (null !== $lang) {
                return '' === trim($lang) ? null : trim($lang);
            }
        }

        return $this->language;
    }

    /**
     * Whether nobody perceives the element: it or an ancestor is `hidden`, `aria-hidden="true"`, `display: none` or
     * `visibility: hidden` inline, or never shown, such as `script`.
     */
    public function isHidden(Element $element): bool
    {
        return $this->hidden[$element];
    }

    /**
     * The declarations of the element's `style`, by property in lower case, their values trimmed and in lower case.
     *
     * @return array<string, string>
     */
    public function style(Element $element): array
    {
        return $this->styles[$element];
    }

    /**
     * Whether the element plays one of these ARIA roles, read from its `role`, whose first known token counts.
     */
    public function hasRole(Element $element, string ...$roles): bool
    {
        $role = $element->getAttribute('role');

        return null !== $role && \in_array(strtolower(explode(' ', self::collapse($role))[0]), $roles, true);
    }

    /**
     * The name a screen reader announces for the element, computed as the accessible name of WAI-ARIA, simplified:
     * the text of the elements `aria-labelledby` names, else `aria-label`, else the text alternative of an image or the
     * value of a button input, else the text of its content, else its `title`. Spaces are collapsed.
     */
    public function accessibleName(Element $element): string
    {
        $name = $this->labelOf($element);

        if ('' === $name) {
            $name = self::collapse($this->ownName($element) ?? $this->textOf($element));
        }

        return '' === $name ? self::collapse($element->getAttribute('title') ?? '') : $name;
    }

    /**
     * The name the element is given by ARIA: the text of the elements `aria-labelledby` names, else its `aria-label`;
     * `''` without either. Spaces are collapsed.
     */
    public function labelOf(Element $element): string
    {
        $labelledBy = [];

        foreach (explode(' ', self::collapse($element->getAttribute('aria-labelledby') ?? '')) as $id) {
            $labelledBy[] = isset($this->byId[$id]) ? $this->textOf($this->byId[$id][0]) : '';
        }

        $name = self::collapse(implode(' ', $labelledBy));

        return '' === $name ? self::collapse($element->getAttribute('aria-label') ?? '') : $name;
    }

    /**
     * The text of the element's content as a screen reader reads it: its text, the `aria-label` of a descendant in
     * place of its content, the text alternative of its images and the title of its drawings, its hidden descendants
     * left out. The text of a block, an image, a drawing or an input stands apart from its neighbours; an inline element
     * joins them, as `<b>` does in `re<b>ad</b>`. Spaces are collapsed.
     */
    public function textOf(Element $element): string
    {
        $text = '';

        foreach ($element->childNodes as $child) {
            if ($child instanceof Text) {
                $text .= $child->data;
            } elseif ($child instanceof Element && !$this->isHidden($child)) {
                $label = self::collapse($child->getAttribute('aria-label') ?? '');
                $own = $this->ownName($child);
                $apart = null !== $own || \in_array($child->localName, self::BLOCKS, true) ? ' ' : '';
                $text .= $apart.('' !== $label ? $label : ($own ?? $this->textOf($child))).$apart;
            }
        }

        return self::collapse($text);
    }

    /**
     * The text of the element's own text nodes, without its descendants', spaces collapsed.
     */
    public function ownText(Element $element): string
    {
        $text = '';

        foreach ($element->childNodes as $child) {
            if ($child instanceof Text) {
                $text .= $child->data;
            }
        }

        return self::collapse($text);
    }

    /**
     * @param array<string, string> $style
     */
    private static function hides(Element $element, string $name, array $style): bool
    {
        return $element->hasAttribute('hidden')
            || 'true' === strtolower(trim($element->getAttribute('aria-hidden') ?? ''))
            || 'none' === ($style['display'] ?? null)
            || \in_array($style['visibility'] ?? null, ['hidden', 'collapse'], true)
            || \in_array($name, self::NEVER_SHOWN, true);
    }

    /**
     * @return array<string, string>
     */
    private static function declarations(?string $style): array
    {
        $declarations = [];

        foreach (explode(';', $style ?? '') as $declaration) {
            $colon = strpos($declaration, ':');

            if (false !== $colon) {
                $declarations[strtolower(trim(substr($declaration, 0, $colon)))] = strtolower(trim(substr($declaration, $colon + 1)));
            }
        }

        return $declarations;
    }

    public static function collapse(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * The name an element takes from an attribute or a child rather than from its content: the `alt` of an image, the
     * `title` of a drawing, the value of a button input; `null` for the others.
     */
    private function ownName(Element $element): ?string
    {
        $name = $element->localName;

        if ('img' === $name || 'area' === $name) {
            return $element->getAttribute('alt') ?? '';
        }

        if ('svg' === $name) {
            return ($title = $this->svgTitle($element)) instanceof Element ? $this->textOf($title) : '';
        }

        if ('input' !== $name) {
            return null;
        }

        return match (strtolower($element->getAttribute('type') ?? 'text')) {
            'image' => $element->getAttribute('alt') ?? '',
            'submit' => $element->getAttribute('value') ?? 'Submit',
            'reset' => $element->getAttribute('value') ?? 'Reset',
            default => $element->getAttribute('value') ?? '',
        };
    }

    /**
     * The `title` child of a drawing, which names it.
     */
    public function svgTitle(Element $svg): ?Element
    {
        for ($child = $svg->firstElementChild; $child instanceof Element; $child = $child->nextElementSibling) {
            if ('title' === $child->localName) {
                return $child;
            }
        }

        return null;
    }

    /**
     * The path of the element from the body, such as `div:nth-of-type(2) > p > a`; the position is written when the
     * parent holds more than one element of the name.
     */
    private function path(Element $element): string
    {
        $segments = [];

        for ($node = $element; $node instanceof Element && !\in_array($name = $node->localName, ['html', 'body', 'head'], true); $node = $node->parentElement) {
            $segments[] = $name.$this->position($node, $name);
        }

        return [] === $segments ? $element->localName : implode(' > ', array_reverse($segments));
    }

    private function position(Element $element, string $name): string
    {
        $before = 0;
        $later = false;

        for ($sibling = $element->previousElementSibling; $sibling instanceof Element; $sibling = $sibling->previousElementSibling) {
            $before += $name === $sibling->localName ? 1 : 0;
        }

        for ($sibling = $element->nextElementSibling; $sibling instanceof Element; $sibling = $sibling->nextElementSibling) {
            $later = $later || $name === $sibling->localName;
        }

        return 0 === $before && !$later ? '' : ':nth-of-type('.($before + 1).')';
    }

    /**
     * The opening tag of the element, each value cut to 60 characters and the whole to 160.
     */
    private function snippet(Element $element): string
    {
        $tag = '<'.$element->localName;

        foreach ($element->attributes as $attribute) {
            $tag .= ' '.$attribute->name.'="'.htmlspecialchars(mb_strimwidth($attribute->value, 0, 60, '…'), \ENT_QUOTES | \ENT_HTML5).'"';
        }

        return mb_strimwidth($tag.'>', 0, 160, '…');
    }
}
