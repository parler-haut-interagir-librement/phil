<?php

declare(strict_types=1);

namespace Phil\Ujo\Html;

use ArrayObject;
use Closure;
use Phil\Ujo\Block;
use Phil\Ujo\Document;

/**
 * One rendering of a document, handed to each block renderer: the document — a table of contents reads its headings —,
 * the renderers of nested blocks, the sanitizer, the values computed once for the whole rendering, and the writing of
 * elements with the attributes of their tunes.
 */
final readonly class Rendering
{
    /** @var ArrayObject<string, mixed> each key, to the value computed for it */
    private ArrayObject $remembered;

    public function __construct(
        public Document $document,
        private HtmlRenderer $renderer,
        private InlineSanitizer $sanitizer,
        private bool $dataAttributes = false,
    ) {
        $this->remembered = new ArrayObject();
    }

    /**
     * The value of a key, computed by `$compute` the first time this rendering is asked for it: what a renderer reads
     * from the whole document — the headings of a table of contents — is read once, however many blocks need it.
     *
     * @template T
     *
     * @param Closure(): T $compute
     *
     * @return T
     */
    public function remember(string $key, Closure $compute): mixed
    {
        if (!$this->remembered->offsetExists($key)) {
            $this->remembered[$key] = $compute();
        }

        /** @var T $value */
        $value = $this->remembered[$key];

        return $value;
    }

    /**
     * The HTML of blocks, one per line; a value that is not a list of blocks renders nothing.
     */
    public function blocks(mixed $blocks): string
    {
        $html = [];

        foreach (\is_array($blocks) ? $blocks : [] as $block) {
            if ($block instanceof Block) {
                $html[] = $this->renderer->renderer($block->type)->render($block, $this);
            }
        }

        return implode("\n", $html);
    }

    /**
     * An HTML value, sanitized with a profile; a value that is not a text renders nothing.
     */
    public function inline(mixed $html, string $profile = 'rich'): string
    {
        return \is_string($html) ? $this->sanitizer->sanitize($html, $profile) : '';
    }

    /**
     * The text of an HTML value, without its elements, decoded.
     */
    public function plain(mixed $html): string
    {
        return \is_string($html) ? $this->sanitizer->toText($html) : '';
    }

    /**
     * A plain value escaped; a value that is neither a text nor a number renders nothing.
     */
    public static function text(mixed $value): string
    {
        return \is_string($value) || \is_int($value) ? InlineSanitizer::escape((string) $value) : '';
    }

    /**
     * The root element of a block: its class `ujo-` and the type in kebab case, the renderer's classes and attributes,
     * and what its tunes add — an `id` from `anchor`, classes from `alignment`, `textVariant`, `notice`, `indent`,
     * `variant`, `mask` and `layout`, a style from `layout` and `mask`, `data-toc="exclude"` from `toc`, `lang` and
     * `dir` from `language`, and, when the renderer writes them, the `data-*` attributes of the `data` tune — never one
     * the renderer or another tune writes. An `id` of the anchor tune wins over the renderer's, and the style of the
     * tunes follows the renderer's. The `note` tune is never written.
     *
     * @param array<string, bool|int|string|null> $attributes
     * @param list<string>                        $classes
     */
    public function element(string $tag, Block $block, string $content, array $attributes = [], array $classes = []): string
    {
        $tunes = $this->tunes($block->tunes);
        $style = implode(';', [...\is_string($attributes['style'] ?? null) ? [$attributes['style']] : [], ...$tunes['style']]);
        $id = $tunes['id'] ?? $attributes['id'] ?? null;
        unset($attributes['id'], $attributes['style']);

        return self::tag($tag, [
            'id' => $id,
            'class' => implode(' ', ['ujo-'.strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $block->type) ?? $block->type), ...$classes, ...$tunes['classes']]),
            'lang' => $this->field($block->tunes, 'language', 'language'),
            'dir' => $this->field($block->tunes, 'language', 'direction'),
            ...$this->data($block->tunes),
            ...$attributes,
            ...$tunes['data'],
            'style' => '' === $style ? null : $style,
        ], $content);
    }

    /**
     * An element with attributes: `true` writes the attribute alone, `false` and `null` leave it out. `br`, `hr`,
     * `img`, `source` and `track` are written without content or end tag.
     *
     * @param array<string, bool|int|string|null> $attributes
     */
    public static function tag(string $tag, array $attributes, string $content = ''): string
    {
        $html = '<'.$tag;

        foreach ($attributes as $name => $value) {
            $html .= match (true) {
                true === $value => ' '.$name,
                false === $value, null === $value => '',
                default => ' '.$name.'="'.InlineSanitizer::escape((string) $value).'"',
            };
        }

        return \in_array($tag, ['br', 'hr', 'img', 'source', 'track'], true) ? $html.'>' : $html.'>'.$content.'</'.$tag.'>';
    }

    /**
     * A value that is an array, or the empty array.
     *
     * @return array<array-key, mixed>
     */
    public static function array(mixed $value): array
    {
        return \is_array($value) ? $value : [];
    }

    /**
     * The items of a list value that are objects.
     *
     * @return list<array<array-key, mixed>>
     */
    public static function rows(mixed $value): array
    {
        return array_values(array_filter(self::array($value), \is_array(...)));
    }

    /**
     * @param array<string, mixed> $tunes
     *
     * @return array{id: ?string, classes: list<string>, style: list<string>, data: array<string, ?string>}
     */
    private function tunes(array $tunes): array
    {
        $alignment = $this->field($tunes, 'alignment', 'alignment');
        $variant = \is_string($tunes['textVariant'] ?? null) ? $tunes['textVariant'] : '';
        $notice = $this->field($tunes, 'notice', 'style');
        $indent = $this->field($tunes, 'indent', 'indentLevel');
        $named = $this->field($tunes, 'variant', 'name');
        $mask = $this->field($tunes, 'mask', 'shape');
        $wrap = $this->field($tunes, 'layout', 'wrap');
        $caption = $this->field($tunes, 'notice', 'caption');

        $classes = [];
        $style = [];

        foreach ([
            'ujo-align-' => $alignment,
            'ujo-text-' => '' === $variant ? null : $variant,
            'ujo-notice ujo-notice-' => $notice,
            'ujo-indent-' => '0' === $indent ? null : $indent,
            'ujo-variant-' => $named,
            'ujo-frame' => '1' === $this->field($tunes, 'variant', 'frame') ? '' : null,
            'ujo-decoration' => '1' === $this->field($tunes, 'variant', 'decoration') ? '' : null,
            'ujo-mask-' => null === $mask ? null : $mask.'-'.($this->field($tunes, 'mask', 'variant') ?? '1'),
            'ujo-wrap-' => $wrap,
        ] as $prefix => $value) {
            if (null !== $value) {
                $classes[] = $prefix.$value;
            }
        }

        foreach ([
            'position' => $this->field($tunes, 'layout', 'position'),
            'top' => $this->field($tunes, 'layout', 'top'),
            'right' => $this->field($tunes, 'layout', 'right'),
            'bottom' => $this->field($tunes, 'layout', 'bottom'),
            'left' => $this->field($tunes, 'layout', 'left'),
            'width' => $this->field($tunes, 'layout', 'width'),
            'height' => $this->field($tunes, 'layout', 'height'),
            'z-index' => $this->field($tunes, 'layout', 'zIndex'),
            '--ujo-mask-offset-x' => $this->field($tunes, 'mask', 'offsetX'),
            '--ujo-mask-offset-y' => $this->field($tunes, 'mask', 'offsetY'),
            '--ujo-mask-rotation' => null === $mask ? null : ($this->field($tunes, 'mask', 'rotation') ?? '0').'deg',
        ] as $property => $value) {
            if (null !== $value) {
                $style[] = $property.':'.$value;
            }
        }

        return [
            'id' => $this->field($tunes, 'anchor', 'anchor'),
            'classes' => $classes,
            'style' => $style,
            'data' => [
                'data-notice-caption' => '' === $caption ? null : $caption,
                'data-toc' => '' === $this->field($tunes, 'toc', 'include') ? 'exclude' : null,
            ],
        ];
    }

    /**
     * The values of the `data` tune as `data-*` attributes, when the renderer writes them.
     *
     * @param array<string, mixed> $tunes
     *
     * @return array<string, string>
     */
    private function data(array $tunes): array
    {
        $data = [];

        foreach ($this->dataAttributes ? self::array($tunes['data'] ?? null) : [] as $name => $value) {
            if (\is_string($value)) {
                $data['data-'.$name] = $value;
            }
        }

        return $data;
    }

    /**
     * A scalar value of a tune as text — `true` as `1`, `false` as the empty text —; `null` when absent.
     *
     * @param array<string, mixed> $tunes
     */
    private function field(array $tunes, string $tune, string $field): ?string
    {
        $value = \is_array($tunes[$tune] ?? null) ? $tunes[$tune][$field] ?? null : null;

        return \is_scalar($value) ? (string) $value : null;
    }
}
