<?php

declare(strict_types=1);

namespace Phil\SocialCards\Tests\Support;

use Phil\SocialCards\Card\CardFont;
use Phil\SocialCards\Contract\TextMeasure;

/**
 * A measure without an image library: every character is half the size wide, and each measure is counted.
 */
final class MonospaceMeasure implements TextMeasure
{
    public int $calls = 0;

    public function width(string $text, CardFont $font, int $size): int
    {
        ++$this->calls;

        return intdiv(mb_strlen($text) * $size, 2);
    }
}
