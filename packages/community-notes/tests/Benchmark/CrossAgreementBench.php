<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Tests\Benchmark;

use DateTimeImmutable;
use Phil\CommunityNotes\CrossAgreementRule;
use Phil\CommunityNotes\Memory\InMemoryRatingRepository;
use Phil\CommunityNotes\Rating;
use Phil\CommunityNotes\Verdict;
use Phil\Resource\ResourceReference;

/**
 * The decision of a note rated helpful by fifty raters of two camps, over a history of two hundred notes rated by
 * them all — ten thousand ratings —, looking at the fifty raters for two pairs and for ten; and the worst case, no
 * pair found among fifty raters who all agree, which asks the history of every pair.
 */
final readonly class CrossAgreementBench
{
    private InMemoryRatingRepository $ratings;

    /** @var list<Rating> */
    private array $note;

    /** @var list<Rating> */
    private array $agreeing;

    public function __construct()
    {
        $this->ratings = new InMemoryRatingRepository();
        $at = new DateTimeImmutable('2026-11-12 10:00:00 UTC');
        $note = [];
        $agreeing = [];

        for ($rater = 0; $rater < 50; ++$rater) {
            $member = ResourceReference::of('user', (string) $rater);

            for ($old = 0; $old < 200; ++$old) {
                $this->ratings->save(new Rating('old-'.$old, $member, 0 === $rater % 2 ? Verdict::Helpful : Verdict::NotHelpful, $at));
            }

            $note[] = new Rating('note', $member, Verdict::Helpful, $at->modify('+'.$rater.' seconds'));
            $agreeing[] = new Rating('note', ResourceReference::of('fan', (string) $rater), Verdict::Helpful, $at->modify('+'.$rater.' seconds'));
        }

        $this->note = $note;
        $this->agreeing = $agreeing;
    }

    public function benchTwoPairs(): void
    {
        new CrossAgreementRule()->decide('note', $this->note, $this->ratings);
    }

    public function benchTenPairs(): void
    {
        new CrossAgreementRule(pairs: 10)->decide('note', $this->note, $this->ratings);
    }

    public function benchNoPairAmongFifty(): void
    {
        new CrossAgreementRule()->decide('note', $this->agreeing, $this->ratings);
    }
}
