<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Tests\Unit\Memory;

use DateTimeImmutable;
use Phil\CommunityNotes\Memory\InMemoryNoteRepository;
use Phil\CommunityNotes\Memory\InMemoryRatingRepository;
use Phil\CommunityNotes\Note;
use Phil\CommunityNotes\Rating;
use Phil\CommunityNotes\Verdict;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryRatingRepository::class)]
#[CoversClass(InMemoryNoteRepository::class)]
final class InMemoryRepositoriesTest extends TestCase
{
    public function testTheHistoryOfTwoRatersCountsTheNotesBothRated(): void
    {
        $ratings = new InMemoryRatingRepository();
        $at = new DateTimeImmutable('2026-11-12 10:00:00 UTC');
        $ann = ResourceReference::of('user', 'ann');
        $bob = ResourceReference::of('user', 'bob');

        $ratings->save(new Rating('only-ann', $ann, Verdict::Helpful, $at));
        $ratings->save(new Rating('only-bob', $bob, Verdict::Helpful, $at));
        $ratings->save(new Rating('decided', $ann, Verdict::Helpful, $at));
        $ratings->save(new Rating('decided', $bob, Verdict::Helpful, $at));
        $ratings->save(new Rating('same', $ann, Verdict::NotHelpful, $at));
        $ratings->save(new Rating('same', $bob, Verdict::NotHelpful, $at));
        $ratings->save(new Rating('other', $ann, Verdict::Helpful, $at));
        $ratings->save(new Rating('other', $bob, Verdict::NotHelpful, $at));

        $history = $ratings->between($ann, $bob, 'decided');

        self::assertSame([2, 1], [$history->shared, $history->agreed]);
        self::assertSame([3, 2], [$ratings->between($bob, $ann, 'nope')->shared, $ratings->between($bob, $ann, 'nope')->agreed]);
        self::assertSame(['only-ann', 'decided', 'same', 'other'], array_map(static fn (Rating $rating): string => $rating->note, $ratings->byRater($ann)));
    }

    public function testTheNotesOfAnAuthorAreAListTheOldestFirst(): void
    {
        $notes = new InMemoryNoteRepository();
        $at = new DateTimeImmutable('2026-11-12 10:00:00 UTC');
        $notes->save(Note::write('z', ResourceReference::of('post', '1'), ResourceReference::of('user', 'bob'), 'T', $at));
        $notes->save(Note::write('b', ResourceReference::of('post', '1'), ResourceReference::of('user', 'ann'), 'T', $at));
        $notes->save(Note::write('a', ResourceReference::of('post', '2'), ResourceReference::of('user', 'ann'), 'T', $at->modify('-1 second')));

        self::assertSame(['a', 'b'], array_map(static fn (Note $note): string => $note->id, $notes->byAuthor(ResourceReference::of('user', 'ann'))));
    }
}
