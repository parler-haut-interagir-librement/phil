<?php

declare(strict_types=1);

namespace Phil\Typography\Fixer;

use Override;
use Phil\Typography\Text\Chars;
use Phil\Typography\Text\Run;

/**
 * The letter x between two measures becomes the multiplication sign: `1920 x 1080` is `1920 × 1080`, `20cm x 30cm`
 * is `20cm × 30cm`, `6' x 8'` is `6' × 8'`. A hexadecimal number such as `0x1F` is left alone.
 */
final readonly class Dimension implements Fixer
{
    private const string PATTERN = '/(?<!(?![xX])\p{L}|[\d.,])(?!0x)(\d+(?:[.,]\d+)?(?: ?(?:mm|cm|dm|km|m|in|ft|px|pt|em|rem|"|″|\'|′)(?![\p{L}]))?)( ?)[xX]( ?)(?=\d)/u';

    #[Override]
    public function fix(Run $run): Run
    {
        return $run->replace(static fn (string $text): string => preg_replace(self::PATTERN, '$1$2'.Chars::MULTIPLICATION.'$3', $text) ?? $text);
    }
}
