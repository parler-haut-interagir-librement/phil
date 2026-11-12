<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Unit\Application;

use Override;
use Phil\SearchBundle\Application\Contract\RejectedForms;
use Phil\SearchBundle\Application\RejectedFormSynonyms;
use Phil\SearchBundle\Application\SynonymExpander;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;
use Phil\SearchBundle\Domain\ValueObject\SynonymRule;
use Phil\SearchBundle\Tests\Unit\Fixtures\FixedSynonyms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SynonymExpander::class)]
#[CoversClass(RejectedFormSynonyms::class)]
final class SynonymExpanderTest extends TestCase
{
    public function testTheLongestPhraseFromTheLeftBecomesATerm(): void
    {
        $expander = new SynonymExpander([new FixedSynonyms(['fr' => [
            SynonymRule::equivalent(['jdr', 'jeu de rôle']),
            SynonymRule::equivalent(['jeu', 'partie']),
            SynonymRule::oneWay(['donjon'], ['donjon', 'dungeon']),
            SynonymRule::oneWay(['ddj'], ['donjons et dragons']),
        ]])]);

        self::assertSame([['jeu de rôle', 'jdr'], ['donjon', 'dungeon']], $expander->expand(SearchQuery::of('Jeu de rôle donjon', language: 'fr'))->terms());
        self::assertSame([['jeu', 'partie'], ['de'], ['société']], $expander->expand(SearchQuery::of('jeu de société', ['locale' => 'fr_CA']))->terms());
        self::assertSame([['jdr', 'jeu de rôle'], ['donjons et dragons']], $expander->expand(SearchQuery::of('JDR ddj', language: 'fr'))->terms());
        self::assertSame([['jeu de rôle', 'jdr']], $expander->expand(SearchQuery::of('jeu de rôle', language: 'fr'))->terms());
        self::assertSame([['x'], ['jeu', 'partie']], $expander->expand(SearchQuery::of('x jeu', language: 'fr'))->terms());

        $plain = SearchQuery::of('rien ici', language: 'fr');
        self::assertSame($plain, $expander->expand($plain), 'no word a rule knows');
        $english = SearchQuery::of('jdr', language: 'en');
        self::assertSame($english, $expander->expand($english), 'another language');
        $unknown = SearchQuery::of('jdr');
        self::assertSame($unknown, $expander->expand($unknown), 'no language');
        self::assertSame([], $expander->expand(SearchQuery::of('', language: 'fr'))->terms());
    }

    public function testTheSynonymsOfALanguageAreReadOnceUntilReset(): void
    {
        $first = new FixedSynonyms(['fr' => [SynonymRule::equivalent(['a', 'b']), SynonymRule::oneWay(['a'], ['c'])]]);
        $second = new FixedSynonyms(['fr' => [SynonymRule::oneWay(['a'], ['d', 'b']), SynonymRule::oneWay(['e f g'], ['h'])]]);
        $expander = new SynonymExpander([$first, $second]);

        self::assertSame(['a' => ['a', 'b', 'c', 'd'], 'b' => ['b', 'a'], 'e f g' => ['h']], $expander->map('fr'));
        $expander->map('fr');
        $expander->expand(SearchQuery::of('a', language: 'fr'));
        self::assertSame(['fr' => 1], $first->asked);
        self::assertSame([['h']], $expander->expand(SearchQuery::of('e f g', language: 'fr'))->terms());
        self::assertSame([['e'], ['f']], $expander->expand(SearchQuery::of('e f', language: 'fr'))->terms());

        $expander->reset();
        $expander->map('fr');
        self::assertSame(['fr' => 2], $first->asked);
        self::assertSame([], $expander->map('de'));
    }

    public function testATermKeepsThirtyTwoAlternatives(): void
    {
        $many = array_map(static fn (int $n): string => 'w'.$n, range(1, 40));
        $expander = new SynonymExpander([new FixedSynonyms(['fr' => [SynonymRule::oneWay(['a'], $many)]])]);

        self::assertSame(\array_slice($many, 0, 32), $expander->map('fr')['a']);
    }

    public function testAnEngineGetsWhatEachPhraseAlsoFinds(): void
    {
        $expander = new SynonymExpander([new FixedSynonyms([
            'fr' => [SynonymRule::equivalent(['jdr', 'jeu de rôle']), SynonymRule::oneWay(['donjon'], ['donjon', 'dungeon']), SynonymRule::oneWay(['x'], ['y'])],
            'en' => [SynonymRule::equivalent(['rpg', 'jdr'])],
        ])]);

        self::assertSame(['jdr' => ['jeu de rôle', 'rpg'], 'jeu de rôle' => ['jdr'], 'donjon' => ['dungeon'], 'x' => ['y'], 'rpg' => ['jdr']], $expander->engineSynonyms(['fr', 'en']));
        self::assertSame([], $expander->engineSynonyms([]));
    }

    public function testRejectedFormsAreOneWaySynonyms(): void
    {
        $provider = new RejectedFormSynonyms(new class implements RejectedForms {
            #[Override]
            public function rejectedForms(string $language): iterable
            {
                yield ['D&D', 'Donjons et Dragons'];
                yield ['Same', 'same'];
                yield [str_repeat('x', 101), 'long'];
                yield [$language, 'langue'];
            }
        });
        $rules = [...$provider->rules('fr')];

        self::assertCount(2, $rules);
        self::assertSame([['d&d'], ['d&d', 'donjons et dragons'], false], [$rules[0]->from, $rules[0]->to, $rules[0]->equivalent]);
        self::assertSame([['fr'], ['fr', 'langue']], [$rules[1]->from, $rules[1]->to]);
    }

    public function testAnEngineGetsEachAlternativeOnceWhateverTheLanguages(): void
    {
        $expander = new SynonymExpander([new FixedSynonyms([
            'fr' => [SynonymRule::equivalent(['p', 'x', 'y'])],
            'en' => [SynonymRule::equivalent(['p', 'x', 'z'])],
        ])]);

        self::assertSame(['p' => ['x', 'y', 'z'], 'x' => ['p', 'y', 'z'], 'y' => ['p', 'x'], 'z' => ['p', 'x']], $expander->engineSynonyms(['fr', 'en']));
        self::assertSame(['p', 'x', 'y'], $expander->map('fr')['p']);
        self::assertSame(['y', 'p', 'x'], $expander->map('fr')['y']);
    }
}
