<?php

declare(strict_types=1);

namespace Phil\CommunityNotes;

use InvalidArgumentException;
use Phil\CommunityNotes\Contract\RatingHistory;
use Phil\Resource\ResourceReference;

/**
 * The rule that decides a note: enough ratings, a verdict holding its share of them, and enough pairs of raters who
 * usually disagree giving that verdict together.
 *
 * Two raters usually disagree when, on the other notes they both rated — at least `$minShared` of them —, they gave
 * the same verdict on less than the share `$divergence` of them. Among the raters of the leading verdict, taken in
 * the order they rated, the earliest first, at most `$maxRaters`, the rule pairs them two by two, each rater in one
 * pair at most, so that no single rater carries a decision; it stops at `$pairs` pairs. A note gets the verdict when it
 * finds them; otherwise it needs more ratings.
 *
 * Every number is compared in integers, and every decision lists the pairs that made it. Looking at `$maxRaters`
 * raters asks the history of at most `$maxRaters × ($maxRaters − 1) ÷ 2` pairs.
 */
final readonly class CrossAgreementRule
{
    public const int MAX_RATERS = 200;

    /**
     * @param array{int, int} $share      the share of the ratings the leading verdict holds at least, above one half
     * @param array{int, int} $divergence the share of agreement under which two raters usually disagree
     *
     * @throws InvalidArgumentException when a number is out of its range
     */
    public function __construct(
        public int $minRatings = 5,
        public array $share = [2, 3],
        public int $pairs = 2,
        public int $minShared = 3,
        public array $divergence = [1, 2],
        public int $maxRaters = 50,
    ) {
        if ($minRatings < 1 || $pairs < 1 || $minShared < 1) {
            throw new InvalidArgumentException('A rule asks for at least one rating, one pair and one note shared.');
        }

        [$shareNumerator, $shareDenominator] = $share;

        if ($shareDenominator < 1 || 2 * $shareNumerator <= $shareDenominator || $shareNumerator > $shareDenominator) {
            throw new InvalidArgumentException(\sprintf('The share of the leading verdict is above one half and at most all the ratings, not %d/%d.', $shareNumerator, $shareDenominator));
        }

        [$divergenceNumerator, $divergenceDenominator] = $divergence;

        if ($divergenceDenominator < 1 || $divergenceNumerator < 1 || $divergenceNumerator > $divergenceDenominator) {
            throw new InvalidArgumentException(\sprintf('The share of agreement of two raters who usually disagree is above 0 and at most 1, not %d/%d.', $divergenceNumerator, $divergenceDenominator));
        }

        if ($maxRaters < 2 * $pairs || $maxRaters > self::MAX_RATERS) {
            throw new InvalidArgumentException(\sprintf('A rule looks at %d to %d raters, not %d.', 2 * $pairs, self::MAX_RATERS, $maxRaters));
        }
    }

    /**
     * Decides the note from its ratings and the history of its raters.
     *
     * @param list<Rating> $ratings the ratings of one note
     */
    public function decide(string $note, array $ratings, RatingHistory $history): Decision
    {
        $helpful = \count(array_filter($ratings, static fn (Rating $rating): bool => Verdict::Helpful === $rating->verdict));
        $notHelpful = \count($ratings) - $helpful;

        if (\count($ratings) < $this->minRatings) {
            return new Decision(NoteStatus::NeedsMoreRatings, DecisionReason::TooFewRatings, $helpful, $notHelpful, null, [], $this);
        }

        $verdict = match (true) {
            $this->holdsShare($helpful, \count($ratings)) => Verdict::Helpful,
            $this->holdsShare($notHelpful, \count($ratings)) => Verdict::NotHelpful,
            default => null,
        };

        if (null === $verdict) {
            return new Decision(NoteStatus::NeedsMoreRatings, DecisionReason::NoClearMajority, $helpful, $notHelpful, null, [], $this);
        }

        $bridges = $this->bridges($note, array_filter($ratings, static fn (Rating $rating): bool => $verdict === $rating->verdict), $history);

        if (\count($bridges) < $this->pairs) {
            return new Decision(NoteStatus::NeedsMoreRatings, DecisionReason::NoCrossAgreement, $helpful, $notHelpful, $verdict, $bridges, $this);
        }

        return new Decision(
            Verdict::Helpful === $verdict ? NoteStatus::Helpful : NoteStatus::NotHelpful,
            DecisionReason::CrossAgreement,
            $helpful,
            $notHelpful,
            $verdict,
            $bridges,
            $this,
        );
    }

    /**
     * @return array{min_ratings: int, share: string, pairs: int, min_shared: int, divergence: string, max_raters: int}
     */
    public function toArray(): array
    {
        return [
            'min_ratings' => $this->minRatings,
            'share' => $this->share[0].'/'.$this->share[1],
            'pairs' => $this->pairs,
            'min_shared' => $this->minShared,
            'divergence' => $this->divergence[0].'/'.$this->divergence[1],
            'max_raters' => $this->maxRaters,
        ];
    }

    private function holdsShare(int $votes, int $ratings): bool
    {
        return $votes * $this->share[1] >= $this->share[0] * $ratings;
    }

    /**
     * Pairs of raters who usually disagree, each rater in one pair at most, the earliest raters first.
     *
     * @param array<Rating> $ratings the ratings of the leading verdict, in any order
     *
     * @return list<Bridge>
     */
    private function bridges(string $note, array $ratings, RatingHistory $history): array
    {
        usort($ratings, static fn (Rating $a, Rating $b): int => [$a->ratedAt->getTimestamp(), $a->rater->toString()] <=> [$b->ratedAt->getTimestamp(), $b->rater->toString()]);
        $raters = array_map(static fn (Rating $rating): ResourceReference => $rating->rater, \array_slice($ratings, 0, $this->maxRaters));
        /** @var array<int, bool> $paired whether each rater, by position, is in a pair */
        $paired = [];
        $bridges = [];

        foreach ($raters as $i => $first) {
            foreach (\array_slice($raters, $i + 1, null, true) as $j => $second) {
                if (($paired[$i] ?? false) || ($paired[$j] ?? false)) {
                    continue;
                }

                $past = $history->between($first, $second, $note);

                if ($past->shared >= $this->minShared && $past->agreesLessThan($this->divergence[0], $this->divergence[1])) {
                    $bridges[] = new Bridge($first, $second, $past);
                    $paired[$i] = true;
                    $paired[$j] = true;

                    if (\count($bridges) === $this->pairs) {
                        return $bridges;
                    }
                }
            }
        }

        return $bridges;
    }
}
