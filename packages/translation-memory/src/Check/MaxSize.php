<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Check;

use Override;
use Phil\TranslationMemory\Check\Contract\Check;
use Phil\TranslationMemory\Check\Contract\TextWidth;
use Phil\TranslationMemory\Exception\InvalidConfiguration;
use Phil\TranslationMemory\Text;

/**
 * The widest a target may be drawn, in pixels: a button, a card, the cell of a printed sheet. Each line of the target —
 * cut at `<br>` and at line breaks — is measured by the `TextWidth` of the template's font, and the widest must fit.
 *
 * The limit is the segment's flag `max-size:<pixels>`, or else the one given here; without either, the check passes.
 */
final readonly class MaxSize implements Check
{
    public const string NAME = 'max-size';

    /**
     * @throws InvalidConfiguration when the limit is not above 0
     */
    public function __construct(
        private TextWidth $width,
        private ?float $maximum = null,
    ) {
        if (null !== $maximum && $maximum <= 0.0) {
            throw InvalidConfiguration::because(\sprintf('The widest a target may be is above 0 pixels; got %s.', $maximum));
        }
    }

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(Checked $checked): array
    {
        $flag = $checked->flags->value(self::NAME);
        $flagged = is_numeric($flag) ? (float) $flag : 0.0;
        $maximum = $flagged > 0.0 ? $flagged : $this->maximum;

        if (null === $maximum) {
            return [];
        }

        $widest = 0.0;
        $lines = preg_split('/<br\s*\/?>|\R/iu', $checked->target);

        foreach (false === $lines ? [] : $lines as $line) {
            $widest = max($widest, $this->width->width(Text::plain($line)));
        }

        if ($widest <= $maximum) {
            return [];
        }

        return [new CheckIssue(self::NAME, \sprintf('The target is %s px wide; at most %s px fit.', round($widest, 1), round($maximum, 1)))];
    }
}
