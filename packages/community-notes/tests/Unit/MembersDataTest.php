<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Tests\Unit;

use Phil\CommunityNotes\Bridge;
use Phil\CommunityNotes\CommunityNotes;
use Phil\CommunityNotes\CrossAgreementRule;
use Phil\CommunityNotes\Decision;
use Phil\CommunityNotes\Event\NoteErased;
use Phil\CommunityNotes\Event\NoteStatusChanged;
use Phil\CommunityNotes\Memory\InMemoryNoteRepository;
use Phil\CommunityNotes\Memory\InMemoryRatingRepository;
use Phil\CommunityNotes\Note;
use Phil\CommunityNotes\NoteStatus;
use Phil\CommunityNotes\Rating;
use Phil\CommunityNotes\Tests\Unit\Fixture\Members;
use Phil\CommunityNotes\Tests\Unit\Fixture\RecordingEvents;
use Phil\CommunityNotes\Verdict;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(CommunityNotes::class)]
#[CoversClass(Note::class)]
#[CoversClass(InMemoryNoteRepository::class)]
#[CoversClass(InMemoryRatingRepository::class)]
#[CoversClass(NoteErased::class)]
final class MembersDataTest extends TestCase
{
    private MockClock $clock;

    private InMemoryNoteRepository $noteStore;

    private InMemoryRatingRepository $ratingStore;

    private RecordingEvents $events;

