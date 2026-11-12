<?php

declare(strict_types=1);

namespace Phil\SearchSynonyms\Domain\ValueObject;

use Phil\SearchSynonyms\Domain\Exception\InvalidSynonyms;

/**
 * One rule of synonyms: equivalent phrases, any of which a search reads as all of them — `jdr, jeu de rôle` —, or
 * one-way, the phrases on the left read as those on the right alone — `donjon => donjon, dungeon`.
 *
 * Each phrase is kept as a search compares words: in lower case, every run of white space one space. A phrase is 1 to
 * 100 characters and 8 words at most, of valid UTF-8 without control characters; a rule holds 64 phrases at most, each
 * once.
 */
final readonly class SynonymSet
{
    public const int MAX_PHRASES = 64;

    public const int MAX_LENGTH = 100;

    public const int MAX_WORDS = 8;

    /**
     * @param list<string> $from
     * @param list<string> $to
     */
    private function __construct(
        public array $from,
        public array $to,
        public bool $equivalent,
    ) {
    }

    /**
     * @param list<string> $phrases two or more, once normalized
     *
     * @throws InvalidSynonyms
     */
    public static function equivalent(array $phrases): self
    {
        $phrases = self::checked($phrases);

        if (\count($phrases) < 2) {
            throw InvalidSynonyms::because('Equivalent synonyms are two phrases or more.');
        }

        return new self($phrases, $phrases, true);
    }

    /**
     * @param list<string> $from the phrases read as the others
     * @param list<string> $to   what they are read as, themselves too when listed
     *
     * @throws InvalidSynonyms
     */
    public static function oneWay(array $from, array $to): self
    {
        $from = self::checked($from);
        $to = self::checked($to);

        if ([] === $from || [] === $to) {
            throw InvalidSynonyms::because('One-way synonyms are one phrase or more on each side.');
        }

        if ([] === array_diff($from, $to) && [] === array_diff($to, $from)) {
            throw InvalidSynonyms::because('One-way synonyms change something: the two sides are the same.');
        }

        return new self($from, $to, false);
    }

    /**
     * The phrase as a search compares it: lower case, one space between words, none around them.
     */
    public static function normalize(string $phrase): string
    {
        $lower = mb_strtolower($phrase);

        return trim(preg_replace('/\s+/u', ' ', $lower) ?? $lower);
    }

    /**
     * Every phrase of the rule, each once: the left side, then what the right side adds.
     *
     * @return list<string>
     */
    public function phrases(): array
    {
        return array_values(array_unique([...$this->from, ...$this->to]));
    }

    /**
     * @param list<string> $phrases
     *
     * @return list<string>
     */
    private static function checked(array $phrases): array
    {
        $checked = [];

        foreach ($phrases as $phrase) {
            if (1 !== preg_match('//u', $phrase) || 1 === preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $phrase)) {
                throw InvalidSynonyms::because('A synonym is text of valid UTF-8 without control characters.');
            }

            $normalized = self::normalize($phrase);

            if ('' === $normalized || mb_strlen($normalized) > self::MAX_LENGTH || substr_count($normalized, ' ') >= self::MAX_WORDS) {
                throw InvalidSynonyms::because(\sprintf('A synonym is 1 to %d characters and %d words at most; "%s" is not.', self::MAX_LENGTH, self::MAX_WORDS, $normalized));
            }

            if (!\in_array($normalized, $checked, true)) {
                $checked[] = $normalized;
            }
        }

        if (\count($checked) > self::MAX_PHRASES) {
            throw InvalidSynonyms::because(\sprintf('A rule holds %d phrases at most; %d given.', self::MAX_PHRASES, \count($checked)));
        }

        return $checked;
    }
}
