<?php

declare(strict_types=1);

namespace Phil\Ujo\Type;

use Phil\Ujo\Schema\Plain;
use Phil\Ujo\Schema\Property;
use Phil\Ujo\Schema\Shape;

/**
 * The drawing a block keeps beside its source: `{"markup": svg or mathml, "key": text}` under `rendered`, written once
 * on the server when the block is saved — the SVG of a QR code, a chart or a diagram, the MathML of a formula — so
 * readers and print engines show it without a script.
 *
 * The normalizer only checks its shape and size; the markup is trusted by no one until the key proves who wrote it,
 * for the source the block holds now: `HtmlRenderer::withStoredMarkup()` takes that check.
 */
final class StoredMarkup
{
    /** The types whose blocks may keep a drawing. */
    public const array TYPES = ['math', 'mermaid', 'chart', 'qrCode'];

    /** The longest markup kept, in characters. */
    public const int MAX_MARKUP = 1_048_576;

    /**
     * The optional `rendered` property of those types.
     */
    public static function property(): Property
    {
        return Property::optional(new Shape([
            'markup' => Property::required(new Plain(self::MAX_MARKUP)),
            'key' => Property::required(new Plain(null, '/^[A-Za-z0-9._~-]{1,256}$/D')),
        ]));
    }
}
