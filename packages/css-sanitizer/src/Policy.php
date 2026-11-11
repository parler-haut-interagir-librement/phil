<?php

declare(strict_types=1);

namespace Phil\CssSanitizer;

use Phil\CssSanitizer\Exception\InvalidPolicy;
use Phil\CssSanitizer\Url\RefuseUrls;

/**
 * What a style sheet may hold: its properties, functions, selectors and at-rules, how its URLs are rewritten, and the
 * limits on its size.
 *
 * `new Policy()` allows what a page laid out for the screen or for print needs — box, text, colour, background,
 * columns, flex and grid, fragmentation and generated content — and refuses what can cover the application's own page
 * (`position`, `z-index`), what reacts to the reader (`cursor`, transitions, animations) and every URL, until a
 * rewriter is given. Each `with…()` returns a new policy. Names are compared in ASCII lower case.
 *
 * Some things are never allowed, whatever the policy says: the properties `behavior`, `-ms-behavior` and
 * `-moz-binding`, which run code, and the functions that load a resource from a string — `expression()`, `image()`,
 * `cross-fade()`, `element()`, `src()` — or whose URLs only the rewriter may write — `url()` and `image-set()`. Asking
 * for them throws `InvalidPolicy`. `@import` is refused until the policy allows `AtRuleKind::Import`, and even then
 * loads only the style sheets the rewriter allows.
 */
