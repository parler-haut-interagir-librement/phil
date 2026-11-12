<?php

declare(strict_types=1);

namespace Phil\SearchSynonyms\Domain\ValueObject;

use Phil\SearchSynonyms\Domain\Exception\InvalidSynonyms;

/**
 * The synonyms of one language: its primary subtag in lower case — `fr`, `en` —, and its rules in their order, 10 000
 * at most.
 */
final readonly class SynonymList
{
    public const int MAX_SETS = 10_000;

    /**
     * @param list<SynonymSet> $sets
     */
    private function __construct(
        public string $language,
        public array $sets,
    ) {
    }

    /**
     * @param string           $language a language tag, `fr` or `fr-CA`, kept as its primary subtag
     * @param list<SynonymSet> $sets
     *
     * @throws InvalidSynonyms
     */
    public static function of(string $language, array $sets = []): self
    {
        if (\count($sets) > self::MAX_SETS) {
            throw InvalidSynonyms::because(\sprintf('A language holds %d rules at most; %d given.', self::MAX_SETS, \count($sets)));
        }

        return new self(self::language($language), $sets);
    }

    /**
     * The primary subtag of a language tag, in lower case.
     *
     * @throws InvalidSynonyms when it is no language tag
     */
    public static function language(string $language): string
    {
        if (1 !== preg_match('/^([A-Za-z]{2,3})(?:[-_][A-Za-z0-9]{1,8})*$/D', $language, $match)) {
            throw InvalidSynonyms::because(\sprintf('A language is a tag such as "fr" or "fr-CA"; "%s" is not.', $language));
        }

        return strtolower($match[1]);
    }

    /**
     * The same language with one more rule.
     *
     * @throws InvalidSynonyms past 10 000 rules
     */
    public function with(SynonymSet $set): self
    {
        return self::of($this->language, [...$this->sets, $set]);
    }
}
