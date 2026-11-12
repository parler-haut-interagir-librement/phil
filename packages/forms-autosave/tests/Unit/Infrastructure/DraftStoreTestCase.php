<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\FormsAutosave\Application\Contract\DraftStore;
use Phil\FormsAutosave\Domain\Model\Draft;
use Phil\FormsAutosave\Domain\ValueObject\DraftKey;
use PHPUnit\Framework\TestCase;

/**
 * The contract of every store: a draft by its owner and key, an insertion that fails on an existing draft, a
 * replacement only of the revision read, deletion and the purge of expired drafts.
 */
abstract class DraftStoreTestCase extends TestCase
{
    protected const string ADA = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected const string BOB = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    abstract protected function store(): DraftStore;

    public function testADraftIsKeptUnderItsOwnerAndKey(): void
    {
        $store = $this->store();
        $key = DraftKey::of('abcdefghijklmnop');
        $draft = $this->draft(self::ADA, $key, 'sealed');

        self::assertNull($store->find(self::ADA, $key));
        self::assertTrue($store->insert($draft));
        self::assertFalse($store->insert($this->draft(self::ADA, $key, 'other')));

        $found = $store->find(self::ADA, $key);
        self::assertNotNull($found);
        self::assertSame([self::ADA, 'abcdefghijklmnop', 1, 'sealed', '2026-11-08 15:00:00', '2026-11-08 15:01:00'], [
            $found->owner(), $found->key()->value, $found->revision(), $found->payload(),
            $found->savedAt()->format('Y-m-d H:i:s'), $found->expiresAt()->format('Y-m-d H:i:s'),
        ]);
        self::assertSame('000000', $found->savedAt()->format('u'));
        self::assertSame('000000', $found->expiresAt()->format('u'));
        self::assertNull($store->find(self::BOB, $key));
        self::assertNull($store->find(self::ADA, DraftKey::of('qrstuvwxyzabcdef')));
        self::assertTrue($store->insert($this->draft(self::BOB, $key, 'bob')));
    }

    public function testOnlyTheRevisionReadIsReplaced(): void
    {
        $store = $this->store();
        $key = DraftKey::of('abcdefghijklmnop');
        $first = $this->draft(self::ADA, $key, 'one');
        $store->insert($first);
        $second = $first->next('two', new DateTimeImmutable('2026-11-08 15:00:30 UTC'), 60);

        self::assertTrue($store->replace($first, $second));
        self::assertFalse($store->replace($first, $first->next('stale', new DateTimeImmutable('2026-11-08 15:00:40 UTC'), 60)));
        self::assertFalse($store->replace($this->draft(self::BOB, $key, 'x'), $this->draft(self::BOB, $key, 'x')->next('y', new DateTimeImmutable('2026-11-08 15:00:40 UTC'), 60)));

        $found = $store->find(self::ADA, $key);
        self::assertNotNull($found);
        self::assertSame([2, 'two', '2026-11-08 15:00:30', '2026-11-08 15:01:30'], [$found->revision(), $found->payload(), $found->savedAt()->format('Y-m-d H:i:s'), $found->expiresAt()->format('Y-m-d H:i:s')]);
    }

    public function testAReplacementTouchesOnlyItsOwnersDraft(): void
    {
        $store = $this->store();
        $key = DraftKey::of('abcdefghijklmnop');
        $ada = $this->draft(self::ADA, $key, 'ada');
        $bob = $this->draft(self::BOB, $key, 'bob');
        $store->insert($ada);
        $store->insert($bob);

        self::assertTrue($store->replace($bob, $bob->next('bob 2', new DateTimeImmutable('2026-11-08 15:00:30 UTC'), 60)));
        $kept = $store->find(self::ADA, $key);
        $replaced = $store->find(self::BOB, $key);
        self::assertNotNull($kept);
        self::assertNotNull($replaced);
        self::assertSame(['ada', 1], [$kept->payload(), $kept->revision()]);
        self::assertSame('bob 2', $replaced->payload());
    }

    public function testDeletingAndPurging(): void
    {
        $store = $this->store();
        $key = DraftKey::of('abcdefghijklmnop');
        $store->insert($this->draft(self::ADA, $key, 'x'));
        $store->insert($this->draft(self::BOB, $key, 'x'));
        $store->insert($this->draft(self::ADA, DraftKey::of('qrstuvwxyzabcdef'), 'x', 120));

        $store->delete(self::ADA, $key);
        self::assertNull($store->find(self::ADA, $key));
        self::assertNotNull($store->find(self::BOB, $key));

        self::assertSame(0, $store->purge(new DateTimeImmutable('2026-11-08 15:00:59 UTC')));
        self::assertSame(1, $store->purge(new DateTimeImmutable('2026-11-08 15:01:00 UTC')));
        self::assertNull($store->find(self::BOB, $key));
        self::assertSame(1, $store->purge(new DateTimeImmutable('2026-11-08 10:02:00 America/Montreal')));
        self::assertSame(0, $store->purge(new DateTimeImmutable('2026-11-09 00:00:00 UTC')));
    }

    private function draft(string $owner, DraftKey $key, string $payload, int $seconds = 60): Draft
    {
        return Draft::first($owner, $key, $payload, new DateTimeImmutable('2026-11-08 15:00:00 UTC'), $seconds);
    }
}
