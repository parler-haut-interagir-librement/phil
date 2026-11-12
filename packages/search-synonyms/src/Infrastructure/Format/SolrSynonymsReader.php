<?php

declare(strict_types=1);

namespace Phil\SearchSynonyms\Infrastructure\Format;

use Phil\SearchSynonyms\Domain\Exception\InvalidSynonyms;
use Phil\SearchSynonyms\Domain\ValueObject\SynonymList;
use Phil\SearchSynonyms\Domain\ValueObject\SynonymSet;

/**
 * Reads the Solr synonyms format: one rule per line, phrases separated by commas — `jdr, jeu de rôle`, equivalent —
 * or two sides around `=>` — `donjon => donjon, dungeon`, one-way —; a line starting with `#` is a comment, a blank
 * line is skipped; a backslash makes the next character plain — `\,`, `\=>`, `\\`, `\#` at the start of a line.
 *
 * A file is 1 MiB at most, a line 4096 bytes; a line that is no rule is refused with its number, and so is a file
 * that is not valid UTF-8.
 */
final readonly class SolrSynonymsReader
{
    public const int MAX_BYTES = 1_048_576;

    public const int MAX_LINE = 4096;

    /**
     * @throws InvalidSynonyms
     */
    public function read(string $text, string $language): SynonymList
    {
        if (\strlen($text) > self::MAX_BYTES) {
            throw InvalidSynonyms::because(\sprintf('A synonyms file is %d bytes at most.', self::MAX_BYTES));
        }

        if (1 !== preg_match('//u', $text)) {
            throw InvalidSynonyms::because('A synonyms file is valid UTF-8.');
        }

        $sets = [];
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", str_starts_with($text, "\u{FEFF}") ? substr($text, 3) : $text));

        foreach ($lines as $index => $line) {
            $number = $index + 1;
            $line = trim($line);

            if ('' === $line || str_starts_with($line, '#')) {
                continue;
            }

            if (\strlen($line) > self::MAX_LINE) {
                throw InvalidSynonyms::atLine($number, \sprintf('a line is %d bytes at most.', self::MAX_LINE));
            }

            if (\count($sets) >= SynonymList::MAX_SETS) {
                throw InvalidSynonyms::atLine($number, \sprintf('a file holds %d rules at most.', SynonymList::MAX_SETS));
            }

            $sets[] = $this->rule($line, $number);
        }

        return SynonymList::of($language, $sets);
    }

    /**
     * @throws InvalidSynonyms
     */
    private function rule(string $line, int $number): SynonymSet
    {
        $sides = [[]];
        $phrase = '';
        $length = \strlen($line);

        for ($at = 0; $at < $length; ++$at) {
            $character = $line[$at];

            if ('\\' === $character) {
                if ($at + 1 === $length) {
                    throw InvalidSynonyms::atLine($number, 'a backslash escapes the character after it, and there is none.');
                }

                $phrase .= $line[++$at];
            } elseif (',' === $character) {
                $sides[\count($sides) - 1][] = $phrase;
                $phrase = '';
            } elseif ('=' === $character && '>' === ($line[$at + 1] ?? '')) {
                if (2 === \count($sides)) {
                    throw InvalidSynonyms::atLine($number, 'a rule has one "=>" at most.');
                }

                $sides[0][] = $phrase;
                $sides[] = [];
                $phrase = '';
                ++$at;
            } else {
                $phrase .= $character;
            }
        }

        $sides[\count($sides) - 1][] = $phrase;

        foreach ($sides as $side) {
            foreach ($side as $one) {
                if ('' === trim($one)) {
                    throw InvalidSynonyms::atLine($number, 'a phrase is empty: two commas, or a comma or "=>" with nothing beside it.');
                }
            }
        }

        try {
            return 1 === \count($sides) ? SynonymSet::equivalent($sides[0]) : SynonymSet::oneWay($sides[0], $sides[1]);
        } catch (InvalidSynonyms $invalidSynonyms) {
            throw InvalidSynonyms::atLine($number, lcfirst($invalidSynonyms->getMessage()));
        }
    }
}
