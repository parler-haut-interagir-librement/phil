<?php

declare(strict_types=1);

namespace Phil\TranslationMemory;

use Phil\TextDiff\TextDiff;

/**
 * What changed in the source of a stale segment since its target was written: the words of both sources compared by
 * `phil/text-diff`, as escaped HTML with `<del>` and `<ins>`, ready to show next to the target to revise.
 */
final readonly class SourceChange
{
    private TextDiff $differ;

    public function __construct(?TextDiff $differ = null)
    {
        $this->differ = $differ ?? new TextDiff();
    }

    /**
     * The change as HTML, `null` when the segment has no earlier source to compare with or the words are the same.
     */
    public function of(Segment $segment): ?string
    {
        if (null === $segment->translatedSource) {
            return null;
        }

        $diff = $this->differ->words(Text::plain($segment->translatedSource), Text::plain($segment->source));

        return $diff->isIdentical() ? null : $this->differ->toHtml($diff);
    }
}
