<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Check;

use Override;
use Phil\TranslationMemory\Check\Contract\Check;
use Phil\TranslationMemory\Text;

/**
 * The punctuation that ends a sentence: a target ends with the same mark as its source — a full stop, an exclamation
 * or question mark, a colon, a semicolon, an ellipsis, a comma —, or with none when the source has none.
 *
 * The full-width marks of Chinese and Japanese count as their Latin counterparts, three dots as an ellipsis, and the
 * spaces French puts before `!`, `?`, `:` and `;`, the closing quotes and brackets after the mark are looked past.
 */
final readonly class Punctuation implements Check
{
    public const string NAME = 'punctuation';

    /** Each final mark, and the mark it counts as. */
    private const array MARKS = [
        '.' => '.', '。' => '.', '．' => '.', '!' => '!', '！' => '!', '?' => '?', '？' => '?', ':' => ':', '：' => ':',
        ';' => ';', '；' => ';', '…' => '…', ',' => ',', '，' => ',', '、' => ',',
    ];

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(Checked $checked): array
    {
        $source = $this->mark($checked->source);
        $target = $this->mark($checked->target);

        if ($source === $target) {
            return [];
        }

        return [new CheckIssue(self::NAME, \sprintf('The source ends with %s, the target with %s.', $this->describe($source), $this->describe($target)))];
    }

    /**
     * The final mark of a text, as the mark it counts as; `null` when it ends with none.
     */
    private function mark(string $html): ?string
    {
        $plain = preg_replace('/[\s\x{00A0}\x{202F}"\'»«”“’‘)\]}」』）]+$/u', '', Text::plain($html)) ?? '';
        $plain = str_ends_with($plain, '...') ? '…' : $plain;

        $last = mb_substr($plain, -1);

        return self::MARKS[$last] ?? null;
    }

    private function describe(?string $mark): string
    {
        return null === $mark ? 'no punctuation' : '“'.$mark.'”';
    }
}
