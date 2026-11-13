<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Tests\Unit;

use Phil\CommunityNotes\CommunityNotes;
use Phil\CommunityNotes\CrossAgreementRule;
use Phil\CommunityNotes\DecisionReason;
use Phil\CommunityNotes\Event\NoteRated;
use Phil\CommunityNotes\Event\NoteStatusChanged;
use Phil\CommunityNotes\Event\NoteWithdrawn;
use Phil\CommunityNotes\Event\NoteWritten;
use Phil\CommunityNotes\Exception\InvalidNote;
use Phil\CommunityNotes\Exception\NoteNotFound;
use Phil\CommunityNotes\Exception\NoteRefused;
use Phil\CommunityNotes\Memory\InMemoryNoteRepository;
use Phil\CommunityNotes\Memory\InMemoryRatingRepository;
use Phil\CommunityNotes\Note;
use Phil\CommunityNotes\NoteStatus;
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
#[CoversClass(NoteRefused::class)]
#[CoversClass(NoteNotFound::class)]
#[CoversClass(InvalidNote::class)]
#[CoversClass(NoteWritten::class)]
#[CoversClass(NoteRated::class)]
#[CoversClass(NoteStatusChanged::class)]
#[CoversClass(NoteWithdrawn::class)]
final class CommunityNotesTest extends TestCase
{
    private MockClock $clock;

    private InMemoryNoteRepository $noteStore;

    private InMemoryRatingRepository $ratingStore;

    private Members $members;

    private RecordingEvents $events;

