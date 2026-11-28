<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Tests\Unit;

use DateTimeImmutable;
use Phil\CalendarImport\CalendarImporter;
use Phil\CalendarImport\Change;
use Phil\CalendarImport\ChangeKind;
use Phil\CalendarImport\EventStatus;
use Phil\CalendarImport\Exception\InvalidSource;
use Phil\CalendarImport\Exception\SourceUnavailable;
use Phil\CalendarImport\Field;
use Phil\CalendarImport\ICalendar\ICalendarReader;
use Phil\CalendarImport\ICalendar\RejectedEvent;
use Phil\CalendarImport\ICalendar\Rejection;
use Phil\CalendarImport\ImportedDraft;
use Phil\CalendarImport\ImportOutcome;
use Phil\CalendarImport\Override\EditorOverride;
use Phil\CalendarImport\Source\CalendarSource;
use Phil\CalendarImport\Source\FailureKind;
use Phil\CalendarImport\Source\Fetched;
use Phil\CalendarImport\Source\FetchValidators;
use Phil\CalendarImport\State\ChangeReason;
use Phil\CalendarImport\State\RefreshPolicy;
use Phil\CalendarImport\State\SourceState;
use Phil\CalendarImport\Tests\Double\InMemoryOverrideStore;
use Phil\CalendarImport\Tests\Double\InMemorySourceStateStore;
use Phil\CalendarImport\Tests\Double\QueuedEventSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(CalendarImporter::class)]
#[CoversClass(ImportOutcome::class)]
#[CoversClass(ImportedDraft::class)]
#[CoversClass(Change::class)]
#[CoversClass(EditorOverride::class)]
#[CoversClass(InvalidSource::class)]
final class CalendarImporterTest extends TestCase
{
    private InMemorySourceStateStore $states;

