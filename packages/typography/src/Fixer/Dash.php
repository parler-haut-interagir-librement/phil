<?php

declare(strict_types=1);

namespace Phil\Typography\Fixer;

use Override;
use Phil\Typography\Text\Chars;
use Phil\Typography\Text\Run;

/**
 * Dashes: `---` becomes an em dash and `--` an en dash; a hyphen between two numbers becomes an en dash, as in
 * `1914–1918` or `pages 12–15`, unless the numbers are part of a longer chain such as the date `2026-11-06`; a hyphen
 * between spaces becomes the dash of the style: `this – that` or `this—that`.
 */
final readonly class Dash implements Fixer
{
    private const string RANGE = '/(?<![\d\-–\/:]|\d[.,])(\d{1,4})-(\d{1,4})(?![\d\-\/:]|[.,]\d)/u';

    private const string BETWEEN_WORDS = '/(?<=\S) - (?=\S)/u';

    public function __construct(
        private DashStyle $style = DashStyle::Spaced,
    ) {
    }

    #[Override]
    public function fix(Run $run): Run
    {
        $dash = DashStyle::Spaced === $this->style ? ' '.Chars::EN_DASH.' ' : Chars::EM_DASH;

        return $run->replace(static function (string $text) use ($dash): string {
            $text = strtr($text, ['---' => Chars::EM_DASH, '--' => Chars::EN_DASH]);
            $text = preg_replace(self::RANGE, '$1'.Chars::EN_DASH.'$2', $text) ?? $text;

            return preg_replace(self::BETWEEN_WORDS, $dash, $text) ?? $text;
        });
    }
}
