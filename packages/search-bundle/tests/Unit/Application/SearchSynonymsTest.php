<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Unit\Application;

use Phil\SearchBundle\Application\Search;
use Phil\SearchBundle\Application\SynonymExpander;
use Phil\SearchBundle\Domain\Exception\InvalidSearch;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;
use Phil\SearchBundle\Domain\ValueObject\SynonymRule;
use Phil\SearchBundle\Infrastructure\Console\SynonymsCommand;
use Phil\SearchBundle\Tests\Unit\Fixtures\Article;
use Phil\SearchBundle\Tests\Unit\Fixtures\Articles;
use Phil\SearchBundle\Tests\Unit\Fixtures\FixedSynonyms;
use Phil\SearchBundle\Tests\Unit\Fixtures\QueryRecorder;
use Phil\SearchBundle\Tests\Unit\Fixtures\RecordingQueries;
use Phil\SearchBundle\Tests\Unit\Fixtures\SynonymKeepingIndex;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(Search::class)]
#[CoversClass(SynonymsCommand::class)]
final class SearchSynonymsTest extends TestCase
{
    public function testASearchReadsTheSynonymsOfItsLanguage(): void
    {
        $index = $this->index();
        $search = new Search($index, [], synonyms: $this->expander());
        $search->search('articles', SearchQuery::of('jdr', language: 'fr'));

        self::assertSame([['jdr', 'jeu de rôle']], $index->queries[0]->terms());

        new Search($index, [])->search('articles', SearchQuery::of('jdr', language: 'fr'));
        self::assertSame([['jdr']], $index->queries[1]->terms(), 'without synonyms');
    }

    public function testAnIndexKeepingSynonymsGetsThemInsteadOfAlternatives(): void
    {
        $index = $this->index(aware: true);
        $search = new Search($index, [new Articles([new Article('a', 'A')])], synonyms: $this->expander(), synonymLanguages: ['fr']);
        $search->search('articles', SearchQuery::of('jdr', language: 'fr'));

        self::assertSame([['jdr']], $index->queries[0]->terms());
        self::assertTrue($search->publishSynonyms('articles'));
        self::assertSame(1, $search->reindex('articles'));
        self::assertSame([['articles', ['jdr' => ['jeu de rôle'], 'jeu de rôle' => ['jdr']]], ['articles', ['jdr' => ['jeu de rôle'], 'jeu de rôle' => ['jdr']]]], $index->synonyms);

        self::assertFalse(new Search($index, [], synonyms: $this->expander())->publishSynonyms('articles'), 'no language');
        self::assertFalse(new Search($index, [], synonymLanguages: ['fr'])->publishSynonyms('articles'), 'no synonyms');
        self::assertFalse(new Search($this->index(), [], synonyms: $this->expander(), synonymLanguages: ['fr'])->publishSynonyms('articles'), 'an index reading the alternatives');
        self::assertCount(2, $index->synonyms);

        $this->expectException(InvalidSearch::class);
        $search->publishSynonyms('../x');
    }

    public function testTheCommandGivesTheSynonymsToEachIndex(): void
    {
        $index = $this->index(aware: true);
        $tester = new CommandTester(new SynonymsCommand(new Search($index, [new Articles()], synonyms: $this->expander(), synonymLanguages: ['fr'])));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame("articles: synonyms given to the engine.\n", $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->execute(['indexes' => ['pages', 'news']]));
        self::assertSame(['articles', 'pages', 'news'], array_column($index->synonyms, 0));

        $plain = new CommandTester(new SynonymsCommand(new Search($this->index(), [new Articles()])));
        self::assertSame(Command::SUCCESS, $plain->execute([]));
        self::assertSame("articles: nothing to give, the index reads the synonyms at each search or no language is configured.\n", $plain->getDisplay());
    }

    private function expander(): SynonymExpander
    {
        return new SynonymExpander([new FixedSynonyms(['fr' => [SynonymRule::equivalent(['jdr', 'jeu de rôle'])]])]);
    }

    private function index(bool $aware = false): QueryRecorder
    {
        return $aware ? new SynonymKeepingIndex() : new RecordingQueries();
    }
}
