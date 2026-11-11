<?php

declare(strict_types=1);

namespace Phil\Ujo\Type;

use Phil\Ujo\Html\LanguageTag;
use Phil\Ujo\Schema\Boolean;
use Phil\Ujo\Schema\Choice;
use Phil\Ujo\Schema\Dictionary;
use Phil\Ujo\Schema\Integer;
use Phil\Ujo\Schema\Plain;
use Phil\Ujo\Schema\Property;
use Phil\Ujo\Schema\Shape;

/**
 * The tunes of the default registry: alignment, anchor, text variant, notice and indent from Editor.js tools; the
 * layout, mask, table-of-contents and variant tunes of printed pages; the language of a block, its `data-*` values
 * and its writer's note, the last two never rendered by default.
 */
final class Tunes
{
    /**
     * @return list<TuneType>
     */
    public static function all(): array
    {
        return [
            new TuneType('alignment', new Shape([
                'alignment' => Property::required(new Choice(['left', 'center', 'right', 'justify'])),
            ]), ['alignmentTune']),
            new TuneType('anchor', new Shape([
                'anchor' => Property::required(new Plain(null, '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/D')),
            ]), ['anchorTune']),
            new TuneType('textVariant', new Choice(['', 'call-out', 'citation', 'details'])),
            new TuneType('notice', new Shape([
                'style' => Property::required(new Choice(['info', 'warning', 'spoiler'])),
                'caption' => Property::defaults(new Plain(200), ''),
            ]), ['noticeTune']),
            new TuneType('indent', new Shape([
                'indentLevel' => Property::required(new Integer(0, 8)),
            ]), ['indentTune']),
            new TuneType('layout', new Shape([
                'position' => Property::optional(new Choice(['absolute', 'relative'])),
                'top' => Property::optional(Plain::length()),
                'right' => Property::optional(Plain::length()),
                'bottom' => Property::optional(Plain::length()),
                'left' => Property::optional(Plain::length()),
                'width' => Property::optional(Plain::length()),
                'height' => Property::optional(Plain::length()),
                'wrap' => Property::optional(new Choice(['left', 'right'])),
                'zIndex' => Property::optional(new Integer(-10, 100)),
            ])),
            new TuneType('mask', new Shape([
                'shape' => Property::required(new Choice(['center', 'edge', 'corner'])),
                'variant' => Property::defaults(new Integer(1, 99), 1),
                'offsetX' => Property::optional(Plain::percentage()),
                'offsetY' => Property::optional(Plain::percentage()),
                'rotation' => Property::defaults(new Integer(-360, 360), 0),
            ])),
            new TuneType('toc', new Shape([
                'include' => Property::required(new Boolean()),
            ])),
            new TuneType('variant', new Shape([
                'name' => Property::required(Plain::token()),
                'frame' => Property::defaults(new Boolean(), false),
                'decoration' => Property::defaults(new Boolean(), false),
            ])),
            self::language(),
            new TuneType('data', new Dictionary()),
            new TuneType('note', new Shape([
                'text' => Property::required(new Plain(10_000)),
            ])),
        ];
    }

    /**
     * `{"language": BCP 47 tag, "direction": "ltr" | "rtl" | "auto"}`: a block written in another language than the
     * document, rendered as its `lang` and `dir`.
     */
    public static function language(): TuneType
    {
        return new TuneType('language', new Shape([
            'language' => Property::required(new Plain(LanguageTag::MAX_LENGTH, LanguageTag::PATTERN)),
            'direction' => Property::optional(new Choice(['ltr', 'rtl', 'auto'])),
        ]));
    }
}
