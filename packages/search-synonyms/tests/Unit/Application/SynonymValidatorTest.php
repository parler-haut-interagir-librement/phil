<?php

declare(strict_types=1);

namespace Phil\SearchSynonyms\Tests\Unit\Application;

use Phil\SearchSynonyms\Application\SynonymValidator;
use Phil\SearchSynonyms\Domain\ValueObject\SynonymList;
use Phil\SearchSynonyms\Domain\ValueObject\SynonymSet;
use Phil\SearchSynonyms\Domain\ValueObject\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SynonymValidator::class)]
final class SynonymValidatorTest extends TestCase
{
    public function testOverlapsAndChainsAreNoticed(): void
    {
        $violations = new SynonymValidator()->validate(SynonymList::of('fr', [
            SynonymSet::equivalent(['jdr', 'jeu de rôle']),
            SynonymSet::oneWay(['donjon'], ['donjon', 'dungeon', 'jdr']),
            SynonymSet::equivalent(['rpg', 'jdr']),
            SynonymSet::oneWay(['dungeon'], ['cachot']),
            SynonymSet::oneWay(['x'], ['x', 'y']),
        ]));

        self::assertEquals([
            new Violation(1, Violation::CHAIN, '"dungeon" is rewritten by rule 4, which a search does not follow from this one.'),
            new Violation(1, Violation::CHAIN, '"jdr" is rewritten by rule 1, which a search does not follow from this one.'),
            new Violation(2, Violation::OVERLAP, '"jdr" is already on the left of rule 1: a search reads it as both.'),
        ], $violations);
    }

    public function testACleanListHasNoViolation(): void
    {
        self::assertSame([], new SynonymValidator()->validate(SynonymList::of('fr', [
            SynonymSet::equivalent(['jdr', 'jeu de rôle']),
            SynonymSet::oneWay(['donjon'], ['donjon', 'dungeon']),
            SynonymSet::oneWay(['dj'], ['dj', 'dungeon']),
        ])));
        self::assertSame([], new SynonymValidator()->validate(SynonymList::of('fr')));
    }

    public function testAPhraseKeptOnBothSidesIsNoChain(): void
    {
        $violations = new SynonymValidator()->validate(SynonymList::of('fr', [
            SynonymSet::oneWay(['a'], ['b']),
            SynonymSet::oneWay(['a'], ['a', 'c']),
        ]));

        self::assertEquals([new Violation(1, Violation::OVERLAP, '"a" is already on the left of rule 1: a search reads it as both.')], $violations);
    }
}
