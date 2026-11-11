<?php

declare(strict_types=1);

namespace Phil\Ujo\Html;

/**
 * The elements a field may hold, as an ordered list of inline tools: `rich()` for the text of a block, `plain()` for
 * none, `mathMl()` for a formula, `raw()` for the HTML of a `raw` block.
 */
final readonly class InlineProfile
{
    /**
     * The elements of MathML Core a formula may hold, each with the attributes it keeps beside the common ones.
     *
     * @var array<string, array<string, AttributeValue>>
     */
    private const array MATHML = [
        'mrow' => [], 'mi' => [], 'mn' => [], 'mtext' => [], 'ms' => [], 'merror' => [], 'mphantom' => [],
        'mo' => ['form' => AttributeValue::Keyword, 'fence' => AttributeValue::Keyword, 'separator' => AttributeValue::Keyword, 'lspace' => AttributeValue::Keyword, 'rspace' => AttributeValue::Keyword, 'stretchy' => AttributeValue::Keyword, 'symmetric' => AttributeValue::Keyword, 'maxsize' => AttributeValue::Keyword, 'minsize' => AttributeValue::Keyword, 'largeop' => AttributeValue::Keyword, 'movablelimits' => AttributeValue::Keyword],
        'mfrac' => ['linethickness' => AttributeValue::Keyword],
        'msqrt' => [], 'mroot' => [], 'mstyle' => [],
        'mspace' => ['width' => AttributeValue::Keyword, 'height' => AttributeValue::Keyword, 'depth' => AttributeValue::Keyword],
        'mpadded' => ['width' => AttributeValue::Keyword, 'height' => AttributeValue::Keyword, 'depth' => AttributeValue::Keyword, 'lspace' => AttributeValue::Keyword, 'voffset' => AttributeValue::Keyword],
        'msub' => [], 'msup' => [], 'msubsup' => [],
        'munder' => ['accentunder' => AttributeValue::Keyword], 'mover' => ['accent' => AttributeValue::Keyword], 'munderover' => ['accent' => AttributeValue::Keyword, 'accentunder' => AttributeValue::Keyword],
        'mmultiscripts' => [], 'mprescripts' => [], 'none' => [],
        'mtable' => [], 'mtr' => [], 'mtd' => ['columnspan' => AttributeValue::Number, 'rowspan' => AttributeValue::Number],
        'semantics' => [], 'annotation' => ['encoding' => AttributeValue::Text],
    ];

    /** The attributes every element of MathML may keep. */
    private const array MATHML_COMMON = ['dir' => AttributeValue::Direction, 'displaystyle' => AttributeValue::Keyword, 'scriptlevel' => AttributeValue::Keyword, 'mathvariant' => AttributeValue::Keyword];

    /** @var array<string, list<InlineTool>> the tools that read each tag, in their order */
    private array $byTag;

    /**
     * @param list<InlineTool> $tools
     */
    public function __construct(
        public array $tools = [],
    ) {
        $byTag = [];

        foreach ($tools as $tool) {
            foreach ($tool->tags() as $tag) {
                $byTag[$tag][] = $tool;
            }
        }

        $this->byTag = $byTag;
    }

    /**
     * The inline tools of Editor.js and of the community, as PHIL writes them: bold `<b>`, italic `<i>`, underline
     * `<u class="cdx-underline">`, strikethrough `<s class="cdx-strikethrough">`, marker `<mark class="cdx-marker">`,
     * inline code `<code class="inline-code">`, links with `target` and `rel`, mentions `<a data-mention>`, wiki links
     * `<a data-ref>`, exponents, footnote references `<sup data-footnote>`, line breaks, text colour by token, tooltip,
     * annotation, spoiler, icon `<i data-icon>`, horizontal space `<span data-hspace>`, abbreviations `<abbr title>`,
     * passages in another language `<span lang dir>` — `<bdi>` and `<bdo>` read as one —, ruby `<ruby>` with its
     * reading `<rt>` and fallback `<rp>`, and formulas in MathML `<math>`.
     */
    public static function rich(): self
    {
        return new self([
            new InlineTool('bold', 'b', ['b', 'strong']),
            new InlineTool('icon', 'i', ['i[data-icon]'], null, ['data-icon' => AttributeValue::Reference]),
            new InlineTool('italic', 'i', ['i', 'em']),
            new InlineTool('underline', 'u', ['u'], 'cdx-underline'),
            new InlineTool('strikethrough', 's', ['s', 'strike', 'del'], 'cdx-strikethrough'),
            new InlineTool('marker', 'mark', ['mark'], 'cdx-marker'),
            new InlineTool('inlineCode', 'code', ['code', 'span.inline-code'], 'inline-code'),
            new InlineTool('mention', 'a', ['a[data-mention]'], null, ['href' => AttributeValue::Link, 'data-mention' => AttributeValue::Reference], ['data-mention']),
            new InlineTool('wikiLink', 'a', ['a[data-ref]'], null, ['href' => AttributeValue::Link, 'data-ref' => AttributeValue::Reference], ['data-ref']),
            new InlineTool('link', 'a', ['a'], null, ['href' => AttributeValue::Link, 'target' => AttributeValue::Target, 'rel' => AttributeValue::Rel], ['href']),
            new InlineTool('footnoteReference', 'sup', ['sup[data-footnote]'], null, ['data-footnote' => AttributeValue::Token]),
            new InlineTool('superscript', 'sup', ['sup']),
            new InlineTool('subscript', 'sub', ['sub']),
            new InlineTool('lineBreak', 'br', ['br']),
            new InlineTool('textColor', 'span', ['span.text-color'], 'text-color', ['data-color' => AttributeValue::Token], ['data-color']),
            new InlineTool('tooltip', 'span', ['span.cdx-tooltip'], 'cdx-tooltip', ['data-tooltip' => AttributeValue::Text], ['data-tooltip']),
            new InlineTool('annotation', 'span', ['span.cdx-annotation'], 'cdx-annotation', ['data-title' => AttributeValue::Text, 'data-text' => AttributeValue::Text]),
            new InlineTool('spoiler', 'span', ['span.spoiler'], 'spoiler'),
            new InlineTool('horizontalSpace', 'span', ['span[data-hspace]'], null, ['data-hspace' => AttributeValue::Length], ['data-hspace']),
            new InlineTool('abbreviation', 'abbr', ['abbr'], null, ['title' => AttributeValue::Text], ['title']),
            new InlineTool('language', 'span', ['span[lang]', 'span[dir]', 'bdi', 'bdo'], null, ['lang' => AttributeValue::Language, 'dir' => AttributeValue::Direction], [], [], ['lang', 'dir']),
            new InlineTool('ruby', 'ruby', ['ruby']),
            new InlineTool('rubyText', 'rt', ['rt'], null, [], [], ['ruby']),
            new InlineTool('rubyParenthesis', 'rp', ['rp'], null, [], [], ['ruby']),
            ...self::mathMl()->tools,
        ]);
    }

    /**
     * A formula of MathML Core, `<math>` and the elements it holds — tokens, rows, fractions, roots, scripts, limits,
     * tables, spaces and annotations —, each kept only inside a formula; no link, no style, no class, no colour.
     */
    public static function mathMl(): self
    {
        $tools = [new InlineTool('inlineMath', 'math', ['math'], null, ['display' => AttributeValue::Keyword, ...self::MATHML_COMMON])];

        foreach (self::MATHML as $tag => $attributes) {
            $tools[] = new InlineTool('mathml-'.$tag, $tag, [$tag], null, [...$attributes, ...self::MATHML_COMMON], [], ['math']);
        }

        return new self($tools);
    }

    /**
     * No element: the text alone.
     */
    public static function plain(): self
    {
        return new self();
    }

    /**
     * The inline tools, with the elements of a page's structure — paragraphs, headings, lists, quotes, code, tables,
     * figures and images from `http`, `https` or relative sources —, none with a class, a style or an event.
     */
    public static function raw(): self
    {
        $structure = [];

        foreach (['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'pre', 'table', 'caption', 'thead', 'tbody', 'tfoot', 'tr', 'hr', 'figure', 'figcaption', 'dl', 'dt', 'dd', 'small', 'cite', 'q', 'kbd', 'span', 'section', 'article', 'aside', 'header', 'footer', 'details', 'summary'] as $tag) {
            $structure[] = new InlineTool($tag, $tag, [$tag]);
        }

        return new self([
            ...self::rich()->tools,
            ...$structure,
            new InlineTool('cell', 'td', ['td'], null, ['colspan' => AttributeValue::Number, 'rowspan' => AttributeValue::Number]),
            new InlineTool('headerCell', 'th', ['th'], null, ['colspan' => AttributeValue::Number, 'rowspan' => AttributeValue::Number]),
            new InlineTool('image', 'img', ['img'], null, ['src' => AttributeValue::Media, 'alt' => AttributeValue::Text, 'width' => AttributeValue::Number, 'height' => AttributeValue::Number], ['src']),
        ]);
    }

    public function with(InlineTool ...$tools): self
    {
        return new self([...$this->tools, ...array_values($tools)]);
    }

    public function without(string ...$names): self
    {
        $kept = [];

        foreach ($this->tools as $tool) {
            if (!\in_array($tool->name, $names, true)) {
                $kept[] = $tool;
            }
        }

        return new self($kept);
    }

    /**
     * Whether a tool of the profile reads elements of a tag.
     */
    public function reads(string $tag): bool
    {
        return isset($this->byTag[$tag]);
    }

    /**
     * The first tool that writes an element, with the attributes it keeps of it, where the elements open around it
     * are written as `$inside`; `null` when none does.
     *
     * @param array<string, string> $attributes
     * @param array<string, int>    $inside     how many elements of each written tag are open
     *
     * @return ?array{InlineTool, array<string, string>}
     */
    public function match(string $tag, array $attributes, array $inside = []): ?array
    {
        foreach ($this->byTag[$tag] ?? [] as $tool) {
            $clean = $tool->accepts($tag, $attributes) && $tool->fits($inside) ? $tool->clean($attributes) : null;

            if (null !== $clean) {
                return [$tool, $clean];
            }
        }

        return null;
    }
}
