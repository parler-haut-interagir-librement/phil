<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Tests\Unit\State;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CalendarImport\EventStatus;
use Phil\CalendarImport\EventVersion;
use Phil\CalendarImport\Exception\InvalidLimits;
use Phil\CalendarImport\Exception\InvalidSource;
use Phil\CalendarImport\Field;
use Phil\CalendarImport\Source\FailureKind;
use Phil\CalendarImport\Source\FetchValidators;
use Phil\CalendarImport\SourceEvent;
use Phil\CalendarImport\State\ChangeReason;
use Phil\CalendarImport\State\RefreshPolicy;
use Phil\CalendarImport\State\Snapshot;
use Phil\CalendarImport\State\SnapshotDiff;
use Phil\CalendarImport\State\SourceFailure;
use Phil\CalendarImport\State\SourceState;
use Phil\Recurrence\RecurrenceRule;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceEvent::class)]
#[CoversClass(EventVersion::class)]
#[CoversClass(Snapshot::class)]
#[CoversClass(SnapshotDiff::class)]
#[CoversClass(SourceState::class)]
#[CoversClass(SourceFailure::class)]
#[CoversClass(RefreshPolicy::class)]
#[CoversClass(InvalidLimits::class)]
#[CoversClass(InvalidSource::class)]
final class StateTest extends TestCase
{
    /**
     * @param list<string>            $tags
     * @param list<DateTimeImmutable> $additions
     * @param list<DateTimeImmutable> $exclusions
     */
    private static function event(string $uid = 'a', string $title = 'Title', int $sequence = 0, ?string $lastModified = null, ?DateTimeImmutable $recurrenceId = null, bool $allDay = false, ?string $description = null, ?string $location = null, ?Url $url = null, EventStatus $status = EventStatus::Confirmed, array $tags = [], ?RecurrenceRule $rule = null, array $additions = [], array $exclusions = []): SourceEvent
    {
        $start = new DateTimeImmutable('2026-12-12T10:00:00', new DateTimeZone('Europe/Paris'));

        return new SourceEvent($uid, $recurrenceId, $sequence, null === $lastModified ? null : new DateTimeImmutable($lastModified), null, $start, $start->modify('+1 hour'), $allDay, false, $start->getTimezone(), $title, $description, $location, $url, $status, $tags, $rule, $additions, $exclusions);
    }

    public function testTheKeyNamesTheOccurrenceAnEventChanges(): void
    {
        self::assertSame('a', self::event()->key());
        self::assertSame('a#20261212T090000Z', self::event(recurrenceId: new DateTimeImmutable('2026-12-12T10:00:00+01:00'))->key());
        self::assertSame('a#20261212', self::event(recurrenceId: new DateTimeImmutable('2026-12-12T00:00:00+01:00'), allDay: true)->key());
        self::assertSame('a', SourceEvent::keyOf('a', null, true));
    }

    public function testTheVersionLeavesTheStampSequenceAndLastChangeOutOfTheFingerprint(): void
    {
        $version = self::event()->version();

        self::assertSame(64, \strlen($version->fingerprint));
        self::assertSame($version->fingerprint, self::event(sequence: 3, lastModified: '2026-11-01T00:00:00Z')->version()->fingerprint);
        self::assertSame(['title', 'location', 'description', 'tags'], array_keys($version->fields));
        self::assertSame(hash('sha256', '"Title"'), $version->fields['title']);
        self::assertSame(hash('sha256', 'null'), $version->fields['location']);
        self::assertSame(hash('sha256', '[]'), $version->fields['tags']);
    }

