<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Check;

use Override;
use Phil\TranslationMemory\Check\Contract\Check;
use Phil\TranslationMemory\Xliff\InlineCodes;

/**
 * The tags of inline HTML: a target holds each tag of its source as many times, written the same — attributes
 * included —, and no other.
 */
final readonly class Tags implements Check
{
    public const string NAME = 'tags';

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(Checked $checked): array
    {
        $source = $this->tags($checked->source);
        $target = $this->tags($checked->target);
        $issues = [];

        foreach ($source as $tag => $count) {
            if (($target[$tag] ?? 0) < $count) {
                $issues[] = new CheckIssue(self::NAME, \sprintf('The target lacks the tag %s.', $tag));
            }
        }

        foreach ($target as $tag => $count) {
            if (($source[$tag] ?? 0) < $count) {
                $issues[] = new CheckIssue(self::NAME, \sprintf('The target holds the tag %s, which the source does not.', $tag));
            }
        }

        return $issues;
    }

    /**
     * How many times each tag of a text is written.
     *
     * @return array<string, int>
     */
    private function tags(string $html): array
    {
        $written = [];

        foreach (InlineCodes::tokens($html) as [$tag, $token]) {
            if ($tag) {
                $written[] = $token;
            }
        }

        return array_count_values($written);
    }
}