    private InMemoryOverrideStore $overrides;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->states = new InMemorySourceStateStore();
        $this->overrides = new InMemoryOverrideStore();
        $this->clock = new MockClock('2026-11-21T10:00:00Z');
    }

    /**
     * @param list<Fetched|SourceUnavailable> $answers
     */
    private function importer(QueuedEventSource|array $answers, RefreshPolicy $policy = new RefreshPolicy()): CalendarImporter
    {
        return new CalendarImporter($answers instanceof QueuedEventSource ? $answers : new QueuedEventSource($answers), $this->states, $this->overrides, $this->clock, $policy);
    }

    private function read(string $etag, string ...$events): Fetched
    {
        $text = "BEGIN:VCALENDAR\r\n".implode("\r\n", array_map(static fn (string $event): string => "BEGIN:VEVENT\r\n".$event."\r\nEND:VEVENT", $events))."\r\nEND:VCALENDAR";

        return Fetched::read(new ICalendarReader()->read($text), new FetchValidators($etag));
    }

    private function source(string $id = 'club'): CalendarSource
    {
        return CalendarSource::of($id, 'https://example.org/'.$id.'.ics');
    }

    /**
     * @param list<Change> $changes
     *
     * @return list<string>
     */
    private function keys(array $changes): array
    {
        return array_map(static fn (Change $change): string => $change->kind->value.' '.$change->key, $changes);
    }

    public function testAFirstReadAddsEveryEventMarkedWithItsSource(): void
    {
        $outcome = $this->importer([$this->read('"v1"', "UID:a\r\nDTSTART:20261212T100000Z\r\nSUMMARY:Games\r\nCATEGORIES:fun", "UID:a\r\nRECURRENCE-ID:20261219T100000Z\r\nDTSTART:20261219T110000Z", "UID:b\r\nDTSTART:20261213T100000Z\r\nSTATUS:CANCELLED")])->refresh($this->source());

        self::assertTrue($outcome->read);
        self::assertFalse($outcome->isFailure());
        self::assertSame(['added a', 'added a#20261219T100000Z', 'added b'], $this->keys($outcome->changes));
        self::assertSame(['a', 'a#20261219T100000Z', 'b'], array_keys($outcome->drafts));

        $draft = $outcome->drafts['a'];
        self::assertSame(['club', 'a', 'a', null, 'Games', ['fun'], false, []], [$draft->sourceId, $draft->key, $draft->uid, $draft->recurrenceId, $draft->title, $draft->tags, $draft->hidden, $draft->overridden]);
        self::assertSame('2026-12-12T10:00:00+00:00', $draft->start->format(\DATE_ATOM));
        self::assertSame('2026-12-12T10:00:00+00:00', $draft->end->format(\DATE_ATOM));
        self::assertFalse($draft->allDay);
        self::assertFalse($draft->floating);
        self::assertSame('UTC', $draft->timezone->getName());
        self::assertNull($draft->description);
        self::assertNull($draft->location);
        self::assertNull($draft->url);
        self::assertNull($draft->rule);
        self::assertSame([[], []], [$draft->additions, $draft->exclusions]);
        self::assertSame(EventStatus::Confirmed, $draft->status);
        self::assertFalse($draft->isCancelled());
        self::assertSame($draft, $outcome->changes[0]->draft);
        self::assertNull($outcome->changes[0]->reason);
        self::assertSame([], $outcome->changes[0]->conflicts);
        self::assertSame('2026-12-19T10:00:00+00:00', $outcome->drafts['a#20261219T100000Z']->recurrenceId?->format(\DATE_ATOM));
        self::assertTrue($outcome->drafts['b']->isCancelled());

        $state = $this->states->states['club'];
        self::assertSame($state, $outcome->state);
        self::assertSame('"v1"', $state->validators->etag);
        self::assertSame(['a', 'a#20261219T100000Z', 'b'], array_keys($state->snapshot->versions ?? []));
        self::assertSame('2026-11-21T10:00:00+00:00', $state->snapshot?->takenAt->format(\DATE_ATOM));
        self::assertSame('2026-11-21T10:00:00+00:00', $state->succeededAt?->format(\DATE_ATOM));
    }

    public function testASecondReadFindsWhatWasAddedChangedAndRemoved(): void
    {
        $source = new QueuedEventSource([
            $this->read('"v1"', "UID:kept\r\nDTSTART:20261212T100000Z", "UID:changed\r\nDTSTART:20261212T100000Z\r\nSEQUENCE:1", "UID:gone\r\nDTSTART:20261212T100000Z", "UID:7\r\nDTSTART:20261212T100000Z"),
            $this->read('"v2"', "UID:new\r\nDTSTART:20261212T100000Z", "UID:changed\r\nDTSTART:20261212T110000Z\r\nSEQUENCE:2", "UID:kept\r\nDTSTART:20261212T100000Z", "UID:7\r\nDTSTART:20261212T100000Z\r\nSUMMARY:x"),
        ]);
        $importer = $this->importer($source);
        $importer->refresh($this->source());

        $this->clock->modify('+1 hour');

        $outcome = $importer->refresh($this->source());

        self::assertSame(['added new', 'changed changed', 'changed 7', 'removed gone'], $this->keys($outcome->changes));
        self::assertSame(ChangeReason::Sequence, $outcome->changes[1]->reason);
        self::assertSame('2026-12-12T11:00:00+00:00', $outcome->changes[1]->draft?->start->format(\DATE_ATOM));
        self::assertSame(ChangeReason::Content, $outcome->changes[2]->reason);
        self::assertNull($outcome->changes[3]->draft);
        self::assertNull($outcome->changes[3]->reason);
        self::assertSame(['added new'], $this->keys($outcome->changesOf(ChangeKind::Added)));
        self::assertSame(['removed gone'], $this->keys($outcome->changesOf(ChangeKind::Removed)));
        self::assertSame(['new', 'changed', 'kept', '7'], array_map(strval(...), array_keys($outcome->drafts)));
        self::assertSame('"v1"', $source->asked[1]?->etag, 'The second read is conditional.');
        self::assertTrue($source->asked[0]?->isEmpty());
    }

    public function testANotModifiedChangesNothingAndKeepsTheSnapshot(): void
    {
        $importer = $this->importer([$this->read('"v1"', "UID:a\r\nDTSTART:20261212T100000Z"), Fetched::notModified(new FetchValidators('"v1b"'))]);
        $first = $importer->refresh($this->source());
        $this->clock->modify('+1 hour');

        $outcome = $importer->refresh($this->source());

        self::assertFalse($outcome->read);
        self::assertFalse($outcome->isFailure());
        self::assertSame([], $outcome->changes);
        self::assertSame([], $outcome->drafts);
        self::assertSame([], $outcome->rejected);
        self::assertSame($first->state->snapshot, $outcome->state->snapshot);
        self::assertSame('"v1b"', $outcome->state->validators->etag);
        self::assertSame('2026-11-21T11:00:00+00:00', $outcome->state->checkedAt?->format(\DATE_ATOM));
        self::assertSame($outcome->state, $this->states->states['club']);
    }

    public function testASourceThatFailsKeepsItsLastStateAndDatesItsFailure(): void
    {
        $importer = $this->importer([$this->read('"v1"', "UID:a\r\nDTSTART:20261212T100000Z"), SourceUnavailable::status('https://example.org/club.ics', 503), $this->read('"v2"', "UID:a\r\nDTSTART:20261212T100000Z")]);
        $first = $importer->refresh($this->source());
        $this->clock->modify('+1 hour');

        $failed = $importer->refresh($this->source());

        self::assertFalse($failed->read);
        self::assertTrue($failed->isFailure());
        self::assertSame([], $failed->changes);
        self::assertSame($first->state->snapshot, $failed->state->snapshot);
        self::assertSame($failed->state, $this->states->states['club']);
        self::assertSame(FailureKind::Status, $failed->state->failure?->kind);
        self::assertSame(503, $failed->state->failure->status);
        self::assertSame('"https://example.org/club.ics" answered with the status 503.', $failed->state->failure->message);
        self::assertSame('2026-11-21T11:00:00+00:00', $failed->state->failure->at->format(\DATE_ATOM));
        self::assertSame(1, $failed->state->failures);

        $this->clock->modify('+1 hour');
        $back = $importer->refresh($this->source());
        self::assertSame([], $back->changes, 'Back after a failure, nothing changed.');
        self::assertFalse($back->isFailure());
        self::assertSame(0, $back->state->failures);
    }

    public function testAnEventRefusedThisTimeKeepsItsLastVersionRatherThanBeingRemoved(): void
    {
        $importer = $this->importer([
            $this->read('"v1"', "UID:a\r\nDTSTART:20261212T100000Z", "UID:b\r\nRECURRENCE-ID:20261212T100000Z\r\nDTSTART:20261212T100000Z"),
            $this->read('"v2"', "UID:a\r\nDTSTART;TZID=Nowhere:20261212T100000", "UID:b\r\nRECURRENCE-ID:20261212T100000Z\r\nDTSTART:bad", "UID:c\r\nDTSTART:bad", 'DTSTART:bad'),
            $this->read('"v3"'),
        ]);
        $importer->refresh($this->source());

        $outcome = $importer->refresh($this->source());

        self::assertSame([], $outcome->changes);
        self::assertSame([], $outcome->drafts);
        self::assertSame([Rejection::UnknownTimeZone, Rejection::InvalidDate, Rejection::InvalidDate, Rejection::MissingUid], array_map(static fn (RejectedEvent $rejected): Rejection => $rejected->reason, $outcome->rejected));
        self::assertSame(['a', 'b#20261212T100000Z'], array_keys($outcome->state->snapshot->versions ?? []));

        self::assertSame(['removed a', 'removed b#20261212T100000Z'], $this->keys($importer->refresh($this->source())->changes));
    }

    public function testADuplicateDoesNotKeepAnOlderVersion(): void
    {
        $importer = $this->importer([
            $this->read('"v1"', "UID:a\r\nDTSTART:20261212T100000Z\r\nSUMMARY:one"),
            $this->read('"v2"', "UID:a\r\nDTSTART:20261212T100000Z\r\nSUMMARY:two\r\nSEQUENCE:2", "UID:a\r\nDTSTART:20261212T100000Z\r\nSUMMARY:one"),
        ]);
        $importer->refresh($this->source());

        $outcome = $importer->refresh($this->source());

        self::assertSame(['changed a'], $this->keys($outcome->changes));
        self::assertSame('two', $outcome->drafts['a']->title);
        self::assertSame(Rejection::DuplicateUid, $outcome->rejected[0]->reason);
    }

    public function testOverridesSurviveEveryReadAndAChangeOfAReplacedFieldIsReported(): void
    {
        $this->overrides->overrides['club'] = [
            new EditorOverride('a', title: 'Our title', tags: ['ours']),
            new EditorOverride('b', hidden: true),
            new EditorOverride('c', location: 'Our hall', description: 'Our words'),
            new EditorOverride('d', location: 'Our place'),
        ];
        $this->overrides->overrides['other'] = [new EditorOverride('a', title: 'Not this one')];
        $importer = $this->importer([
            $this->read('"v1"', "UID:a\r\nDTSTART:20261212T100000Z\r\nSUMMARY:Theirs\r\nLOCATION:Their hall", "UID:b\r\nDTSTART:20261212T100000Z", "UID:c\r\nDTSTART:20261212T100000Z\r\nLOCATION:Hall", "UID:d\r\nDTSTART:20261212T100000Z\r\nSUMMARY:One\r\nLOCATION:Place"),
            $this->read('"v2"', "UID:a\r\nDTSTART:20261212T100000Z\r\nSUMMARY:Theirs, renamed\r\nLOCATION:Their other hall", "UID:b\r\nDTSTART:20261212T100000Z\r\nSUMMARY:now titled", "UID:c\r\nDTSTART:20261212T100000Z\r\nLOCATION:Hall\r\nCATEGORIES:x", "UID:d\r\nDTSTART:20261212T100000Z\r\nSUMMARY:Two\r\nLOCATION:Other place"),
        ]);

        $first = $importer->refresh($this->source());
        self::assertSame(['Our title', 'Their hall', ['ours'], [Field::Title, Field::Tags], false], [$first->drafts['a']->title, $first->drafts['a']->location, $first->drafts['a']->tags, $first->drafts['a']->overridden, $first->drafts['a']->hidden]);
        self::assertTrue($first->drafts['b']->hidden);
        self::assertSame(['Our hall', 'Our words'], [$first->drafts['c']->location, $first->drafts['c']->description]);

        $second = $importer->refresh($this->source());

        self::assertSame(['changed a', 'changed b', 'changed c', 'changed d'], $this->keys($second->changes));
        self::assertSame('Our title', $second->drafts['a']->title, 'The override survives the read.');
        self::assertSame('Their other hall', $second->drafts['a']->location);
        self::assertSame([Field::Title], $second->changes[0]->conflicts, 'The source changed the title the editor replaced.');
        self::assertSame([], $second->changes[1]->conflicts, 'A hidden event replaces no field.');
        self::assertTrue($second->drafts['b']->hidden);
        self::assertSame([], $second->changes[2]->conflicts, 'The location the editor replaced did not change.');
        self::assertSame([Field::Location], $second->changes[3]->conflicts, 'Of the title and the location the source changed, the editor replaced the location.');
    }

    public function testAnOverrideReplacesOnlyTheFieldsItHolds(): void
    {
        $override = new EditorOverride('a', description: 'd');

        self::assertSame([Field::Description], $override->fields());
        self::assertTrue($override->replaces(Field::Description));
        self::assertFalse($override->replaces(Field::Title));
        self::assertFalse($override->replaces(Field::Location));
        self::assertFalse($override->replaces(Field::Tags));
        self::assertSame([Field::Tags], new EditorOverride('a', tags: [])->fields(), 'Empty tags replace the categories too.');
        self::assertSame([Field::Title, Field::Location], new EditorOverride('a', 'T', 'L')->fields());
        self::assertFalse(new EditorOverride('a')->hidden);

        $this->expectException(InvalidSource::class);
        $this->expectExceptionMessageIsOrContains('An override names the key of the event it changes.');
        new EditorOverride('');
    }

    public function testOnlyTheSourcesThatAreDueAreRead(): void
    {
        $source = new QueuedEventSource([$this->read('"v1"'), $this->read('"v2"'), $this->read('"v3"')]);
        $importer = $this->importer($source, new RefreshPolicy(interval: 3_600));
        $this->states->save(new SourceState('fresh', checkedAt: new DateTimeImmutable('2026-11-21T09:30:00Z')));
        $this->states->save(new SourceState('stale', checkedAt: new DateTimeImmutable('2026-11-21T09:00:00Z')));

        $outcomes = $importer->refreshDue([$this->source('fresh'), $this->source('stale'), $this->source('never')]);

        self::assertSame(['stale', 'never'], array_map(static fn (ImportOutcome $outcome): string => $outcome->state->sourceId, $outcomes));
        self::assertCount(2, $source->asked);
        self::assertSame([], $importer->refreshDue([]));
    }
}
