<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Domain\ValueObject;

use Phil\SearchBundle\Domain\Exception\InvalidSearch;

/**
 * Words a search reads as others in one language: equivalent phrases, any of which finds the others — `jdr`, `jeu de
 * rôle` —, or one-way, the phrases on the left searched as those on the right alone — `donjon => donjon, dungeon`.
 *
 * Each phrase is normalized as `Phrase` reads it, 1 to 100 characters and 8 words at most; a rule holds 64 phrases at
 * most, each once.
 */
final readonly class SynonymRule
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
     * @param list<string> $phrases at least two once normalized
     */
    public static function equivalent(array $phrases): self
    {
        $phrases = self::checked($phrases);

        if (\count($phrases) < 2) {
            throw InvalidSearch::because('Equivalent synonyms are two phrases or more.');
        }

        return new self($phrases, $phrases, true);
    }

    /**
     * @param list<string> $from the phrases searched as the others
     * @param list<string> $to   what they are searched as, themselves too when listed
     */
    public static function oneWay(array $from, array $to): self
    {
        $from = self::checked($from);
        $to = self::checked($to);

        if ([] === $from || [] === $to) {
            throw InvalidSearch::because('One-way synonyms are one phrase or more on each side.');
        }

        if ([] === array_diff($to, $from) && [] === array_diff($from, $to)) {
            throw InvalidSearch::because('One-way synonyms change something: the two sides are the same.');
        }

        return new self($from, $to, false);
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
            $normalized = Phrase::normalize($phrase);

            if ('' === $normalized || mb_strlen($normalized) > self::MAX_LENGTH || \count(explode(' ', $normalized)) > self::MAX_WORDS) {
                throw InvalidSearch::because(\sprintf('A synonym is 1 to %d characters and %d words at most; "%s" is not.', self::MAX_LENGTH, self::MAX_WORDS, $phrase));
            }

            if (!\in_array($normalized, $checked, true)) {
                $checked[] = $normalized;
            }
        }

        if (\count($checked) > self::MAX_PHRASES) {
            throw InvalidSearch::because(\sprintf('A synonym rule holds %d phrases at most; %d given.', self::MAX_PHRASES, \count($checked)));
        }

        return $checked;
    }
}
