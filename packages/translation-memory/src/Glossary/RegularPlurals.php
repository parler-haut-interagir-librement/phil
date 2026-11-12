<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Glossary;

use Override;
use Phil\TranslationMemory\Glossary\Contract\Inflector;
use Phil\TranslationMemory\LanguageTag;

/**
 * The regular plural of a term in English and in French, its regional variants included.
 *
 * In English, the last word takes the plural — `hit point`, `hit points`; `box`, `boxes`; `city`, `cities`. In French,
 * every word before the first preposition does — `point de vie`, `points de vie`; `épée longue`, `épées longues`;
 * `cheval`, `chevaux`; `jeu`, `jeux` —, words already plural and words with an apostrophe left alone. Irregular
 * plurals — `mouse`, `œil` — are written in the term's forms; a phrase already plural gives itself, which the glossary
 * leaves out.
 */
final readonly class RegularPlurals implements Inflector
{
    /** The French words that end the inflected head of a phrase. */
    private const array FRENCH_PREPOSITIONS = ['à', 'au', 'aux', 'de', 'des', 'du', 'en', 'par', 'pour', 'sans', 'sous', 'sur'];

    /** The French nouns in `-al` and `-eu` that take an `s`. */
    private const array FRENCH_S = ['bal', 'bleu', 'carnaval', 'chacal', 'festival', 'pneu', 'récital', 'régal', 'émeu'];

    /** The French nouns in `-ou` that take an `x`. */
    private const array FRENCH_X = ['bijou', 'caillou', 'chou', 'genou', 'hibou', 'joujou', 'pou'];

    #[Override]
    public function forms(string $phrase, LanguageTag $language): array
    {
        $words = explode(' ', $phrase);

        $plural = match (explode('-', $language->value)[0]) {
            'en' => implode(' ', [...\array_slice($words, 0, -1), $this->english($words[\count($words) - 1])]),
            'fr' => $this->frenchPhrase($words),
            default => $phrase,
        };

        return $plural === $phrase ? [] : [$plural];
    }

    private function english(string $word): string
    {
        return match (true) {
            1 !== preg_match('/^\p{L}+$/u', $word) => $word,
            1 === preg_match('/(?:s|x|z|ch|sh)$/iu', $word) => $word.'es',
            1 === preg_match('/[^aeiou]y$/i', $word) => substr($word, 0, -1).'ies',
            default => $word.'s',
        };
    }

    /**
     * @param non-empty-list<string> $words
     */
    private function frenchPhrase(array $words): string
    {
        $inflected = [];
        $head = true;

        foreach ($words as $word) {
            $head = $head && !\in_array(mb_strtolower($word), self::FRENCH_PREPOSITIONS, true);
            $inflected[] = $head ? $this->french($word) : $word;
        }

        return implode(' ', $inflected);
    }

    private function french(string $word): string
    {
        $lower = mb_strtolower($word);

        return match (true) {
            1 !== preg_match('/^\p{L}+$/u', $word), 1 === preg_match('/[sxz]$/iu', $word) => $word,
            \in_array($lower, self::FRENCH_S, true) => $word.'s',
            \in_array($lower, self::FRENCH_X, true), 1 === preg_match('/(?:au|eu)$/i', $word) => $word.'x',
            1 === preg_match('/al$/i', $word) => substr($word, 0, -1).'ux',
            default => $word.'s',
        };
    }
}