    /**
     * @return iterable<string, array{SourceEvent}>
     */
    public static function differentEvents(): iterable
    {
        $start = new DateTimeImmutable('2026-12-12T10:00:00', new DateTimeZone('Europe/Paris'));
        $zone = $start->getTimezone();
        $end = $start->modify('+1 hour');

        yield 'title' => [self::event(title: 'Other')];
        yield 'description' => [self::event('a', 'Title', 0, null, null, false, 'Words')];
        yield 'location' => [self::event('a', 'Title', 0, null, null, false, null, 'Hall')];
        yield 'url' => [self::event('a', 'Title', 0, null, null, false, null, null, Url::fromString('https://example.org/'))];
        yield 'status' => [self::event('a', 'Title', 0, null, null, false, null, null, null, EventStatus::Cancelled)];
        yield 'tags' => [self::event('a', 'Title', 0, null, null, false, null, null, null, EventStatus::Confirmed, ['x'])];
        yield 'rule' => [self::event('a', 'Title', 0, null, null, false, null, null, null, EventStatus::Confirmed, [], RecurrenceRule::parse('FREQ=DAILY'))];
        yield 'additions' => [self::event('a', 'Title', 0, null, null, false, null, null, null, EventStatus::Confirmed, [], null, [$end])];
        yield 'exclusions' => [self::event('a', 'Title', 0, null, null, false, null, null, null, EventStatus::Confirmed, [], null, [], [$end])];
        yield 'start' => [new SourceEvent('a', null, 0, null, null, $start->modify('-1 minute'), $end, false, false, $zone, 'Title')];
        yield 'end' => [new SourceEvent('a', null, 0, null, null, $start, $end->modify('+1 minute'), false, false, $zone, 'Title')];
        yield 'zone at the same instant' => [new SourceEvent('a', null, 0, null, null, $start->setTimezone(new DateTimeZone('UTC')), $end, false, false, $zone, 'Title')];
        yield 'whole day' => [new SourceEvent('a', null, 0, null, null, $start, $end, true, false, $zone, 'Title')];
        yield 'floating' => [new SourceEvent('a', null, 0, null, null, $start, $end, false, true, $zone, 'Title')];
    }

    /**
     * The fingerprint is the SHA-256 of the event's content written as JSON, characters and slashes as they are: it
     * stays the same from one version of the library to the next, so that an upgrade does not change every event.
     */
    public function testTheFingerprintIsTheContentWrittenAsJson(): void
    {
        $zone = new DateTimeZone('Europe/Paris');
        $event = self::event(
            title: 'Soirée/jeux',
            url: Url::fromString('https://example.org/a'),
            tags: ['jeux'],
            rule: RecurrenceRule::parse('FREQ=WEEKLY;COUNT=3', $zone),
            additions: [new DateTimeImmutable('2026-12-30T10:00:00', $zone)],
            exclusions: [new DateTimeImmutable('2026-12-19T10:00:00', $zone)],
        );
        $content = '{"start":"2026-12-12T10:00:00+01:00 Europe/Paris","end":"2026-12-12T11:00:00+01:00 Europe/Paris","allDay":false,"floating":false,'
            .'"url":"https://example.org/a","status":"CONFIRMED","rule":"FREQ=WEEKLY;COUNT=3",'
            .'"additions":["2026-12-30T10:00:00+01:00 Europe/Paris"],"exclusions":["2026-12-19T10:00:00+01:00 Europe/Paris"],'
            .'"title":"Soirée/jeux","location":null,"description":null,"tags":["jeux"]}';

        self::assertSame(hash('sha256', $content), $event->version()->fingerprint);
        self::assertSame(hash('sha256', '"Soirée/jeux"'), $event->version()->fields['title']);

        $day = new SourceEvent('a', null, 0, null, null, new DateTimeImmutable('2026-12-12', $zone), new DateTimeImmutable('2026-12-13', $zone), true, true, $zone);
        $content = '{"start":"2026-12-12T00:00:00+01:00 Europe/Paris","end":"2026-12-13T00:00:00+01:00 Europe/Paris","allDay":true,"floating":true,'
            .'"url":null,"status":"CONFIRMED","rule":null,"additions":[],"exclusions":[],"title":null,"location":null,"description":null,"tags":[]}';

        self::assertSame(hash('sha256', $content), $day->version()->fingerprint);
    }

    #[DataProvider('differentEvents')]
    public function testEveryImportedFieldChangesTheFingerprint(SourceEvent $event): void
    {
        self::assertNotSame(self::event()->version()->fingerprint, $event->version()->fingerprint);
    }

