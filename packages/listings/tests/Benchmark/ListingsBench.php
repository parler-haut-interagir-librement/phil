<?php

declare(strict_types=1);

namespace Phil\Listings\Tests\Benchmark;

use DateTimeImmutable;
use Phil\Listings\Criteria;
use Phil\Listings\Criterion;
use Phil\Listings\KindPolicy;
use Phil\Listings\Listing;
use Phil\Listings\ListingId;
use Phil\Listings\ListingKind;
use Phil\Listings\Location;
use Phil\Listings\MemberId;
use Phil\Listings\Title;

/**
 * A search tests the criteria of every listing it reads against the criteria it wants, and lists only the visible
 * ones; a submitted form builds a title and its criteria.
 */
final readonly class ListingsBench
{
    private Criteria $listing;

    private Criteria $wanted;

    private Listing $published;

    private DateTimeImmutable $now;

    public function __construct()
    {
        $this->now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $this->listing = Criteria::of([
            Criterion::choice('day', ['fri', 'sat', 'sun']),
            Criterion::choice('genre', ['fantasy', 'horror', 'science-fiction', 'mystery']),
            Criterion::range('players', 3, 5),
            Criterion::range('age', 18, 99),
            Criterion::flag('beginners', true),
            Criterion::flag('online', false),
        ]);
        $this->wanted = Criteria::of([
            Criterion::choice('day', ['sat']),
            Criterion::choice('genre', ['mystery', 'western']),
            Criterion::range('players', 4, 6),
            Criterion::flag('beginners', true),
        ]);
        $policy = KindPolicy::of(ListingKind::of('partner'));
        $this->published = Listing::draft(ListingId::of('l1'), $policy, MemberId::of('m1'), Title::of('Players wanted'), $this->listing, Location::area('Plateau', '4200 Saint-Laurent'), null, $this->now)
            ->submit($policy, $this->now);
    }

    public function benchCriteriaMeet(): bool
    {
        return $this->listing->meets($this->wanted);
    }

    public function benchVisibleAndMeets(): bool
    {
        return $this->published->isVisibleAt($this->now) && $this->published->criteria->meets($this->wanted);
    }

    public function benchTitle(): void
    {
        Title::of("  Deux joueurs\u{00A0}recherchés pour une campagne  ");
    }

    public function benchCriteriaBuilt(): void
    {
        Criteria::of([Criterion::choice('day', ['sat', 'sun']), Criterion::range('players', 3, 5), Criterion::flag('beginners', true)]);
    }
}
