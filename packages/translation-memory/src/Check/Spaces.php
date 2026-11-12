<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Check;

use Override;
use Phil\TranslationMemory\Check\Contract\Check;

/**
 * The spaces a translator may lose or add by mistake: a target starts and ends with white space when its source does,
 * and holds no run of two spaces its source does not hold.
 */
final readonly class Spaces implements Check
{
    public const string NAME = 'spaces';

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(Checked $checked): array
    {
        $source = $this->words($checked->source);
        $target = $this->words($checked->target);
        $issues = [];

        foreach (['starts' => '/^\s/u', 'ends' => '/\s$/u'] as $where => $pattern) {
            $expected = 1 === preg_match($pattern, $source);

            if ($expected !== (1 === preg_match($pattern, $target))) {
                $issues[] = new CheckIssue(self::NAME, \sprintf('The %s %s with white space, the %s does not.', $expected ? 'source' : 'target', $where, $expected ? 'target' : 'source'));
            }
        }

        if (1 === preg_match('/ {2}/', $target) && 1 !== preg_match('/ {2}/', $source)) {
            $issues[] = new CheckIssue(self::NAME, 'The target holds two spaces in a row, the source none.');
        }

        return $issues;
    }

    /**
     * The words of inline HTML with their white space as written: tags left out, character references decoded.
     */
    private function words(string $html): string
    {
        return html_entity_decode(strip_tags($html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
    }
}