    public function testAChangeIsToldBySequenceThenLastChangeThenContent(): void
    {
        $old = self::event(sequence: 1, lastModified: '2026-11-01T00:00:00Z')->version();

        self::assertNull(self::event(sequence: 2, lastModified: '2026-11-02T00:00:00Z')->version()->changedFrom($old));
        self::assertSame(ChangeReason::Sequence, self::event(title: 'New', sequence: 2, lastModified: '2026-11-02T00:00:00Z')->version()->changedFrom($old));
        self::assertSame(ChangeReason::Sequence, self::event(title: 'New', sequence: 0)->version()->changedFrom($old));
        self::assertSame(ChangeReason::LastModified, self::event(title: 'New', sequence: 1, lastModified: '2026-11-02T00:00:00Z')->version()->changedFrom($old));
        self::assertSame(ChangeReason::Content, self::event(title: 'New', sequence: 1, lastModified: '2026-11-01T01:00:00+01:00')->version()->changedFrom($old));
        self::assertSame(ChangeReason::Content, self::event(title: 'New', sequence: 1)->version()->changedFrom($old));
        self::assertSame(ChangeReason::Content, self::event(title: 'New', sequence: 1, lastModified: '2026-11-02T00:00:00Z')->version()->changedFrom(self::event(sequence: 1)->version()));
    }

    public function testTheFieldsAChangeChangesAreNamed(): void
    {
        $old = self::event()->version();

        self::assertSame([Field::Title], self::event(title: 'New')->version()->changedFields($old));
        self::assertSame([Field::Location, Field::Tags], self::event('a', 'Title', 0, null, null, false, null, 'Hall', null, EventStatus::Confirmed, ['x'])->version()->changedFields($old));
        self::assertSame([], self::event('a', 'Title', 0, null, null, false, null, null, null, EventStatus::Cancelled)->version()->changedFields($old));
        self::assertSame(Field::cases(), self::event()->version()->changedFields(new EventVersion(0, null, 'x')));
    }

    public function testASnapshotComparesWithTheLastOne(): void
    {
        $at = new DateTimeImmutable('2026-11-21T10:00:00Z');
        $previous = new Snapshot($at, ['kept' => self::event('kept')->version(), 'changed' => self::event('changed')->version(), '42' => self::event('42')->version(), 'gone' => self::event('gone')->version()]);
        $current = new Snapshot($at, ['new' => self::event('new')->version(), 'kept' => self::event('kept')->version(), '42' => self::event('42', 'New')->version(), 'changed' => self::event('changed', 'New', 1)->version()]);

        $diff = $current->compare($previous);

        self::assertSame(['new'], $diff->added);
        self::assertSame(['42', 'changed'], $diff->changed);
        self::assertSame(['gone'], $diff->removed);
        self::assertSame(ChangeReason::Content, $diff->reason('42'));
        self::assertSame(ChangeReason::Sequence, $diff->reason('changed'));
        self::assertNull($diff->reason('kept'));
        self::assertSame([Field::Title], $diff->fieldsOf('changed'));
        self::assertSame([], $diff->fieldsOf('kept'));
        self::assertFalse($diff->isEmpty());

        self::assertSame(['new', 'kept', '42', 'changed'], $current->compare(null)->added);
        self::assertTrue($current->compare($current)->isEmpty());
        self::assertFalse(new SnapshotDiff(changed: ['a'])->isEmpty());
        self::assertFalse(new SnapshotDiff(removed: ['a'])->isEmpty());
        self::assertSame(['42'], new Snapshot($at, [])->compare(new Snapshot($at, ['42' => self::event('42')->version()]))->removed);
    }

