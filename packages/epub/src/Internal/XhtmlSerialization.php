<?php

declare(strict_types=1);

namespace Phil\Epub\Internal;

use Closure;
use Dom\Element;
use Dom\Text;

/**
 * One conversion of a body to XHTML, with what it finds on the way.
 *
 * @internal
 */
final class XhtmlSerialization
{
    public const string HTML = 'http://www.w3.org/1999/xhtml';

    private const string SVG = 'http://www.w3.org/2000/svg';

    private const string MATHML = 'http://www.w3.org/1998/Math/MathML';

    private const string XLINK = 'http://www.w3.org/1999/xlink';

    private const string XML = 'http://www.w3.org/XML/1998/namespace';

    private const array DROPPED = ['script', 'noscript', 'template', 'style', 'link', 'meta', 'title', 'base', 'object', 'embed'];

    /**
     * Dropped from SVG and MathML: scripts and styles, which foreign content keeps in its own namespace, and the SVG
     * animations, which could set an address such as a link's href to a script after the attributes were checked.
     */
    private const array FOREIGN_DROPPED = ['script', 'style', 'set', 'animate', 'animatemotion', 'animatetransform', 'discard', 'handler', 'listener'];

    private const array VOID = ['area', 'br', 'col', 'hr', 'img', 'input', 'source', 'track', 'wbr'];

    private const array OUTSIDE_TOC = ['aside', 'nav', 'blockquote', 'figure', 'details', 'table'];

    private const array MEDIA = ['audio', 'video', 'source', 'track', 'iframe'];

    /**
     * The level of each heading element.
     *
     * @var array<string, int>
     */
    public const array HEADINGS = ['h1' => 1, 'h2' => 2, 'h3' => 3, 'h4' => 4, 'h5' => 5, 'h6' => 6];

    private const string NAME = '/^[A-Za-z_][A-Za-z0-9._-]*$/D';

    private const string PREFIXED = '/^(xml|epub):[A-Za-z_][A-Za-z0-9._-]*$/D';

    /**
     * @var array<string, string>
     */
    private array $taken = [];

    /**
     * @var array<string, string>
     */
    private array $ids = [];

    /**
     * @var list<Heading>
     */
    private array $headings = [];

    /**
     * @var list<PageTarget>
     */
    private array $pages = [];

    private bool $svg = false;

    private bool $mathml = false;

    private bool $remote = false;

    private int $outsideToc = 0;

    private int $generated = 0;

    /**
     * @param Closure(string): ?string         $link
     * @param Closure(string, string): ?string $resource
     */
    public function __construct(private readonly Closure $link, private readonly Closure $resource, private readonly Element $body)
    {
        foreach ($body->querySelectorAll('[id]') as $element) {
            $id = $element->getAttribute('id') ?? '';
            $this->taken[$id] = $id;
        }
    }

    public function run(): Converted
    {
        $xhtml = $this->children($this->body);

        return new Converted($xhtml, array_keys($this->ids), $this->headings, $this->pages, $this->svg, $this->mathml, $this->remote);
    }

    private function children(Element $parent): string
    {
        $xhtml = '';

        foreach ($parent->childNodes as $node) {
            if ($node instanceof Text) {
                $xhtml .= self::text($node->data);
            } elseif ($node instanceof Element) {
                $xhtml .= $this->element($node, $parent->namespaceURI);
            }
        }

        return $xhtml;
    }

    private function element(Element $element, ?string $parentNamespace): string
    {
        $namespace = $element->namespaceURI;
        $name = $element->localName;

        if (self::HTML === $namespace ? \in_array($name, self::DROPPED, true) : \in_array(strtolower($name), self::FOREIGN_DROPPED, true)) {
            return '';
        }

        if (1 !== preg_match(self::NAME, $name)) {
            return $this->children($element);
        }

        $attributes = $this->declarations($element, $parentNamespace);

        foreach ($element->attributes as $attribute) {
            $qualified = match ($attribute->namespaceURI) {
                null => $attribute->localName,
                self::XML => 'xml:'.$attribute->localName,
                self::XLINK => self::SVG === $namespace ? 'xlink:'.$attribute->localName : null,
                default => null,
            };

            if (null === $qualified || 'id' === $qualified || (null === $attribute->namespaceURI && !$this->writable($qualified))) {
                continue;
            }

            $value = $attribute->value;

            switch ($this->addressKind($namespace, $name, $qualified)) {
                case 'link':
                    $value = ($this->link)($value);

                    break;
                case 'resource':
                    $value = ($this->resource)($value, $name);

                    if (null === $value && 'poster' !== $qualified) {
                        return 'img' === $name ? self::text($element->getAttribute('alt') ?? '') : '';
                    }

                    $this->remote = $this->remote || (null !== $value && 1 === preg_match('/^[a-z][a-z0-9+.-]*:/i', $value));

                    break;
                case 'fragment':
                    $value = str_starts_with($value, '#') ? $value : null;

                    break;
                case 'dropped':
                    $value = null;
            }

            if (null !== $value) {
                $attributes[] = [$qualified, $value];
            }
        }

        $id = $this->identify($element);

        if (null !== $id) {
            array_unshift($attributes, ['id', $id]);
        }

        $this->svg = $this->svg || self::SVG === $namespace;
        $this->mathml = $this->mathml || self::MATHML === $namespace;

        $open = '<'.$name;

        foreach ($attributes as [$attribute, $value]) {
            $open .= ' '.$attribute.'="'.self::attribute($value).'"';
        }

        if (self::HTML === $namespace && \in_array($name, self::VOID, true)) {
            return $open.'/>';
        }

        $outside = self::HTML === $namespace && \in_array($name, self::OUTSIDE_TOC, true);
        $this->outsideToc += $outside ? 1 : 0;
        $content = $this->children($element);
        $this->outsideToc -= $outside ? 1 : 0;

        if ('' === $content && self::HTML !== $namespace) {
            return $open.'/>';
        }

        return $open.'>'.$content.'</'.$name.'>';
    }

