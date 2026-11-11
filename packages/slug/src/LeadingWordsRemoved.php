<?php

declare(strict_types=1);

namespace Phil\Slug;

/**
 * Drops one leading word of a list from the slug of the slugger it decorates: with `le`, `la`, `les` and `the`,
 * `Les Gardiens` is `gardiens` and `The Guild` is `guild`, so names sort and read by their first meaningful word.
 *
 * **One word, once.** The first word of the slug is dropped when it is in the list and words follow it; a slug that
 * is only that word keeps it (`Les` stays `les`). The words are given as slugs are spelled, and compared to the
 * slug's first word: `L'Ordre` gives `l-ordre`, whose first word is `l`.
 */
final readonly class LeadingWordsRemoved implements Slugger
{
    /**
     * @var list<string>
     */
    private array $words;

    /**
     * @param list<string> $words
     *
     * @throws InvalidSlug when a word is not a single slug word
     */
    public function __construct(
        private Slugger $slugger,
        array $words,
    ) {
        $this->words = array_map(self::checkWord(...), $words);
    }

    /**
     * A leading word as the list takes it — one word of lowercase ASCII letters and digits, as slugs spell them —,
     * checked before any slugger is built: what a configuration of the words reads.
     *
     * @throws InvalidSlug when it is not a single slug word
     */
    public static function checkWord(string $word): string
    {
        if (1 !== preg_match('/^[a-z0-9]+$/D', $word)) {
            throw InvalidSlug::because(\sprintf('"%s" is not one word of lowercase ASCII letters and digits.', $word));
        }

        return $word;
    }

    public function slug(string $text): Slug
    {
        $slug = $this->slugger->slug($text);
        $parts = explode('-', $slug->value, 2);

        if (2 === \count($parts) && \in_array($parts[0], $this->words, true)) {
            return Slug::fromString($parts[1]);
        }

        return $slug;
    }
}
