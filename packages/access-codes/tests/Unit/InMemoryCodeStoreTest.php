<?php

declare(strict_types=1);

namespace Phil\AccessCodes\Tests\Unit;

use Override;
use Phil\AccessCodes\Contract\CodeStore;
use Phil\AccessCodes\Exception\InvalidConfiguration;
use Phil\AccessCodes\InMemoryCodeStore;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryCodeStore::class)]
final class InMemoryCodeStoreTest extends CodeStoreTestCase
{
    public function testAHashGivenTwiceInABatchIsRefused(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('A batch is stored with one distinct hash per code.');

        $this->store()->add($this->batch('b1', 2), [self::hash(1), self::hash(1)]);
    }

    public function testTheMessagesNameWhatIsStoredAlready(): void
    {
        $store = $this->store();
        $store->add($this->batch('b1', 1), [self::hash(1)]);

        try {
            $store->add($this->batch('b1', 1), [self::hash(2)]);
            self::fail('A batch was stored twice.');
        } catch (InvalidConfiguration $invalidConfiguration) {
            self::assertSame('A batch with the identifier "b1" is already stored.', $invalidConfiguration->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('A code of the batch is already stored.');

        $store->add($this->batch('b2', 1), [self::hash(1)]);
    }

    #[Override]
    protected function store(): CodeStore
    {
        return new InMemoryCodeStore();
    }
}