    /**
     * The namespace declarations of an SVG or MathML element that is the root of its tree.
     *
     * @return list<array{string, string}>
     */
    private function declarations(Element $element, ?string $parentNamespace): array
    {
        $namespace = $element->namespaceURI;

        if (self::HTML === $namespace || $parentNamespace === $namespace) {
            return [];
        }

        return self::SVG === $namespace ? [['xmlns', self::SVG], ['xmlns:xlink', self::XLINK]] : [['xmlns', self::MATHML]];
    }

    private function writable(string $name): bool
    {
        if (str_starts_with($name, 'on') || str_starts_with($name, 'xmlns')) {
            return false;
        }

        return 1 === preg_match(self::NAME, $name) || 1 === preg_match(self::PREFIXED, $name);
    }

    private function addressKind(?string $namespace, string $element, string $attribute): ?string
    {
        if (self::HTML === $namespace) {
            return match (true) {
                'href' === $attribute && \in_array($element, ['a', 'area'], true) => 'link',
                'src' === $attribute && ('img' === $element || \in_array($element, self::MEDIA, true)) => 'resource',
                'poster' === $attribute && 'video' === $element => 'resource',
                \in_array($attribute, ['srcset', 'sizes'], true) => 'dropped',
                default => null,
            };
        }

        if (\in_array($attribute, ['href', 'xlink:href'], true)) {
            return match ($element) {
                'a' => 'link',
                'image' => 'resource',
                default => 'fragment',
            };
        }

        return null;
    }

    /**
     * The identifier to write on the element: its own unless empty or already written, or one made for a heading of
     * the table of contents or a page break marker that has none.
     */
    private function identify(Element $element): ?string
    {
        $id = $element->getAttribute('id');

        if ('' === $id || isset($this->ids[$id ?? ''])) {
            $id = null;
        }

        $heading = $this->heading($element);
        $page = $this->pageLabel($element);

        if (null === $id && (null !== $heading || null !== $page)) {
            $id = $this->generate(null !== $heading ? 'heading' : 'page');
        }

        if (null === $id) {
            return null;
        }

        $this->ids[$id] = $id;

        if (null !== $heading) {
            $this->headings[] = new Heading($heading, $id, $this->collapse($element->textContent));
        }

        if (null !== $page) {
            $this->pages[] = new PageTarget($id, $page);
        }

        return $id;
    }

    /**
     * The level of a heading that belongs in the table of contents, or null.
     */
    private function heading(Element $element): ?int
    {
        if (self::HTML !== $element->namespaceURI || 0 !== $this->outsideToc || 'exclude' === $element->getAttribute('data-toc')) {
            return null;
        }

        $level = self::HEADINGS[$element->localName] ?? null;

        return null === $level || '' === $this->collapse($element->textContent) ? null : $level;
    }

    /**
     * The page label of a page break marker, or null.
     */
    private function pageLabel(Element $element): ?string
    {
        $roles = explode(' ', $element->getAttribute('role') ?? '');
        $types = explode(' ', $element->getAttribute('epub:type') ?? '');

        if (!\in_array('doc-pagebreak', $roles, true) && !\in_array('pagebreak', $types, true)) {
            return null;
        }

        $label = $this->collapse($element->getAttribute('aria-label') ?? $element->getAttribute('title') ?? $element->textContent);

        return '' === $label ? null : $label;
    }

    private function generate(string $prefix): string
    {
        do {
            $candidate = $prefix.'-'.++$this->generated;
        } while (isset($this->taken[$candidate]));

        $this->taken[$candidate] = $candidate;

        return $candidate;
    }

    private function collapse(?string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text ?? '') ?? '');
    }

    public static function text(string $text): string
    {
        return htmlspecialchars(self::clean($text), \ENT_XML1 | \ENT_QUOTES, 'UTF-8');
    }

    public static function attribute(string $value): string
    {
        return htmlspecialchars(self::clean($value), \ENT_XML1 | \ENT_QUOTES, 'UTF-8');
    }

    private static function clean(string $text): string
    {
        return preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '';
    }
}
