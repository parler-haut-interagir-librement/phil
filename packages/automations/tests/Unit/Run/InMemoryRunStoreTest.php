<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Run;

use DateTimeImmutable;
use Override;
use Phil\Automations\Contract\RunStore;
use Phil\Automations\Run\AutomationRun;
use Phil\Automations\Run\InMemoryRunStore;
use Phil\Automations\Run\RunId;
use Phil\Automations\Run\RunState;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryRunStore::class)]
final class InMemoryRunStoreTest extends RunStoreTestCase
{
    #[Override]
    protected function store(): RunStore
    {
        return new InMemoryRunStore();
    }

    public function testARunWithoutAnInstantIsDueAsAtTheEpochAndTheTiesKeepTheirOrder(): void
    {
        $one = self::newRun('a')->waitingUntil(new DateTimeImmutable('@1'), null, 0, self::at(0));
        $epoch = self::newRun('b')->waitingUntil(new DateTimeImmutable('@0'), null, 0, self::at(0));
        $none = self::newRun('c');
        $none = new AutomationRun($none->id, $none->automationId, $none->version, $none->subject, $none->payload, RunState::Waiting, $none->cursor, null, 0, [], null, 0, self::at(0), self::at(0));

        $later = self::newRun('d')->waitingUntil(new DateTimeImmutable('@5'), null, 0, self::at(0));

        foreach ([[$one, $epoch, $none, $later, [$epoch, $none, $one, $later]], [$one, $epoch, $later, $none, [$epoch, $none, $one, $later]], [$one, $none, $later, $epoch, [$none, $epoch, $one, $later]]] as [$first, $second, $third, $fourth, $expected]) {
            $store = new InMemoryRunStore();

            foreach ([$first, $second, $third, $fourth] as $run) {
                $store->create($run);
            }

            self::assertEquals(array_map(static fn (AutomationRun $run): string => $run->id->value, $expected), array_map(static fn (RunId $id): string => $id->value, $store->due(self::at(10), 10)));
        }
    }
}
