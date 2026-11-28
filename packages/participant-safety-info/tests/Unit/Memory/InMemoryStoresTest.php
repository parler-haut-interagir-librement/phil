<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Tests\Unit\Memory;

use DateTimeImmutable;
use Phil\ParticipantSafetyInfo\AccessReason;
use Phil\ParticipantSafetyInfo\AccessRecord;
use Phil\ParticipantSafetyInfo\Memory\InMemoryAccessJournal;
use Phil\ParticipantSafetyInfo\Memory\InMemorySafetyInfoStore;
use Phil\ParticipantSafetyInfo\SealedSafetyInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemorySafetyInfoStore::class)]
#[CoversClass(InMemoryAccessJournal::class)]
final class InMemoryStoresTest extends TestCase
{
    public function testTheStoreKeepsOneRecordPerEventAndParticipant(): void
    {
        $store = new InMemorySafetyInfoStore();
        $at = new DateTimeImmutable('2026-12-01');
        $store->save(new SealedSafetyInfo('e', 'p', 'one', $at, $at));
        $store->save(new SealedSafetyInfo('e', 'p', 'two', $at, $at));
        $store->save(new SealedSafetyInfo('e', 'q', 'three', $at, $at));

        self::assertSame('two', $store->get('e', 'p')?->sealed);
        self::assertNull($store->get('f', 'p'));
        self::assertTrue($store->delete('e', 'p'));
        self::assertFalse($store->delete('e', 'p'));
        self::assertNull($store->get('e', 'p'));
        self::assertSame('three', $store->get('e', 'q')?->sealed);
    }

    public function testTheEventAndTheParticipantStaySeparate(): void
    {
        $store = new InMemorySafetyInfoStore();
        $at = new DateTimeImmutable('2026-12-01');
        $store->save(new SealedSafetyInfo('ab', 'c', 'one', $at, $at));

        self::assertNull($store->get('a', 'bc'));
        self::assertFalse($store->delete('a', 'bc'));
        self::assertSame('one', $store->get('ab', 'c')?->sealed);
    }

    public function testThePurgeDeletesWhatIsKeptUntilBeforeTheDateWithinTheLimit(): void
    {
        $store = new InMemorySafetyInfoStore();
        $old = new DateTimeImmutable('2026-12-01');
        $now = new DateTimeImmutable('2026-12-02');
        $store->save(new SealedSafetyInfo('e', 'a', 's', $old, $old));
        $store->save(new SealedSafetyInfo('e', 'b', 's', $old, $now));
        $store->save(new SealedSafetyInfo('e', 'c', 's', $old, $old));
        $store->save(new SealedSafetyInfo('e', 'd', 's', $old, $old));

        self::assertSame(2, $store->deleteRetainedUntilBefore($now, 2));
        self::assertNull($store->get('e', 'a'));
        self::assertNotNull($store->get('e', 'b'));
        self::assertNull($store->get('e', 'c'));
        self::assertNotNull($store->get('e', 'd'));
        self::assertSame(1, $store->deleteRetainedUntilBefore($now, 10));
        self::assertNotNull($store->get('e', 'b'));
    }

    public function testTheJournalReadsTheLatestReadingsOfAParticipantFirst(): void
    {
        $journal = new InMemoryAccessJournal();
        $first = new AccessRecord('e', 'p', 'one', AccessReason::Responsible, new DateTimeImmutable('2026-12-01'));
        $other = new AccessRecord('e', 'q', 'two', AccessReason::Responsible, new DateTimeImmutable('2026-12-01'));
        $otherEvent = new AccessRecord('f', 'p', 'four', AccessReason::Responsible, new DateTimeImmutable('2026-12-01'));
        $second = new AccessRecord('e', 'p', 'three', AccessReason::Responsible, new DateTimeImmutable('2026-12-02'));
        $journal->record($first);
        $journal->record($other);
        $journal->record($otherEvent);
        $journal->record($second);

        self::assertSame([$second, $first], $journal->recordsOf('e', 'p', 10));
        self::assertSame([$second], $journal->recordsOf('e', 'p', 1));
    }
}
