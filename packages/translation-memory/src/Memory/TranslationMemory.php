<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Memory;

use Phil\TranslationMemory\Exception\InvalidConfiguration;
use Phil\TranslationMemory\Fingerprint;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Segment;
use Phil\TranslationMemory\Text;
use Phil\TranslationMemory\Variants;

/**
 * The translation memory: what was translated before, proposed for a new source.
 *
 * An exact match is a unit of the same source fingerprint, scored 1. A fuzzy match is a unit whose plain source is
 * close enough: the store gives the units whose length can still reach the minimum score, the trigrams sort them, and
 * the best of them — the shortlist — are scored by their Levenshtein distance. A fuzzy match of the same words with
 * other tags scores 0.99, below every exact match. Matches come best first, one per target text, at most `$limit`.
 *
 * A regional variant — `fr-CA` among the `Variants` — reads the units of its own language and those of its parent:
 * a `fr` unit is proposed for a `fr-CA` segment, after a `fr-CA` unit of the same score.
 */
final readonly class TranslationMemory
{
    /**
     * @throws InvalidConfiguration when the minimum is not above 0 and at most 1, or the limit or the shortlist is
     *                              below 1
     */
    public function __construct(
        private MemoryStore $store,
        private float $minimum = 0.7,
        private int $limit = 5,
        private int $shortlist = 50,
        private Variants $variants = new Variants(),
    ) {
        if ($minimum <= 0.0 || $minimum > 1.0) {
            throw InvalidConfiguration::because(\sprintf('The minimum score is above 0 and at most 1; got %s.', $minimum));
        }

        if ($limit < 1 || $shortlist < 1) {
            throw InvalidConfiguration::because(\sprintf('The limit and the shortlist are at least 1; got %d and %d.', $limit, $shortlist));
        }
    }

    /**
     * The matches of a source text, best first.
     *
     * @return list<MemoryMatch>
     */
    public function lookup(string $source, LanguageTag $sourceLanguage, LanguageTag $targetLanguage): array
    {
        $fingerprint = Fingerprint::of($source);
        $plain = Text::plain($source);
        $ranked = [];

        foreach ($this->variants->chain($targetLanguage) as $rank => $language) {
            foreach ($this->store->exact($sourceLanguage, $language, $fingerprint) as $unit) {
                $ranked[] = [new MemoryMatch($unit, 1.0, MatchKind::Exact), $rank];
            }

            if (1.0 > $this->minimum) {
                foreach ($this->fuzzy($plain, $fingerprint, $sourceLanguage, $language) as $match) {
                    $ranked[] = [$match, $rank];
                }
            }
        }

        usort($ranked, static fn (array $a, array $b): int => [$b[0]->score, $a[1]] <=> [$a[0]->score, $b[1]]);
        $kept = [];

        foreach ($ranked as [$match]) {
            $kept[$match->unit->target] ??= $match;
        }

        return \array_slice(array_values($kept), 0, $this->limit);
    }

    /**
     * Keeps the translation of a segment, when it is translated or reviewed.
     *
     * @return bool whether the segment was kept
     */
    public function learn(Segment $segment, LanguageTag $sourceLanguage, LanguageTag $targetLanguage, ?string $origin = null): bool
    {
        $target = $segment->isUsable() ? $segment->target : null;

        if (null === $target || !Text::hasWords($segment->source) || !Text::hasWords($target)) {
            return false;
        }

        $this->store->add(new MemoryUnit($sourceLanguage, $targetLanguage, $segment->source, $target, $origin));

        return true;
    }

    /**
     * @return list<MemoryMatch>
     */
    private function fuzzy(string $plain, Fingerprint $fingerprint, LanguageTag $sourceLanguage, LanguageTag $targetLanguage): array
    {
        $trigrams = Similarity::trigrams($plain);
        $length = mb_strlen($plain);
        $sorted = [];

        foreach ($this->store->candidates($sourceLanguage, $targetLanguage, (int) ceil($length * $this->minimum - Similarity::EPSILON), (int) floor($length / $this->minimum + Similarity::EPSILON)) as $unit) {
            if (!$unit->fingerprint->equals($fingerprint)) {
                $sorted[] = [Similarity::dice($trigrams, Similarity::trigrams($unit->plain)), $unit];
            }
        }

        usort($sorted, static fn (array $a, array $b): int => $b[0] <=> $a[0]);
        $matches = [];

        foreach (\array_slice($sorted, 0, $this->shortlist) as [, $unit]) {
            $score = Similarity::score($plain, $unit->plain, $this->minimum);

            if ($score >= $this->minimum) {
                $matches[] = new MemoryMatch($unit, min($score, 0.99), MatchKind::Fuzzy);
            }
        }

        return $matches;
    }
}