    private CommunityNotes $notes;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-12 10:00:00 UTC');
        $this->noteStore = new InMemoryNoteRepository();
        $this->ratingStore = new InMemoryRatingRepository();
        $this->members = new Members();
        $this->events = new RecordingEvents();
        $this->notes = new CommunityNotes($this->noteStore, $this->ratingStore, $this->members, $this->clock, new CrossAgreementRule(minRatings: 4, pairs: 1), $this->events);
    }

    public function testANoteIsWrittenRatedAndPublished(): void
    {
        $this->camps();
        $this->events->events = [];
        $note = $this->notes->write($this->ref('post:1'), $this->ref('user:zoe'), 'The photo dates from 2019.');

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $note->id);
        self::assertSame([NoteStatus::NeedsMoreRatings, null, 'The photo dates from 2019.', '2026-11-12 10:00'], [$note->status, $note->decision, $note->text, $note->writtenAt->format('Y-m-d H:i')]);
        self::assertSame([$note->id], array_map(static fn (Note $kept): string => $kept->id, $this->notes->notesOf($this->ref('post:1'))));
        self::assertSame([], $this->notes->shownOf($this->ref('post:1')));

        foreach (['ann', 'bob', 'cid'] as $rater) {
            $this->clock->sleep(1);
            self::assertSame(NoteStatus::NeedsMoreRatings, $this->notes->rate($note->id, $this->ref('user:'.$rater), Verdict::Helpful)->status);
        }

        self::assertSame(DecisionReason::TooFewRatings, $this->notes->get($note->id)->decision?->reason);
        $published = $this->notes->rate($note->id, $this->ref('user:dan'), Verdict::Helpful);

        self::assertSame([NoteStatus::Helpful, true], [$published->status, $published->isShown()]);
        self::assertSame(DecisionReason::CrossAgreement, $published->decision?->reason);
        self::assertSame([$note->id], array_map(static fn (Note $shown): string => $shown->id, $this->notes->shownOf($this->ref('post:1'))));
        self::assertEquals([new NoteWritten($note->id), new NoteRated($note->id), new NoteRated($note->id), new NoteRated($note->id), new NoteRated($note->id), new NoteStatusChanged($note->id)], $this->events->events);
    }

    public function testRatingAgainReplacesTheRatingAndMayUnpublish(): void
    {
        $this->camps();
        $note = $this->notes->write($this->ref('post:1'), $this->ref('user:zoe'), 'Context.');

        foreach (['ann', 'cid', 'bob', 'dan'] as $rater) {
            $this->notes->rate($note->id, $this->ref('user:'.$rater), Verdict::Helpful);
        }

        self::assertSame(NoteStatus::Helpful, $this->notes->get($note->id)->status);
        $this->events->events = [];

        $this->notes->rate($note->id, $this->ref('user:cid'), Verdict::NotHelpful);
        $this->notes->rate($note->id, $this->ref('user:dan'), Verdict::NotHelpful);

        $back = $this->notes->get($note->id);

        self::assertSame([NoteStatus::NeedsMoreRatings, DecisionReason::NoClearMajority, 2, 2], [$back->status, $back->decision?->reason, $back->decision?->helpful, $back->decision?->notHelpful]);
        self::assertCount(4, $this->ratingStore->ofNote($note->id));
        self::assertEquals([new NoteRated($note->id), new NoteRated($note->id), new NoteStatusChanged($note->id)], $this->events->events);
    }

    public function testWhatIsRefused(): void
    {
        $note = $this->notes->write($this->ref('post:1'), $this->ref('user:zoe'), 'Context.');
        $this->members->writersRefused = ['user:new'];
        $this->members->ratersRefused = ['user:new'];

        $this->assertRefused(fn (): Note => $this->notes->write($this->ref('post:1'), $this->ref('user:new'), 'Mine.'), 'You may not write a note here.');
        $this->assertRefused(fn (): Note => $this->notes->rate($note->id, $this->ref('user:new'), Verdict::Helpful), 'You may not rate this note.');
        $this->assertRefused(fn (): Note => $this->notes->rate($note->id, $this->ref('user:zoe'), Verdict::Helpful), 'The author of a note does not rate it.');
        $this->assertRefused(fn (): Note => $this->notes->withdraw($note->id, $this->ref('user:ann')), 'Only the author of a note withdraws it.');

        $withdrawn = $this->notes->withdraw($note->id, $this->ref('user:zoe'));
        self::assertSame([NoteStatus::Withdrawn, false], [$withdrawn->status, $withdrawn->isShown()]);
        self::assertEquals(new NoteWithdrawn($note->id), array_last($this->events->events));
        $this->assertRefused(fn (): Note => $this->notes->rate($note->id, $this->ref('user:ann'), Verdict::Helpful), 'This note is withdrawn.');
        self::assertSame(NoteStatus::Withdrawn, $this->notes->reconsider($note->id)->status);
        self::assertSame([], $this->ratingStore->ofNote($note->id));

        try {
            $this->notes->get('nope');
            self::fail('Found.');
        } catch (NoteNotFound $noteNotFound) {
            self::assertSame('No note is identified by "nope".', $noteNotFound->getMessage());
        }

        $this->expectExceptionObject(InvalidNote::because('The text of a note is a UTF-8 text of 1 to 2000 characters.'));
        $this->notes->write($this->ref('post:1'), $this->ref('user:zoe'), '   ');
    }

    public function testANoteIsReconsideredUnderANewRule(): void
    {
        $this->camps();
        $note = $this->notes->write($this->ref('post:1'), $this->ref('user:zoe'), 'Context.');

        foreach (['ann', 'cid', 'bob'] as $rater) {
            $this->notes->rate($note->id, $this->ref('user:'.$rater), Verdict::Helpful);
        }

        $lenient = new CommunityNotes($this->noteStore, $this->ratingStore, $this->members, $this->clock, new CrossAgreementRule(minRatings: 3, pairs: 1));
        self::assertSame(NoteStatus::Helpful, $lenient->reconsider($note->id)->status);
        self::assertSame(NoteStatus::Helpful, $this->noteStore->get($note->id)?->status);
    }

    public function testANoteIsChecked(): void
    {
        $at = $this->clock->now();
        $cases = [
            ['', 'Text', 'A note is identified by 1 to 64 ASCII letters, digits, "_" or "-".'],
            [str_repeat('i', 65), 'Text', 'A note is identified by 1 to 64 ASCII letters, digits, "_" or "-".'],
            ['a b', 'Text', 'A note is identified by 1 to 64 ASCII letters, digits, "_" or "-".'],
            ['id', '', 'The text of a note is a UTF-8 text of 1 to 2000 characters.'],
            ['id', "\xff", 'The text of a note is a UTF-8 text of 1 to 2000 characters.'],
            ["id\n", 'Text', 'A note is identified by 1 to 64 ASCII letters, digits, "_" or "-".'],
            ['id', str_repeat('é', 2001), 'The text of a note is a UTF-8 text of 1 to 2000 characters.'],
        ];

        foreach ($cases as [$id, $text, $message]) {
            try {
                Note::write($id, $this->ref('post:1'), $this->ref('user:zoe'), $text, $at);
                self::fail($message);
            } catch (InvalidNote $invalidNote) {
                self::assertSame($message, $invalidNote->getMessage());
            }
        }

        self::assertSame(str_repeat('é', 2000), Note::write(str_repeat('i', 64), $this->ref('post:1'), $this->ref('user:zoe'), str_repeat('é', 2000), $at)->text);
        $restored = Note::restore('id', $this->ref('post:1'), $this->ref('user:zoe'), 'T', $at, NoteStatus::NotHelpful, null);
        self::assertSame([NoteStatus::NotHelpful, false, 'post:1', 'user:zoe'], [$restored->status, $restored->isShown(), $restored->subject->toString(), $restored->author->toString()]);
        self::assertSame(2000, Note::MAX_TEXT);
    }

    public function testTheNotesOfASubjectAreInTheOrderTheyWereWritten(): void
    {
        $at = $this->clock->now();
        $this->noteStore->save(Note::write('b', $this->ref('post:1'), $this->ref('user:zoe'), 'T', $at));
        $this->noteStore->save(Note::write('a', $this->ref('post:1'), $this->ref('user:zoe'), 'T', $at));
        $this->noteStore->save(Note::write('c', $this->ref('post:1'), $this->ref('user:zoe'), 'T', $at->modify('-1 second')));
        $this->noteStore->save(Note::write('d', $this->ref('post:2'), $this->ref('user:zoe'), 'T', $at));

        self::assertSame(['c', 'a', 'b'], array_map(static fn (Note $note): string => $note->id, $this->notes->notesOf($this->ref('post:1'))));
        self::assertNull($this->noteStore->get('nope'));
        self::assertNull($this->ratingStore->of('a', $this->ref('user:ann')));
    }

    public function testOnlyTheHelpfulNotesAreShownAsAList(): void
    {
        $this->camps();
        $first = $this->notes->write($this->ref('post:1'), $this->ref('user:zoe'), 'First.');
        $this->clock->sleep(1);
        $second = $this->notes->write($this->ref('post:1'), $this->ref('user:zoe'), 'Second.');

        foreach (['ann', 'cid', 'bob', 'dan'] as $rater) {
            $this->notes->rate($second->id, $this->ref('user:'.$rater), Verdict::Helpful);
        }

        self::assertSame([$first->id, $second->id], array_map(static fn (Note $note): string => $note->id, $this->notes->notesOf($this->ref('post:1'))));
        self::assertEquals([$this->notes->get($second->id)], $this->notes->shownOf($this->ref('post:1')));
    }

    public function testEverythingWorksWithoutAnEventDispatcher(): void
    {
        $notes = new CommunityNotes($this->noteStore, $this->ratingStore, $this->members, $this->clock);
        $note = $notes->write($this->ref('post:1'), $this->ref('user:zoe'), 'Context.');
        $notes->rate($note->id, $this->ref('user:ann'), Verdict::Helpful);
        $notes->withdraw($note->id, $this->ref('user:zoe'));
        $notes->eraseMember($this->ref('user:zoe'));

        self::assertNull($notes->find($note->id));
        self::assertSame([], $this->events->events);
    }

    /**
     * ann and bob against cid and dan on three earlier notes.
     */
    private function camps(): void
    {
        foreach (['old-1', 'old-2', 'old-3'] as $i => $subject) {
            $old = $this->notes->write($this->ref('post:old'.$i), $this->ref('user:yan'), 'Old '.$subject);
            $this->notes->rate($old->id, $this->ref('user:ann'), Verdict::Helpful);
            $this->notes->rate($old->id, $this->ref('user:bob'), Verdict::Helpful);
            $this->notes->rate($old->id, $this->ref('user:cid'), Verdict::NotHelpful);
            $this->notes->rate($old->id, $this->ref('user:dan'), Verdict::NotHelpful);
            self::assertSame(Verdict::NotHelpful, $this->ratingStore->of($old->id, $this->ref('user:dan'))?->verdict);
        }
    }

    private function assertRefused(callable $action, string $message): void
    {
        try {
            $action();
            self::fail('Accepted: '.$message);
        } catch (NoteRefused $noteRefused) {
            self::assertSame($message, $noteRefused->getMessage());
        }
    }

    private function ref(string $reference): ResourceReference
    {
        return ResourceReference::fromString($reference);
    }
}
