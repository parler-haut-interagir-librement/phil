<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Tests\Unit;

use Override;
use Phil\IncidentMode\Activation;
use Phil\IncidentMode\Contract\ActivationStore;
use Phil\IncidentMode\InMemoryActivationStore;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryActivationStore::class)]
#[CoversClass(Activation::class)]
final class InMemoryActivationStoreTest extends ActivationStoreTestCase
{
    public function testResetEmptiesIt(): void
    {
        $store = new InMemoryActivationStore();
        $store->add($activation = $this->activation('a', '10:00', '12:00'));
        $store->reset();

        self::assertNull($store->find($activation->id));
    }

    #[Override]
    protected function store(): ActivationStore
    {
        return new InMemoryActivationStore();
    }
}