    private CommunityNotes $notes;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-12 10:00:00 UTC');
        $this->noteStore = new InMemoryNoteRepository();
        $this->ratingStore = new InMemoryRatingRepository();
        $this->events = new RecordingEvents();
        $this->notes = new CommunityNotes($this->noteStore, $this->ratingStore, new Members(), $this->clock, new CrossAgreementRule(minRatings: 2, pairs: 1, minShared: 1, divergence: [2, 3]), $this->events);
    }

    public function testAMembersNotesAndRatingsAreExported(): void
    {
        self::assertSame([], $this->notes->exportMember($this->ref('user:ann')));

        $first = $this->notes->write($this->ref('post:1'), $this->ref('user:ann'), 'First.');
        $this->clock->sleep(60);
        $second = $this->notes->write($this->ref('post:2'), $this->ref('user:ann'), 'Second.');
        $other = $this->notes->write($this->ref('post:3'), $this->ref('user:bob'), 'Other.');
        $this->clock->sleep(60);
        $this->notes->rate($other->id, $this->ref('user:ann'), Verdict::NotHelpful);

        self::assertSame([
            'notes' => [
                ['id' => $first->id, 'subject' => 'post:1', 'text' => 'First.', 'status' => 'needs_more_ratings', 'written_at' => '2026-11-12T10:00:00+00:00'],
                ['id' => $second->id, 'subject' => 'post:2', 'text' => 'Second.', 'status' => 'needs_more_ratings', 'written_at' => '2026-11-12T10:01:00+00:00'],
            ],
            'ratings' => [
                ['note' => $other->id, 'verdict' => 'not_helpful', 'rated_at' => '2026-11-12T10:02:00+00:00'],
            ],
        ], $this->notes->exportMember($this->ref('user:ann')));
        self::assertSame(['notes' => [], 'ratings' => [['note' => $first->id, 'verdict' => 'helpful', 'rated_at' => '2026-11-12T10:02:00+00:00']]], $this->rateAndExport($first->id, 'user:cid'));
    }

    public function testAMembersDataIsErasedAndNoDecisionNamesThemAnyMore(): void
    {
        // ann and bob disagreed on an earlier note of yan's; both find zoe's note helpful.
        $old = $this->notes->write($this->ref('post:0'), $this->ref('user:yan'), 'Old.');
        $this->notes->rate($old->id, $this->ref('user:ann'), Verdict::Helpful);
        $this->notes->rate($old->id, $this->ref('user:bob'), Verdict::NotHelpful);

        $note = $this->notes->write($this->ref('post:1'), $this->ref('user:zoe'), 'Context.');
        $this->notes->rate($note->id, $this->ref('user:ann'), Verdict::Helpful);
        $this->notes->rate($note->id, $this->ref('user:bob'), Verdict::Helpful);

        $withdrawn = $this->notes->write($this->ref('post:2'), $this->ref('user:zoe'), 'Gone.');
        $this->notes->rate($withdrawn->id, $this->ref('user:ann'), Verdict::Helpful);
        $this->notes->rate($withdrawn->id, $this->ref('user:bob'), Verdict::Helpful);
        $this->notes->withdraw($withdrawn->id, $this->ref('user:zoe'));

        $own = $this->notes->write($this->ref('post:3'), $this->ref('user:ann'), 'Mine.');
        $this->notes->rate($own->id, $this->ref('user:bob'), Verdict::Helpful);

        self::assertSame(NoteStatus::Helpful, $this->notes->get($note->id)->status);
        self::assertSame(['user:ann', 'user:bob'], $this->namesIn($this->notes->get($withdrawn->id)->decision));
        $this->events->events = [];

        $this->notes->eraseMember($this->ref('user:ann'));

        self::assertNull($this->notes->find($own->id));
        self::assertSame([], $this->ratingStore->ofNote($own->id));
        self::assertSame([], $this->notes->exportMember($this->ref('user:ann')));
        self::assertSame([NoteStatus::NeedsMoreRatings, []], [$this->notes->get($note->id)->status, $this->namesIn($this->notes->get($note->id)->decision)]);
        self::assertSame([NoteStatus::Withdrawn, []], [$this->notes->get($withdrawn->id)->status, $this->namesIn($this->notes->get($withdrawn->id)->decision)]);
        self::assertSame(1, $this->notes->get($withdrawn->id)->decision?->ratings());
        self::assertSame(['user:bob'], array_map(static fn (Rating $rating): string => $rating->rater->toString(), $this->ratingStore->ofNote($old->id)));
        self::assertEquals([new NoteErased($own->id), new NoteStatusChanged($note->id)], $this->events->events);
        self::assertSame('Old.', $this->notes->get($old->id)->text);
    }

    public function testErasingAMemberWithNothingChangesNothing(): void
    {
        $note = $this->notes->write($this->ref('post:1'), $this->ref('user:zoe'), 'Context.');
        $this->events->events = [];

        $this->notes->eraseMember($this->ref('user:ann'));

        self::assertSame([], $this->events->events);
        self::assertSame($note, $this->notes->find($note->id));
    }

    public function testANoteErasedBeforeItsRatingsIsSkipped(): void
    {
        $note = $this->notes->write($this->ref('post:1'), $this->ref('user:zoe'), 'Context.');
        $this->notes->rate($note->id, $this->ref('user:ann'), Verdict::Helpful);
        $kept = $this->notes->write($this->ref('post:2'), $this->ref('user:zoe'), 'Kept.');
        $this->notes->rate($kept->id, $this->ref('user:ann'), Verdict::Helpful);
        self::assertSame(1, $this->notes->get($kept->id)->decision?->ratings());
        $this->noteStore->remove($note->id);
        $this->noteStore->remove('nope');

        $this->notes->eraseMember($this->ref('user:ann'));

        self::assertSame([], $this->ratingStore->byRater($this->ref('user:ann')));
        self::assertNull($this->noteStore->get($note->id));
        self::assertSame(0, $this->notes->get($kept->id)->decision?->ratings());
    }

    public function testAWithdrawnNoteIsExplainedAgainWithItsStatus(): void
    {
        $note = Note::write('n', $this->ref('post:1'), $this->ref('user:zoe'), 'T', $this->clock->now())->withdrawn();
        $decision = new CrossAgreementRule()->decide('n', [], $this->ratingStore);
        $explained = $note->explained($decision);

        self::assertSame([NoteStatus::Withdrawn, $decision, 'n', 'T'], [$explained->status, $explained->decision, $explained->id, $explained->text]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rateAndExport(string $note, string $rater): array
    {
        $this->notes->rate($note, $this->ref($rater), Verdict::Helpful);

        return $this->notes->exportMember($this->ref($rater));
    }

    /**
     * @return list<string>
     */
    private function namesIn(?Decision $decision): array
    {
        $names = [];

        foreach ($decision->bridges ?? [] as $bridge) {
            self::assertInstanceOf(Bridge::class, $bridge);
            $names[] = $bridge->first->toString();
            $names[] = $bridge->second->toString();
        }

        return $names;
    }

    private function ref(string $reference): ResourceReference
    {
        return ResourceReference::fromString($reference);
    }
}
