<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Glossary;

use Phil\TranslationMemory\Glossary\Contract\Inflector;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Text;

/**
 * The terms of one pair of languages, found in sources and checked in targets.
 *
 * Terms and translations are found as whole words in the plain text — never inside a longer word —, without regard to
 * case unless the term says otherwise; where two terms overlap, the longer one wins. A target is checked term by term:
 * it must hold an allowed translation at least as many times as the source holds the term, and no refused one outside
 * an allowed translation — `vie` may be refused while `points de vie` is allowed. The inflected forms of a term are
 * found as the term, and those of its translations count as the translations; given an `Inflector`, the glossary
 * adds the forms it makes to every term.
 */
final readonly class Glossary
{
    /**
     * @var list<Term>
     */
    public array $terms;

    /**
     * @param list<Term> $terms
     */
    public function __construct(
        public LanguageTag $sourceLanguage,
        public LanguageTag $targetLanguage,
        array $terms = [],
        ?Inflector $inflector = null,
    ) {
        $this->terms = $inflector instanceof Inflector ? array_map(static fn (Term $term): Term => $term->inflected($inflector, $sourceLanguage, $targetLanguage), $terms) : $terms;
    }

    /**
     * The terms of a source text, in reading order, overlaps resolved in favour of the longer term.
     *
     * @return list<TermHit>
     */
    public function find(string $source): array
    {
        $plain = Text::plain($source);
        $found = [];

        foreach ($this->terms as $term) {
            foreach ($term->variants() as $variant) {
                foreach ($this->occurrences($plain, $variant, $term->caseSensitive) as $byte => $text) {
                    $found[] = [$byte, $text, $term];
                }
            }
        }

        usort($found, static fn (array $a, array $b): int => [$a[0], \strlen($b[1])] <=> [$b[0], \strlen($a[1])]);
        $hits = [];
        $end = null;

        foreach ($found as [$byte, $text, $term]) {
            if (null === $end || $byte >= $end) {
                $hits[] = new TermHit($term, $text, mb_strlen(substr($plain, 0, $byte)));
                $end = $byte + \strlen($text);
            }
        }

        return $hits;
    }

    /**
     * The issues of a target against the terms of its source.
     *
     * @return list<TermIssue>
     */
    public function check(string $source, string $target): array
    {
        $plain = Text::plain($target);
        $counts = [];
        $terms = [];

        foreach ($this->find($source) as $hit) {
            $counts[$hit->term->source] = ($counts[$hit->term->source] ?? 0) + 1;
            $terms[$hit->term->source] = $hit->term;
        }

        $issues = [];

        foreach ($terms as $name => $term) {
            $allowed = [];

            foreach ($term->accepted() as $translation) {
                $allowed += $this->occurrences($plain, $translation, $term->caseSensitive);
            }

            if (\count($allowed) < $counts[$name]) {
                $issues[] = new TermIssue(TermIssueKind::Missing, $term, $counts[$name], \count($allowed));
            }

            foreach ($term->forbidden as $forbidden) {
                foreach ($this->occurrences($plain, $forbidden, $term->caseSensitive) as $byte => $words) {
                    if (!$this->within($byte, $byte + \strlen($words), $allowed)) {
                        $issues[] = new TermIssue(TermIssueKind::Forbidden, $term, forbidden: $words);
                    }
                }
            }
        }

        return $issues;
    }

    /**
     * Whether the bytes from `$start` to `$end` lie inside one of the occurrences.
     *
     * @param array<int, string> $occurrences
     */
    private function within(int $start, int $end, array $occurrences): bool
    {
        return array_any($occurrences, static fn (string $words, int $byte): bool => $byte <= $start && $end <= $byte + \strlen($words));
    }

    /**
     * The places of a phrase in a text as whole words, by offset in bytes.
     *
     * @return array<int, string>
     */
    private function occurrences(string $text, string $phrase, bool $caseSensitive): array
    {
        $pattern = '/(?<![\p{L}\p{N}_])'.preg_quote($phrase, '/').'(?![\p{L}\p{N}_])/u'.($caseSensitive ? '' : 'i');
        preg_match_all($pattern, $text, $matches, \PREG_OFFSET_CAPTURE);
        $found = [];

        foreach ($matches[0] as [$words, $byte]) {
            $found[$byte] = $words;
        }

        return $found;
    }
}
