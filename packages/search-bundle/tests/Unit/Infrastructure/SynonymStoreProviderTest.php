<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Unit\Infrastructure;

use Phil\SearchBundle\Infrastructure\SearchSynonyms\SynonymStoreProvider;
use Phil\SearchSynonyms\Domain\ValueObject\SynonymList;
use Phil\SearchSynonyms\Domain\ValueObject\SynonymSet;
use Phil\SearchSynonyms\Infrastructure\Store\InMemorySynonymStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SynonymStoreProvider::class)]
final class SynonymStoreProviderTest extends TestCase
{
    public function testTheSetsOfAStoreAreRules(): void
    {
        $provider = new SynonymStoreProvider(new InMemorySynonymStore(SynonymList::of('fr', [
            SynonymSet::equivalent(['jdr', 'jeu de rôle']),
            SynonymSet::oneWay(['donjon'], ['donjon', 'dungeon']),
        ])));
        $rules = [...$provider->rules('fr')];

        self::assertCount(2, $rules);
        self::assertSame([['jdr', 'jeu de rôle'], ['jdr', 'jeu de rôle'], true], [$rules[0]->from, $rules[0]->to, $rules[0]->equivalent]);
        self::assertSame([['donjon'], ['donjon', 'dungeon'], false], [$rules[1]->from, $rules[1]->to, $rules[1]->equivalent]);
        self::assertSame([], [...$provider->rules('en')]);
    }
}
