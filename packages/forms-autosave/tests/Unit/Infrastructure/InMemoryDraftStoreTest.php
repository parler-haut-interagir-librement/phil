<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\FormsAutosave\Application\Contract\DraftStore;
use Phil\FormsAutosave\Domain\Model\Draft;
use Phil\FormsAutosave\Domain\ValueObject\DraftKey;
use Phil\FormsAutosave\Infrastructure\Memory\InMemoryDraftStore;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryDraftStore::class)]
final class InMemoryDraftStoreTest extends DraftStoreTestCase
{
    #[Override]
    protected function store(): DraftStore
    {
        return new InMemoryDraftStore();
    }

    public function testResettingEmptiesIt(): void
    {
        $store = new InMemoryDraftStore();
        $key = DraftKey::of('abcdefghijklmnop');
        $store->insert(Draft::first(self::ADA, $key, 'x', new DateTimeImmutable(), 60));
        $store->reset();

        self::assertNull($store->find(self::ADA, $key));
    }
}