final readonly class Policy
{
    /** The properties that run code or bind behaviour; never allowed. */
    public const array DENIED_PROPERTIES = ['behavior', '-ms-behavior', '-moz-binding'];

    /** The functions that run code or load a resource from a string; never allowed as functions of a value. */
    public const array DENIED_FUNCTIONS = [
        'expression', 'url', 'src', 'image', 'image-set', '-webkit-image-set', 'cross-fade', '-webkit-cross-fade',
        'element', '-moz-element', 'paint',
    ];

    public const array DEFAULT_PROPERTIES = [
        // Box.
        'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'margin-block', 'margin-block-start',
        'margin-block-end', 'margin-inline', 'margin-inline-start', 'margin-inline-end',
        'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left', 'padding-block',
        'padding-block-start', 'padding-block-end', 'padding-inline', 'padding-inline-start', 'padding-inline-end',
        'border', 'border-top', 'border-right', 'border-bottom', 'border-left', 'border-width', 'border-style',
        'border-color', 'border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width',
        'border-top-style', 'border-right-style', 'border-bottom-style', 'border-left-style', 'border-top-color',
        'border-right-color', 'border-bottom-color', 'border-left-color', 'border-block', 'border-block-start',
        'border-block-end', 'border-inline', 'border-inline-start', 'border-inline-end', 'border-radius',
        'border-top-left-radius', 'border-top-right-radius', 'border-bottom-right-radius', 'border-bottom-left-radius',
        'border-collapse', 'border-spacing', 'box-sizing', 'box-shadow', 'box-decoration-break', 'outline',
        'outline-color', 'outline-style', 'outline-width', 'outline-offset',
        // Size.
        'width', 'height', 'min-width', 'max-width', 'min-height', 'max-height', 'inline-size', 'block-size',
        'min-inline-size', 'max-inline-size', 'min-block-size', 'max-block-size', 'aspect-ratio',
        // Layout.
        'display', 'float', 'clear', 'overflow', 'overflow-x', 'overflow-y', 'visibility', 'vertical-align',
        'object-fit', 'object-position', 'flex', 'flex-basis', 'flex-direction', 'flex-flow', 'flex-grow',
        'flex-shrink', 'flex-wrap', 'order', 'justify-content', 'justify-items', 'justify-self', 'align-content',
        'align-items', 'align-self', 'place-content', 'place-items', 'place-self', 'gap', 'row-gap', 'column-gap',
        'grid', 'grid-area', 'grid-auto-columns', 'grid-auto-flow', 'grid-auto-rows', 'grid-column',
        'grid-column-start', 'grid-column-end', 'grid-row', 'grid-row-start', 'grid-row-end', 'grid-template',
        'grid-template-areas', 'grid-template-columns', 'grid-template-rows',
        // Columns and fragmentation.
        'columns', 'column-count', 'column-width', 'column-fill', 'column-rule', 'column-rule-color',
        'column-rule-style', 'column-rule-width', 'column-span', 'break-before', 'break-after', 'break-inside',
        'page-break-before', 'page-break-after', 'page-break-inside', 'orphans', 'widows', 'page',
        // Generated content and paged media.
        'content', 'quotes', 'counter-reset', 'counter-increment', 'counter-set', 'list-style', 'list-style-type',
        'list-style-position', 'list-style-image', 'string-set', 'bookmark-label', 'bookmark-level', 'bookmark-state',
        'footnote-display', 'footnote-policy',
        // Text.
        'color', 'font', 'font-family', 'font-size', 'font-style', 'font-weight', 'font-variant', 'font-variant-caps',
        'font-variant-numeric', 'font-variant-ligatures', 'font-variant-east-asian', 'font-stretch', 'font-kerning',
        'font-feature-settings', 'font-variation-settings', 'font-optical-sizing', 'font-size-adjust',
        'font-synthesis', 'line-height', 'letter-spacing', 'word-spacing', 'text-align', 'text-align-last',
        'text-indent', 'text-transform', 'text-decoration', 'text-decoration-line', 'text-decoration-color',
        'text-decoration-style', 'text-decoration-thickness', 'text-underline-offset', 'text-shadow', 'text-overflow',
        'text-wrap', 'white-space', 'word-break', 'overflow-wrap', 'hyphens', 'hyphenate-character',
        'hyphenate-limit-chars', 'hanging-punctuation', 'direction', 'unicode-bidi', 'writing-mode',
        'text-orientation', 'tab-size',
        // Background and effects.
        'background', 'background-color', 'background-image', 'background-position', 'background-size',
        'background-repeat', 'background-origin', 'background-clip', 'background-blend-mode', 'opacity', 'filter',
        'mix-blend-mode', 'transform', 'transform-origin', 'rotate', 'scale', 'translate', 'clip-path',
        'print-color-adjust', '-webkit-print-color-adjust',
        // Tables.
        'table-layout', 'caption-side', 'empty-cells',
    ];

    public const array DEFAULT_FUNCTIONS = [
        // Colours.
        'rgb', 'rgba', 'hsl', 'hsla', 'hwb', 'lab', 'lch', 'oklab', 'oklch', 'color', 'color-mix', 'light-dark',
        // Mathematics and grids.
        'calc', 'min', 'max', 'clamp', 'round', 'mod', 'rem', 'abs', 'sign', 'repeat', 'minmax', 'fit-content',
        // Gradients.
        'linear-gradient', 'radial-gradient', 'conic-gradient', 'repeating-linear-gradient',
        'repeating-radial-gradient', 'repeating-conic-gradient',
        // Generated content and paged media.
        'counter', 'counters', 'attr', 'string', 'content', 'leader', 'target-counter', 'target-counters', 'target-text',
        // Transforms.
        'translate', 'translatex', 'translatey', 'rotate', 'scale', 'scalex', 'scaley', 'skew', 'skewx', 'skewy',
        'matrix',
        // Filters.
        'blur', 'brightness', 'contrast', 'drop-shadow', 'grayscale', 'hue-rotate', 'invert', 'opacity', 'saturate',
        'sepia',
    ];

    public const array DEFAULT_PSEUDO_CLASSES = [
        'root', 'empty', 'first-child', 'last-child', 'only-child', 'first-of-type', 'last-of-type', 'only-of-type',
        'nth-child', 'nth-last-child', 'nth-of-type', 'nth-last-of-type', 'not', 'is', 'where', 'lang', 'dir', 'link',
        'hover', 'focus', 'focus-visible', 'focus-within', 'active', 'target',
    ];

    public const array DEFAULT_PSEUDO_ELEMENTS = [
        'before', 'after', 'first-letter', 'first-line', 'marker', 'selection', 'footnote-call', 'footnote-marker',
    ];

    public const array DEFAULT_ATTRIBUTES = ['lang', 'dir'];

    public const array DEFAULT_MEDIA_TYPES = ['all', 'print', 'screen'];

    /** @var array<string, true> */
    public array $properties;

    /** @var array<string, true> */
    public array $functions;

    /** @var array<string, true> */
    public array $pseudoClasses;

    /** @var array<string, true> */
    public array $pseudoElements;

    /** @var array<string, true> attribute names; one that ends with `*` allows every name that starts with it */
    public array $attributes;

    /** @var array<string, true> */
    public array $mediaTypes;

    /** @var array<string, true> the values of the allowed `AtRuleKind`s */
    public array $atRules;

    public function __construct(
        public UrlRewriter $urlRewriter = new RefuseUrls(),
        public ?string $scope = null,
        public bool $important = true,
        public bool $customProperties = false,
        public int $maxLength = 1_048_576,
        public int $maxDepth = 32,
        public int $maxNesting = 3,
    ) {
        if ($maxLength < 1) {
            throw InvalidPolicy::limit('maximum length', $maxLength);
        }

        if ($maxDepth < 1) {
            throw InvalidPolicy::limit('maximum depth', $maxDepth);
        }

        if ($maxNesting < 0) {
            throw InvalidPolicy::negative('maximum nesting', $maxNesting);
        }

        $this->properties = $this->set(self::DEFAULT_PROPERTIES);
        $this->functions = $this->set(self::DEFAULT_FUNCTIONS);
        $this->pseudoClasses = $this->set(self::DEFAULT_PSEUDO_CLASSES);
        $this->pseudoElements = $this->set(self::DEFAULT_PSEUDO_ELEMENTS);
        $this->attributes = $this->set(self::DEFAULT_ATTRIBUTES);
        $this->mediaTypes = $this->set(self::DEFAULT_MEDIA_TYPES);
        $this->atRules = $this->kinds(array_filter(AtRuleKind::cases(), static fn (AtRuleKind $kind): bool => AtRuleKind::Import !== $kind));
    }

    public function withProperties(string ...$properties): self
    {
        foreach ($properties as $property) {
            if (\in_array(strtolower($property), self::DENIED_PROPERTIES, true)) {
                throw InvalidPolicy::deniedProperty($property);
            }
        }

        return clone ($this, ['properties' => $this->properties + $this->set($properties)]);
    }

    public function withoutProperties(string ...$properties): self
    {
        return clone ($this, ['properties' => array_diff_key($this->properties, $this->set($properties))]);
    }

    public function withFunctions(string ...$functions): self
    {
        foreach ($functions as $function) {
            if (\in_array(strtolower($function), self::DENIED_FUNCTIONS, true)) {
                throw InvalidPolicy::deniedFunction($function);
            }
        }

        return clone ($this, ['functions' => $this->functions + $this->set($functions)]);
    }

    public function withoutFunctions(string ...$functions): self
    {
        return clone ($this, ['functions' => array_diff_key($this->functions, $this->set($functions))]);
    }

    public function withPseudoClasses(string ...$names): self
    {
        return clone ($this, ['pseudoClasses' => $this->pseudoClasses + $this->set($names)]);
    }

    public function withoutPseudoClasses(string ...$names): self
    {
        return clone ($this, ['pseudoClasses' => array_diff_key($this->pseudoClasses, $this->set($names))]);
    }

    public function withPseudoElements(string ...$names): self
    {
        return clone ($this, ['pseudoElements' => $this->pseudoElements + $this->set($names)]);
    }

    public function withoutPseudoElements(string ...$names): self
    {
        return clone ($this, ['pseudoElements' => array_diff_key($this->pseudoElements, $this->set($names))]);
    }

    /** Attribute names that attribute selectors may test; `data-*` allows every `data-` attribute. */
    public function withAttributes(string ...$names): self
    {
        return clone ($this, ['attributes' => $this->attributes + $this->set($names)]);
    }

    public function withoutAttributes(string ...$names): self
    {
        return clone ($this, ['attributes' => array_diff_key($this->attributes, $this->set($names))]);
    }

    /** The media types `@media` may name, in place of the current ones. */
    public function withMediaTypes(string ...$types): self
    {
        return clone ($this, ['mediaTypes' => $this->set($types)]);
    }

    public function withAtRules(AtRuleKind ...$kinds): self
    {
        return clone ($this, ['atRules' => $this->atRules + $this->kinds($kinds)]);
    }

    public function withoutAtRules(AtRuleKind ...$kinds): self
    {
        return clone ($this, ['atRules' => array_diff_key($this->atRules, $this->kinds($kinds))]);
    }

    public function withUrlRewriter(UrlRewriter $rewriter): self
    {
        return clone ($this, ['urlRewriter' => $rewriter]);
    }

    /**
     * A selector every style rule is placed under — `.user-styles` turns `p` into `.user-styles p` —, or `null` for
     * none. It is the application's, and is written as given.
     */
    public function withScope(?string $scope): self
    {
        return clone ($this, ['scope' => $scope]);
    }

    /** Whether `!important` is kept; when it is not, declarations lose the flag and stay. */
    public function withImportant(bool $important): self
    {
        return clone ($this, ['important' => $important]);
    }

    /** Whether custom properties (`--name`) and `var()` are allowed; their values are checked as any other. */
    public function withCustomProperties(bool $allowed): self
    {
        return clone ($this, ['customProperties' => $allowed]);
    }

    /** The longest source accepted, in bytes; a longer one is refused whole. */
    public function withMaxLength(int $bytes): self
    {
        if ($bytes < 1) {
            throw InvalidPolicy::limit('maximum length', $bytes);
        }

        return clone ($this, ['maxLength' => $bytes]);
    }

    /** The deepest nesting of blocks and functions accepted; a source nested deeper is refused whole. */
    public function withMaxDepth(int $depth): self
    {
        if ($depth < 1) {
            throw InvalidPolicy::limit('maximum depth', $depth);
        }

        return clone ($this, ['maxDepth' => $depth]);
    }

    /**
     * How many levels of style rules may nest inside a style rule, CSS Nesting's way, `@media` and `@supports` among
     * them; 0 removes every nested rule.
     */
    public function withMaxNesting(int $levels): self
    {
        if ($levels < 0) {
            throw InvalidPolicy::negative('maximum nesting', $levels);
        }

        return clone ($this, ['maxNesting' => $levels]);
    }

    public function allowsAtRule(AtRuleKind $kind): bool
    {
        return isset($this->atRules[$kind->value]);
    }

    public function allowsAttribute(string $name): bool
    {
        $name = strtolower($name);

        if (isset($this->attributes[$name])) {
            return true;
        }

        return array_any(array_keys($this->attributes), static fn (string $allowed): bool => str_ends_with($allowed, '*') && str_starts_with($name, substr($allowed, 0, -1)));
    }

    /**
     * @param array<string> $names
     *
     * @return array<string, true>
     */
    private function set(array $names): array
    {
        return array_fill_keys(array_map(strtolower(...), $names), true);
    }

    /**
     * @param array<AtRuleKind> $kinds
     *
     * @return array<string, true>
     */
    private function kinds(array $kinds): array
    {
        return $this->set(array_map(static fn (AtRuleKind $kind): string => $kind->value, $kinds));
    }
}
