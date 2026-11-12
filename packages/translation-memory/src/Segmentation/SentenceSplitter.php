<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Segmentation;

/**
 * Cuts an inline HTML text into sentences: a sentence ends at `.`, `!`, `?` or `…`, with the closing quotes, brackets
 * and tags that follow — a French `»` after its space too —, when white space comes next and the next word does not
 * start with a lower-case letter; at `。`, `！` or `？` it ends even without white space. A period after a known
 * abbreviation or a single letter — an initial — ends nothing. Tags are never cut.
 *
 * The pieces give the text back exactly when joined: each piece is a sentence with the white space that follows it.
 */
final readonly class SentenceSplitter
{
    /**
     * Abbreviations followed by a period inside a sentence, in English and French, compared in lower case.
     */
    public const array ABBREVIATIONS = [
        'mr', 'mrs', 'ms', 'dr', 'prof', 'sr', 'jr', 'st', 'mt', 'vs', 'e.g', 'i.e', 'cf', 'no', 'vol', 'p', 'pp',
        'ch', 'fig', 'ed', 'approx', 'm', 'mm', 'mme', 'mlle', 'me', 'pr', 'av', 'boul', 'env', 'ex', 'p.ex', 'c.-à-d',
    ];

    private const array ENDS = ['.', '!', '?', '…'];

    private const array WIDE_ENDS = ['。', '！', '？'];

    private const array CLOSERS = ['"', "'", '”', '’', '»', ')', ']', '}', '」', '』'];

    /**
     * @var array<string, true>
     */
    private array $abbreviations;

    /**
     * @param list<string> $abbreviations the words that a period does not end a sentence after, without their period
     */
    public function __construct(array $abbreviations = self::ABBREVIATIONS)
    {
        $this->abbreviations = array_fill_keys(array_map(mb_strtolower(...), $abbreviations), true);
    }

    /**
     * The sentences of a text, each with the white space after it.
     *
     * @return list<string>
     */
    public function split(string $text): array
    {
        $characters = mb_str_split($text);
        $count = \count($characters);
        $pieces = [];
        $start = 0;
        $index = 0;

        while ($index < $count) {
            $character = $characters[$index];

            if ('<' === $character) {
                $index = $this->afterTag($characters, $index);

                continue;
            }

            ++$index;
            $wide = \in_array($character, self::WIDE_ENDS, true);

            if (!$wide && !\in_array($character, self::ENDS, true)) {
                continue;
            }

            if ('.' === $character && $this->isAbbreviation($characters, $index - 1)) {
                continue;
            }

            $index = $this->afterClosers($characters, $index);
            $space = $index;

            while ($space < $count && $this->isSpace($characters[$space])) {
                ++$space;
            }

            if (!$wide && ($space === $index || $this->continues($characters, $space))) {
                continue;
            }

            $pieces[] = implode('', \array_slice($characters, $start, $space - $start));
            $start = $space;
            $index = $space;
        }

        if ($start < $count) {
            $pieces[] = implode('', \array_slice($characters, $start));
        }

        return $pieces;
    }

    /**
     * @param list<string> $characters
     */
    private function afterTag(array $characters, int $index): int
    {
        $count = \count($characters);

        while ($index < $count && '>' !== $characters[$index]) {
            ++$index;
        }

        return $index + 1;
    }

    /**
     * @param list<string> $characters
     */
    private function afterClosers(array $characters, int $index): int
    {
        $count = \count($characters);

        while ($index < $count) {
            if ('<' === $characters[$index] && '/' === ($characters[$index + 1] ?? '')) {
                $index = $this->afterTag($characters, $index);
            } elseif ($this->isSpace($characters[$index]) && '»' === ($characters[$index + 1] ?? '')) {
                ++$index;
            } elseif (\in_array($characters[$index], self::CLOSERS, true) || \in_array($characters[$index], self::ENDS, true)) {
                ++$index;
            } else {
                break;
            }
        }

        return $index;
    }

    /**
     * Whether the word before the period at `$period` is an abbreviation or a single letter.
     *
     * @param list<string> $characters
     */
    private function isAbbreviation(array $characters, int $period): bool
    {
        $word = '';

        for ($index = $period - 1; $index >= 0 && !$this->isSpace($characters[$index]) && '>' !== $characters[$index]; --$index) {
            $word = $characters[$index].$word;
        }

        $word = mb_strtolower(ltrim($word, '("\'“‘«[{'));

        return isset($this->abbreviations[$word]) || 1 === preg_match('/^\p{L}$/u', $word);
    }

    /**
     * Whether the sentence goes on at `$index`: tags only up to the end, or a word that starts in lower case.
     *
     * @param list<string> $characters
     */
    private function continues(array $characters, int $index): bool
    {
        $count = \count($characters);

        while ($index < $count && '<' === $characters[$index]) {
            $index = $this->afterTag($characters, $index);
        }

        $character = $characters[$index] ?? null;

        return null === $character || 1 === preg_match('/\p{Ll}/u', $character);
    }

    private function isSpace(string $character): bool
    {
        return 1 === preg_match('/\s/u', $character);
    }
}
