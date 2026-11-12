<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\FormsAutosave\Application\Contract\DraftStore;
use Phil\FormsAutosave\Application\DraftCopy;
use Phil\FormsAutosave\Application\DraftKeys;
use Phil\FormsAutosave\Application\Drafts;
use Phil\FormsAutosave\Application\Exception\DraftTooLarge;
use Phil\FormsAutosave\Application\Exception\RevisionConflict;
use Phil\FormsAutosave\Application\PlainSealer;
use Phil\FormsAutosave\Domain\Model\Draft;
use Phil\FormsAutosave\Domain\ValueObject\DraftContent;
use Phil\FormsAutosave\Domain\ValueObject\DraftKey;
use Phil\FormsAutosave\Infrastructure\Memory\InMemoryDraftStore;
use Phil\FormsAutosave\Tests\Unit\Fixture\ContextSealer;
use Phil\FormsAutosave\Tests\Unit\Fixture\RacingStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(Drafts::class)]
#[CoversClass(DraftCopy::class)]
#[CoversClass(RevisionConflict::class)]
#[CoversClass(DraftTooLarge::class)]
#[CoversClass(PlainSealer::class)]
final class DraftsTest extends TestCase
{
    private MockClock $clock;

    private DraftKeys $keys;

    private DraftKey $key;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-08 15:00:00 UTC');
        $this->keys = new DraftKeys('secret');
        $this->key = DraftKey::of('abcdefghijklmnop');
    }

    public function testTheDefaultsAreAWeekAndSixtyFourKibibytes(): void
    {
        self::assertSame(604800, Drafts::SECONDS);
        self::assertSame(65536, Drafts::MAX_BYTES);

        $store = new InMemoryDraftStore();
        new Drafts($store, new PlainSealer(), $this->keys, $this->clock)->save('ada', $this->key, null, DraftContent::of('v', ['a' => [str_repeat('x', 65000)]]));
        $draft = $store->find($this->keys->owner('ada'), $this->key);
        self::assertNotNull($draft);
        self::assertSame('2026-11-15 15:00:00', $draft->expiresAt()->format('Y-m-d H:i:s'));

        $this->expectException(DraftTooLarge::class);
        new Drafts($store, new PlainSealer(), $this->keys, $this->clock)->save('ada', $this->key, 1, DraftContent::of('v', ['a' => [str_repeat('x', 65536)]]));
    }

    public function testADraftIsSavedSealedUnderItsOwnersHashAndReadBack(): void
    {
        $store = new InMemoryDraftStore();
        $drafts = new Drafts($store, new ContextSealer(), $this->keys, $this->clock, 60);

        self::assertNull($drafts->read('ada', $this->key));
        self::assertSame(1, $drafts->save('ada', $this->key, null, DraftContent::of('v1', ['title' => ['Hello']])));

        $owner = $this->keys->owner('ada');
        $stored = $store->find($owner, $this->key);
        self::assertNotNull($stored);
        self::assertSame('phil_form_draft:'.$owner.':abcdefghijklmnop|{"version":"v1","fields":{"title":["Hello"]}}', $stored->payload());
        self::assertSame('2026-11-08 15:01:00', $stored->expiresAt()->format('Y-m-d H:i:s'));

        $copy = $drafts->read('ada', $this->key);
        self::assertNotNull($copy);
        self::assertSame(1, $copy->revision);
        self::assertSame('2026-11-08 15:00:00', $copy->savedAt->format('Y-m-d H:i:s'));
        self::assertSame('v1', $copy->content->version);
        self::assertSame(['title' => ['Hello']], $copy->content->fields);
        self::assertNull($drafts->read('bob', $this->key), 'another user has their own drafts');
    }

    public function testEachSaveNamesTheRevisionItReplaces(): void
    {
        $drafts = new Drafts(new InMemoryDraftStore(), new PlainSealer(), $this->keys, $this->clock, 60);
        $drafts->save('ada', $this->key, null, DraftContent::of('v', ['a' => ['1']]));

        $this->clock->sleep(30);

        self::assertSame(2, $drafts->save('ada', $this->key, 1, DraftContent::of('v', ['a' => ['2']])));
        self::assertSame(3, $drafts->save('ada', $this->key, 2, DraftContent::of('v', ['a' => ['3']])));
        $copy = $drafts->read('ada', $this->key);
        self::assertNotNull($copy);
        self::assertSame(['a' => ['3']], $copy->content->fields);
        self::assertSame('2026-11-08 15:00:30', $copy->savedAt->format('Y-m-d H:i:s'));

        foreach ([null, 2, 4] as $stale) {
            try {
                $drafts->save('ada', $this->key, $stale, DraftContent::of('v', ['a' => ['x']]));
                self::fail('a save of another revision is refused');
            } catch (RevisionConflict $conflict) {
                self::assertSame(3, $conflict->current);
                self::assertSame('The draft is at revision 3.', $conflict->getMessage());
            }
        }
    }

    public function testAnExpiredDraftCountsAsNone(): void
    {
        $store = new RacingStore();
        $drafts = new Drafts($store, new PlainSealer(), $this->keys, $this->clock, 60);
        $drafts->save('ada', $this->key, null, DraftContent::of('v', ['a' => ['1']]));

        $this->clock->sleep(59);
        self::assertNotNull($drafts->read('ada', $this->key));
        $this->clock->sleep(1);

        self::assertNull($drafts->read('ada', $this->key));
        self::assertSame([], $store->deleted, 'reading leaves an expired draft to the purge');
        self::assertSame(1, $drafts->save('ada', $this->key, 7, DraftContent::of('v', ['a' => ['2']])));
        self::assertSame(['abcdefghijklmnop'], $store->deleted);
        self::assertSame(1, $drafts->save('bob', $this->key, null, DraftContent::of('v', [])));
        self::assertSame(['abcdefghijklmnop'], $store->deleted, 'nothing to delete before a first save');
    }

    public function testADraftThatNoLongerOpensIsDeleted(): void
    {
        $store = new InMemoryDraftStore();
        $owner = $this->keys->owner('ada');
        $drafts = new Drafts($store, new ContextSealer(), $this->keys, $this->clock, 60);

        foreach (['moved|{"version":"v","fields":{}}', 'phil_form_draft:'.$owner.':abcdefghijklmnop|not json'] as $payload) {
            $store->insert(Draft::first($owner, $this->key, $payload, $this->clock->now(), 60));

            self::assertNull($drafts->read('ada', $this->key));
            self::assertNull($store->find($owner, $this->key));
        }
    }

    public function testASaveThatLosesARaceIsAConflict(): void
    {
        $owner = $this->keys->owner('ada');
        $other = function (InMemoryDraftStore $store) use ($owner): void {
            $store->insert(Draft::first($owner, $this->key, '{}', $this->clock->now(), 60)->next('{}', $this->clock->now(), 60)->next('{}', $this->clock->now(), 60));
        };
        $drafts = new Drafts(new RacingStore($other), new PlainSealer(), $this->keys, $this->clock, 60);

        try {
            $drafts->save('ada', $this->key, null, DraftContent::of('v', []));
            self::fail('a first save that meets another is refused');
        } catch (RevisionConflict $revisionConflict) {
            self::assertSame(3, $revisionConflict->current);
        }

        $replaced = new RacingStore(function (InMemoryDraftStore $store) use ($owner): void {
            $read = $store->find($owner, $this->key);
            self::assertNotNull($read);
            $store->replace($read, $read->next('{}', $this->clock->now(), 60));
        });
        $racing = new Drafts($replaced, new PlainSealer(), $this->keys, $this->clock, 60);
        $replaced->inner->insert(Draft::first($owner, $this->key, '{}', $this->clock->now(), 60));

        try {
            $racing->save('ada', $this->key, 1, DraftContent::of('v', []));
            self::fail('a replacement that meets another is refused');
        } catch (RevisionConflict $revisionConflict) {
            self::assertSame(2, $revisionConflict->current);
        }

        $gone = new RacingStore(static fn (InMemoryDraftStore $store) => $store->delete($owner, DraftKey::of('abcdefghijklmnop')));
        $gone->inner->insert(Draft::first($owner, $this->key, '{}', $this->clock->now(), 60));

        try {
            new Drafts($gone, new PlainSealer(), $this->keys, $this->clock, 60)->save('ada', $this->key, 1, DraftContent::of('v', []));
            self::fail('a replacement of a draft deleted meanwhile is refused');
        } catch (RevisionConflict $revisionConflict) {
            self::assertNull($revisionConflict->current);
            self::assertSame('The draft changed while it was saved.', $revisionConflict->getMessage());
        }
    }

    public function testAFirstSaveRefusedByAStoreThatThenHasNothingIsAConflictWithoutRevision(): void
    {
        $store = new class implements DraftStore {
            public function find(string $owner, DraftKey $key): ?Draft
            {
                return null;
            }

            public function insert(Draft $draft): bool
            {
                return false;
            }

            public function replace(Draft $read, Draft $next): bool
            {
                return false;
            }

            public function delete(string $owner, DraftKey $key): void
            {
            }

            public function purge(DateTimeImmutable $now): int
            {
                return 0;
            }
        };

        try {
            new Drafts($store, new PlainSealer(), $this->keys, $this->clock, 60)->save('ada', $this->key, null, DraftContent::of('v', []));
            self::fail('a first save the store refuses is a conflict');
        } catch (RevisionConflict $revisionConflict) {
            self::assertNull($revisionConflict->current);
        }
    }

    public function testTheSizeIsThatOfTheEncodedContent(): void
    {
        $content = DraftContent::of('v', ['a' => ['x']]);
        $bytes = \strlen($content->toJson());
        $drafts = new Drafts(new InMemoryDraftStore(), new PlainSealer(), $this->keys, $this->clock, 60, $bytes);
        self::assertSame(1, $drafts->save('ada', $this->key, null, $content));

        $this->expectException(DraftTooLarge::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A draft is at most %d bytes.', $bytes - 1));

        new Drafts(new InMemoryDraftStore(), new PlainSealer(), $this->keys, $this->clock, 60, $bytes - 1)->save('ada', $this->key, null, $content);
    }

    public function testDiscardingAndPurging(): void
    {
        $store = new InMemoryDraftStore();
        $drafts = new Drafts($store, new PlainSealer(), $this->keys, $this->clock, 60);
        $other = DraftKey::of('qrstuvwxyzabcdef');
        $drafts->save('ada', $this->key, null, DraftContent::of('v', []));
        $drafts->save('ada', $other, null, DraftContent::of('v', []));
        $drafts->discard('ada', $this->key);

        self::assertNull($drafts->read('ada', $this->key));
        self::assertNotNull($drafts->read('ada', $other));
        self::assertSame(0, $drafts->purge());
        $this->clock->sleep(60);
        self::assertSame(1, $drafts->purge());
    }

    public function testThePlainSealerKeepsTheContentAsItIs(): void
    {
        $sealer = new PlainSealer();

        self::assertSame('{"a":1}', $sealer->seal('{"a":1}', 'ctx'));
        self::assertSame('{"a":1}', $sealer->open('{"a":1}', 'other'));
    }
}