    public function testASnapshotIsWrittenAndReadBack(): void
    {
        $snapshot = new Snapshot(new DateTimeImmutable('2026-11-21T10:00:00-05:00'), ['a' => self::event(lastModified: '2026-11-01T00:00:00Z')->version(), '7' => self::event('7', sequence: 4)->version()]);

        $data = $snapshot->toArray();
        self::assertSame('2026-11-21T10:00:00-05:00', $data['takenAt']);
        self::assertSame('2026-11-01T00:00:00+00:00', $data['versions']['a']['lastModified']);
        self::assertSame(4, $data['versions']['7']['sequence']);

        $decoded = json_decode(json_encode($data, \JSON_THROW_ON_ERROR), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        $read = Snapshot::fromArray($decoded);
        self::assertEquals($snapshot, $read);
        self::assertSame(['a', '7'], array_map(strval(...), array_keys($read->versions)));
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function invalidSnapshots(): iterable
    {
        $version = ['sequence' => 0, 'lastModified' => null, 'fingerprint' => 'f', 'fields' => ['title' => 'h']];

        yield 'no moment' => [['versions' => []], 'A stored snapshot holds the moment it was taken, written as ATOM, and its versions.'];
        yield 'a moment not ATOM' => [['takenAt' => '2026-11-21', 'versions' => []], 'A stored snapshot holds the moment it was taken, written as ATOM, and its versions.'];
        yield 'a moment not a string' => [['takenAt' => 5, 'versions' => []], 'A stored snapshot holds the moment it was taken, written as ATOM, and its versions.'];
        yield 'no versions' => [['takenAt' => '2026-11-21T10:00:00+00:00'], 'A stored snapshot holds the moment it was taken, written as ATOM, and its versions.'];
        yield 'a version not an array' => [['takenAt' => '2026-11-21T10:00:00+00:00', 'versions' => ['a' => 'x']], 'The versions of a stored snapshot are versions by key.'];
        yield 'no sequence' => [['takenAt' => '2026-11-21T10:00:00+00:00', 'versions' => ['a' => ['sequence' => '1'] + $version]], 'A stored version holds a sequence, a fingerprint, fields and a last change or null.'];
        yield 'no fingerprint' => [['takenAt' => '2026-11-21T10:00:00+00:00', 'versions' => ['a' => ['fingerprint' => 1] + $version]], 'A stored version holds a sequence, a fingerprint, fields and a last change or null.'];
        yield 'fields not an array' => [['takenAt' => '2026-11-21T10:00:00+00:00', 'versions' => ['a' => ['fields' => 'x'] + $version]], 'A stored version holds a sequence, a fingerprint, fields and a last change or null.'];
        yield 'a last change not a string' => [['takenAt' => '2026-11-21T10:00:00+00:00', 'versions' => ['a' => ['lastModified' => 5] + $version]], 'A stored version holds a sequence, a fingerprint, fields and a last change or null.'];
        yield 'a last change not ATOM' => [['takenAt' => '2026-11-21T10:00:00+00:00', 'versions' => ['a' => ['lastModified' => 'soon'] + $version]], '"soon" is not a moment written as ATOM.'];
        yield 'a field by number' => [['takenAt' => '2026-11-21T10:00:00+00:00', 'versions' => ['a' => ['fields' => ['h']] + $version]], 'The fields of a stored version are fingerprints by name.'];
        yield 'a field not a string' => [['takenAt' => '2026-11-21T10:00:00+00:00', 'versions' => ['a' => ['fields' => ['title' => 1]] + $version]], 'The fields of a stored version are fingerprints by name.'];
    }

    /**
     * @param array<mixed> $data
     */
    #[DataProvider('invalidSnapshots')]
    public function testAStoredSnapshotThatDoesNotHoldTogetherIsRefused(array $data, string $message): void
    {
        $this->expectException(InvalidSource::class);
        $this->expectExceptionMessageIsOrContains($message);

        Snapshot::fromArray($data);
    }

    public function testAVersionWithoutFieldsIsRead(): void
    {
        $version = EventVersion::fromArray(['sequence' => 1, 'fingerprint' => 'f']);

        self::assertSame([1, null, 'f', []], [$version->sequence, $version->lastModified, $version->fingerprint, $version->fields]);
    }

    public function testAStateKeepsItsSnapshotThroughFailuresAndCountsThem(): void
    {
        $at = new DateTimeImmutable('2026-11-21T10:00:00Z');
        $snapshot = new Snapshot($at, []);
        $validators = new FetchValidators('"v1"');
        $state = new SourceState('club');

        self::assertFalse($state->isFailing());
        self::assertTrue($state->validators->isEmpty());

        $state = $state->succeeded($snapshot, $validators, $at);
        self::assertSame([$snapshot, $validators, $at, $at, null, 0], [$state->snapshot, $state->validators, $state->checkedAt, $state->succeededAt, $state->failure, $state->failures]);

        $later = $at->modify('+1 hour');
        $failure = new SourceFailure(FailureKind::Status, 'down', $later, 503);
        $failed = $state->failed($failure)->failed($failure);
        self::assertTrue($failed->isFailing());
        self::assertSame([$snapshot, $validators, $later, $at, $failure, 2, 'club'], [$failed->snapshot, $failed->validators, $failed->checkedAt, $failed->succeededAt, $failed->failure, $failed->failures, $failed->sourceId]);
        self::assertSame([FailureKind::Status, 'down', 503], [$failure->kind, $failure->message, $failure->status]);

        $other = new FetchValidators('"v2"');
        $back = $failed->notModified($other, $later);
        self::assertSame([$snapshot, $other, $later, $later, null, 0, 'club'], [$back->snapshot, $back->validators, $back->checkedAt, $back->succeededAt, $back->failure, $back->failures, $back->sourceId]);
    }

    public function testASourceIsDueAfterItsIntervalOrItsRetryDoubled(): void
    {
        $at = new DateTimeImmutable('2026-11-21T10:00:00Z');
        $policy = new RefreshPolicy(interval: 3_600, retry: 300, backoff: 2_000);
        $read = new SourceState('a')->succeeded(new Snapshot($at, []), new FetchValidators(), $at);

        self::assertNull($policy->nextAt(null));
        self::assertNull($policy->nextAt(new SourceState('a')));
        self::assertTrue($policy->isDue(null, $at));
        self::assertSame('2026-11-21T11:00:00+00:00', $policy->nextAt($read)?->format(\DATE_ATOM));
        self::assertFalse($policy->isDue($read, $at->modify('+3599 seconds')));
        self::assertTrue($policy->isDue($read, $at->modify('+3600 seconds')));

        $failure = new SourceFailure(FailureKind::Unreachable, 'down', $at);
        $once = $read->failed($failure);
        self::assertSame('2026-11-21T10:05:00+00:00', $policy->nextAt($once)?->format(\DATE_ATOM));
        self::assertSame('2026-11-21T10:10:00+00:00', $policy->nextAt($once->failed($failure))?->format(\DATE_ATOM));
        self::assertSame('2026-11-21T10:20:00+00:00', $policy->nextAt($once->failed($failure)->failed($failure))?->format(\DATE_ATOM));
        self::assertSame('2026-11-21T10:33:20+00:00', $policy->nextAt($once->failed($failure)->failed($failure)->failed($failure))?->format(\DATE_ATOM));

        $many = new SourceState('a', checkedAt: $at, failure: $failure, failures: 1_000);
        self::assertSame([3_600, 300, 86_400], [new RefreshPolicy()->interval, new RefreshPolicy()->retry, new RefreshPolicy()->backoff]);
        self::assertSame('2026-11-22T10:00:00+00:00', new RefreshPolicy()->nextAt($many)?->format(\DATE_ATOM));
        self::assertSame('2026-11-21T10:05:00+00:00', new RefreshPolicy()->nextAt(new SourceState('a', checkedAt: $at, failure: $failure, failures: 0))?->format(\DATE_ATOM));
        self::assertSame('2026-11-21T11:04:00+00:00', new RefreshPolicy(retry: 60, backoff: 604_800)->nextAt(new SourceState('a', checkedAt: $at, failure: $failure, failures: 7))?->format(\DATE_ATOM));
        self::assertSame('2026-11-28T10:00:00+00:00', new RefreshPolicy(retry: 60, backoff: 604_800)->nextAt(new SourceState('a', checkedAt: $at, failure: $failure, failures: 30))?->format(\DATE_ATOM));
    }

    /**
     * @return iterable<string, array{array<string, int>, string}>
     */
    public static function invalidPolicies(): iterable
    {
        yield 'interval low' => [['interval' => 59], 'The interval of a refresh is 60 to 604800, 59 given.'];
        yield 'interval high' => [['interval' => 604_801], 'The interval of a refresh is 60 to 604800, 604801 given.'];
        yield 'retry low' => [['retry' => 59], 'The first retry of a refresh is 60 to 604800, 59 given.'];
        yield 'backoff high' => [['backoff' => 604_801], 'The longest retry of a refresh is 60 to 604800, 604801 given.'];
        yield 'backoff below retry' => [['retry' => 600, 'backoff' => 599], 'The longest retry of a refresh is 600 to 604800, 599 given.'];
    }

    /**
     * @param array<string, int> $arguments
     */
    #[DataProvider('invalidPolicies')]
    public function testAPolicyOutOfRangeIsRefused(array $arguments, string $message): void
    {
        $this->expectException(InvalidLimits::class);
        $this->expectExceptionMessageIsOrContains($message);

        new RefreshPolicy(...$arguments);
    }

    public function testAPolicyAtItsBoundsIsKept(): void
    {
        $policy = new RefreshPolicy(60, 60, 60);
        self::assertSame([60, 60, 60], [$policy->interval, $policy->retry, $policy->backoff]);
        $policy = new RefreshPolicy(604_800, 604_800, 604_800);
        self::assertSame([604_800, 604_800, 604_800], [$policy->interval, $policy->retry, $policy->backoff]);
    }
}
