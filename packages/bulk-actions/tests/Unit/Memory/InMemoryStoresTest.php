<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Unit\Memory;

use DateTimeImmutable;
use Phil\BulkActions\Change\ChangeSet;
use Phil\BulkActions\Memory\InMemoryRunStore;
use Phil\BulkActions\Memory\InMemorySelectionStore;
use Phil\BulkActions\Run\BulkRun;
use Phil\BulkActions\Run\RunId;
use Phil\BulkActions\Selection\PendingSelection;
use Phil\BulkActions\Selection\Selection;
use Phil\BulkActions\Selection\SelectionToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemorySelectionStore::class)]
#[CoversClass(InMemoryRunStore::class)]
final class InMemoryStoresTest extends TestCase
{
    public function testASelectionIsPeekedManyTimesAndTakenOnce(): void
    {
        $store = new InMemorySelectionStore();
        $pending = $this->pending(SelectionToken::generate(), '2026-11-21 10:15');
        $store->save($pending);

        self::assertEquals($pending, $store->peek($pending->fingerprint));
        self::assertEquals($pending, $store->peek($pending->fingerprint));
        self::assertNotSame($pending, $store->peek($pending->fingerprint), 'The store keeps what toArray() gives.');
        self::assertEquals($pending, $store->take($pending->fingerprint));
        self::assertNull($store->take($pending->fingerprint));
        self::assertNull($store->peek($pending->fingerprint));
        self::assertSame(0, $store->count());
    }

    public function testExpiredSelectionsAreForgotten(): void
    {
        $store = new InMemorySelectionStore();
        $old = $this->pending(SelectionToken::generate(), '2026-11-21 10:00');
        $edge = $this->pending(SelectionToken::generate(), '2026-11-21 10:05');
        $new = $this->pending(SelectionToken::generate(), '2026-11-21 10:30');
        $store->save($old);
        $store->save($edge);
        $store->save($new);

        $store->forgetExpired(new DateTimeImmutable('2026-11-21 10:05+00:00'));

        self::assertNull($store->peek($old->fingerprint));
        self::assertNotNull($store->peek($edge->fingerprint));
        self::assertNotNull($store->peek($new->fingerprint));
        self::assertSame(2, $store->count());
    }

    public function testARunIsRebuiltOnEachRead(): void
    {
        $store = new InMemoryRunStore();
        $run = BulkRun::confirm(RunId::generate(), 'products', 'edit', 'ana', ChangeSet::of(['price' => 1]), Selection::identifiers(['a']), new DateTimeImmutable('2026-11-21 10:00+00:00'));
        $store->save($run);

        self::assertNotSame($run, $store->get($run->id));
        self::assertEquals($run->toArray(), $store->get($run->id)?->toArray());
        self::assertNull($store->get(RunId::generate()));
    }

    private function pending(SelectionToken $token, string $expiresAt): PendingSelection
    {
        return new PendingSelection($token->fingerprint(), 'ana', 'products', 'edit', Selection::identifiers(['a']), new DateTimeImmutable($expiresAt.'+00:00'));
    }
}
