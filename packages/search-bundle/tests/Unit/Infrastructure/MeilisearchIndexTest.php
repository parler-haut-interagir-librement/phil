<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Unit\Infrastructure;

use ErrorException;
use Phil\SearchBundle\Domain\Exception\InvalidSearch;
use Phil\SearchBundle\Domain\Exception\SearchException;
use Phil\SearchBundle\Domain\ValueObject\Document;
use Phil\SearchBundle\Domain\ValueObject\FacetValue;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;
use Phil\SearchBundle\Domain\ValueObject\SearchResult;
use Phil\SearchBundle\Infrastructure\Meilisearch\MeilisearchIndex;
use Phil\SearchBundle\Infrastructure\Meilisearch\SearchFailed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(MeilisearchIndex::class)]
#[CoversClass(SearchFailed::class)]
final class MeilisearchIndexTest extends TestCase
{
    /** @var list<array{string, string, mixed, ?string}> */
    private array $sent = [];

    /** @var list<string> the raw bodies sent */
    private array $bodies = [];

    public function testDocumentsGoToTheirIndexes(): void
    {
        $index = new MeilisearchIndex($this->client(), 'http://meili:7700/', 'secret');
        $index->index(
            Document::of('articles', 'c', ['title' => 'Old'], ['year' => 2020]),
            Document::of('articles', 'b', ['title' => 'Role'], ['locale' => 'en']),
            Document::of('articles', 'a', ['title' => 'Jeux'], ['locale' => 'fr', 'tags' => ['jdr']]),
            Document::of('pages', 'c', ['title' => 'Accueil']),
        );

        self::assertSame([
            ['GET', 'http://meili:7700/indexes/articles/settings/filterable-attributes', null, 'Bearer secret'],
            ['PUT', 'http://meili:7700/indexes/articles/settings/filterable-attributes', ['year', 'locale', 'tags'], 'Bearer secret'],
            ['POST', 'http://meili:7700/indexes/articles/documents?primaryKey=id', [['id' => 'c', 'title' => 'Old', 'year' => 2020], ['id' => 'b', 'title' => 'Role', 'locale' => 'en'], ['id' => 'a', 'title' => 'Jeux', 'locale' => 'fr', 'tags' => ['jdr']]], 'Bearer secret'],
            ['POST', 'http://meili:7700/indexes/pages/documents?primaryKey=id', [['id' => 'c', 'title' => 'Accueil']], 'Bearer secret'],
        ], $this->sent);
    }

    public function testFilterableAttributesAreAddedToThoseTheIndexHas(): void
    {
        $index = new MeilisearchIndex($this->client(answers: [
            'GET /indexes/articles/settings/filterable-attributes' => ['locale', ['attributePatterns' => ['genre'], 'features' => ['filter' => ['equality' => true]]], 7],
            'GET /indexes/pages/settings/filterable-attributes' => 404,
        ]), 'http://meili');
        $index->index(Document::of('articles', 'a', ['title' => 'A'], ['locale' => 'fr']));
        $index->index(Document::of('articles', 'b', ['title' => 'B'], ['locale' => 'en', 'genre' => 'jdr']));
        $index->index(Document::of('articles', 'c', ['title' => 'C'], ['year' => 2026]));
        $index->index(Document::of('articles', 'd', ['title' => 'D'], ['year' => 2025, 'locale' => 'fr']));
        $index->index(Document::of('pages', 'a', ['title' => 'A'], ['locale' => 'fr']));

        self::assertSame([
            ['GET', 'http://meili/indexes/articles/settings/filterable-attributes', null, null],
            ['POST', 'http://meili/indexes/articles/documents?primaryKey=id', [['id' => 'a', 'title' => 'A', 'locale' => 'fr']], null],
            ['POST', 'http://meili/indexes/articles/documents?primaryKey=id', [['id' => 'b', 'title' => 'B', 'locale' => 'en', 'genre' => 'jdr']], null],
            ['GET', 'http://meili/indexes/articles/settings/filterable-attributes', null, null],
            ['PUT', 'http://meili/indexes/articles/settings/filterable-attributes', ['locale', ['attributePatterns' => ['genre'], 'features' => ['filter' => ['equality' => true]]], 'year'], null],
            ['POST', 'http://meili/indexes/articles/documents?primaryKey=id', [['id' => 'c', 'title' => 'C', 'year' => 2026]], null],
            ['POST', 'http://meili/indexes/articles/documents?primaryKey=id', [['id' => 'd', 'title' => 'D', 'year' => 2025, 'locale' => 'fr']], null],
            ['GET', 'http://meili/indexes/pages/settings/filterable-attributes', null, null],
            ['PUT', 'http://meili/indexes/pages/settings/filterable-attributes', ['locale'], null],
            ['POST', 'http://meili/indexes/pages/documents?primaryKey=id', [['id' => 'a', 'title' => 'A', 'locale' => 'fr']], null],
        ], $this->sent);

        $this->expectException(SearchFailed::class);
        $this->expectExceptionMessageIsOrContains('Meilisearch answered the filterable attributes with 500.');

        new MeilisearchIndex($this->client(answers: ['GET /indexes/articles/settings/filterable-attributes' => 500]), 'http://meili')->index(Document::of('articles', 'a', ['title' => 'A'], ['locale' => 'fr']));
    }

    public function testAFilterableAttributesAnswerIsReadUnder400(): void
    {
        new MeilisearchIndex($this->client(answers: ['GET /indexes/articles/settings/filterable-attributes' => 399]), 'http://meili')->index(Document::of('articles', 'a', ['title' => 'A'], ['locale' => 'fr']));
        self::assertSame(['PUT', 'http://meili/indexes/articles/settings/filterable-attributes', ['locale'], null], $this->sent[1]);

        $this->expectException(SearchFailed::class);
        $this->expectExceptionMessageIsOrContains('Meilisearch answered the filterable attributes with 400.');

        new MeilisearchIndex($this->client(answers: ['GET /indexes/articles/settings/filterable-attributes' => 400]), 'http://meili')->index(Document::of('articles', 'a', ['title' => 'A'], ['locale' => 'fr']));
    }

    /**
     * A pattern that is not a name is kept as Meilisearch has it, and never read as one: no warning, no attribute.
     */
    public function testOddFilterableNamesAreLeftOut(): void
    {
        set_error_handler(static fn (int $level, string $message): never => throw new ErrorException($message, 0, $level));

        try {
            $index = new MeilisearchIndex($this->client(answers: ['GET /indexes/articles/settings/filterable-attributes' => [['attributePatterns' => [['odd'], 'genre']]]]), 'http://meili');
            $index->index(Document::of('articles', 'a', ['title' => 'A'], ['locale' => 'fr']));
            $index->index(Document::of('articles', 'b', ['title' => 'B'], ['year' => 2026]));
        } finally {
            restore_error_handler();
        }

        self::assertSame(['PUT', 'http://meili/indexes/articles/settings/filterable-attributes', [['attributePatterns' => [['odd'], 'genre']], 'locale'], null], $this->sent[1]);
        self::assertSame(['PUT', 'http://meili/indexes/articles/settings/filterable-attributes', [['attributePatterns' => [['odd'], 'genre']], 'locale', 'year'], null], $this->sent[4]);
    }

    public function testFilterValuesGoAsTheyAre(): void
    {
        new MeilisearchIndex($this->client(['hits' => []]), 'http://meili')->search('articles', SearchQuery::of('', ['tags' => 'soirée', 'path' => 'a/b', 'quote' => 'dit "oui"']));

        $body = $this->sent[0][2];
        self::assertIsArray($body);
        self::assertSame(['tags = "soirée"', 'path = "a/b"', 'quote = "dit \\"oui\\""'], $body['filter'] ?? null);
    }

    public function testARebuildFillsAnotherIndexThenSwapsThem(): void
    {
        $settings = ['filterableAttributes' => ['locale'], 'searchableAttributes' => ['title', 'body']];
        $index = new MeilisearchIndex($this->client(answers: ['GET /indexes/articles/settings' => $settings]), 'http://meili');
        $index->rebuild('articles', [[Document::of('articles', 'a', ['title' => 'A'], ['locale' => 'fr'])], [Document::of('articles', 'b', ['title' => 'B'], ['tags' => 'jdr'])]]);
        $index->index(Document::of('articles', 'c', ['title' => 'C'], ['tags' => 'x', 'locale' => 'en']));

        self::assertSame([
            ['DELETE', 'http://meili/indexes/articles__rebuild', null, null],
            ['POST', 'http://meili/indexes', ['uid' => 'articles', 'primaryKey' => 'id'], null],
            ['POST', 'http://meili/indexes', ['uid' => 'articles__rebuild', 'primaryKey' => 'id'], null],
            ['GET', 'http://meili/indexes/articles/settings', null, null],
            ['PATCH', 'http://meili/indexes/articles__rebuild/settings', $settings, null],
            ['POST', 'http://meili/indexes/articles__rebuild/documents?primaryKey=id', [['id' => 'a', 'title' => 'A', 'locale' => 'fr']], null],
            ['GET', 'http://meili/indexes/articles__rebuild/settings/filterable-attributes', null, null],
            ['PUT', 'http://meili/indexes/articles__rebuild/settings/filterable-attributes', ['locale', 'tags'], null],
            ['POST', 'http://meili/indexes/articles__rebuild/documents?primaryKey=id', [['id' => 'b', 'title' => 'B', 'tags' => 'jdr']], null],
            ['POST', 'http://meili/swap-indexes', [['indexes' => ['articles', 'articles__rebuild']]], null],
            ['DELETE', 'http://meili/indexes/articles__rebuild', null, null],
            ['POST', 'http://meili/indexes/articles/documents?primaryKey=id', [['id' => 'c', 'title' => 'C', 'tags' => 'x', 'locale' => 'en']], null],
        ], $this->sent);
    }

    public function testAFirstRebuildHasNoSettingsToCopy(): void
    {
        new MeilisearchIndex($this->client(answers: ['GET /indexes/pages/settings' => 404]), 'http://meili')->rebuild('pages', []);

        self::assertSame(['DELETE', 'POST', 'POST', 'GET', 'POST', 'DELETE'], array_column($this->sent, 0));
    }

    public function testARebuildThatFailsLeavesTheLiveIndex(): void
    {
        $failing = (static function (): iterable {
            yield [Document::of('articles', 'a', ['title' => 'A'])];

            throw new RuntimeException('The provider failed.');
        })();

        try {
            new MeilisearchIndex($this->client(answers: ['GET /indexes/articles/settings' => 404]), 'http://meili')->rebuild('articles', $failing);
            self::fail('The rebuild went on.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The provider failed.', $runtimeException->getMessage());
        }

        self::assertSame(['DELETE', 'http://meili/indexes/articles__rebuild', null, null], $this->sent[\count($this->sent) - 1]);
        self::assertNotContains('http://meili/swap-indexes', array_column($this->sent, 1));
    }

    public function testAnIndexNameIsChecked(): void
    {
        $index = new MeilisearchIndex($this->client(), 'http://meili');

        foreach ([
            static fn (): SearchResult => $index->search('../keys', SearchQuery::of('')),
            static fn () => $index->remove('a/b', ['x']),
            static fn () => $index->clear('A'),
            static fn () => $index->rebuild('x?y', []),
        ] as $call) {
            try {
                $call();
                self::fail('An index name was not checked.');
            } catch (InvalidSearch $invalidSearch) {
                self::assertStringStartsWith('An index is 1 to 64 lower-case letters', $invalidSearch->getMessage());
            }
        }

        self::assertSame([], $this->sent);
    }

    public function testRemovalsClearingsAndSearches(): void
    {
        $index = new MeilisearchIndex($this->client(['hits' => [['id' => 'a'], ['id' => 7], ['other' => 1], 'x'], 'estimatedTotalHits' => 12]), 'http://meili:7700');
        $index->remove('articles', ['a', 'b']);
        $index->clear('articles');

        $result = $index->search('articles', SearchQuery::of('jeux', ['locale' => 'fr', 'year' => 2026], 5, 10));

        self::assertSame([['a', '7', '', ''], 12], [$result->ids, $result->total]);
        self::assertSame([
            ['POST', 'http://meili:7700/indexes/articles/documents/delete-batch', ['a', 'b'], null],
            ['DELETE', 'http://meili:7700/indexes/articles/documents', null, null],
            ['POST', 'http://meili:7700/indexes/articles/search', ['q' => 'jeux', 'filter' => ['locale = "fr"', 'year = "2026"'], 'limit' => 5, 'offset' => 10, 'attributesToRetrieve' => ['id']], null],
        ], $this->sent);
        $odd = new MeilisearchIndex($this->client(['hits' => 'x', 'estimatedTotalHits' => 'x']), 'http://meili')->search('a', SearchQuery::of(''));
        self::assertSame([[], 0, 0], [$odd->ids, $odd->total, new MeilisearchIndex($this->client([]), 'http://meili')->search('a', SearchQuery::of(''))->total]);
    }

    public function testListsOfValuesAndFacetsWithoutAFilterGoInOneSearch(): void
    {
        $index = new MeilisearchIndex($this->client(['hits' => [['id' => 'a']], 'estimatedTotalHits' => 1, 'facetDistribution' => ['genre' => ['jdr' => 4, 'plateau' => 2, 'odd' => 'x'], 'other' => ['x' => 1], 'year' => 'x']]), 'http://meili');
        $result = $index->search('games', SearchQuery::of('jeu', ['tags' => ['a', 'dit "b"', 'c\\'], 'year' => 2026], 10, 0, ['genre', 'locale']));

        self::assertSame([['a'], 1], [$result->ids, $result->total]);
        self::assertSame(['genre', 'locale'], array_keys($result->facets));
        self::assertSame([['jdr', 4], ['plateau', 2]], array_map(static fn (FacetValue $value): array => [$value->value, $value->count], $result->facet('genre')));
        self::assertSame([], $result->facet('locale'));
        self::assertSame([
            ['POST', 'http://meili/indexes/games/search', ['q' => 'jeu', 'filter' => ['tags IN ["a", "dit \\"b\\"", "c\\\\"]', 'year = "2026"'], 'limit' => 10, 'offset' => 0, 'attributesToRetrieve' => ['id'], 'facets' => ['genre', 'locale']], null],
        ], $this->sent);
    }

    public function testAFacetWhoseFilterIsSetIsCountedByAnotherQuery(): void
    {
        $index = new MeilisearchIndex($this->client(answers: ['POST /multi-search' => ['results' => [
            ['hits' => [['id' => 'a'], ['id' => 'b']], 'estimatedTotalHits' => 2, 'facetDistribution' => ['year' => ['2026' => 2], 'genre' => ['jdr' => 2]]],
            ['hits' => [], 'estimatedTotalHits' => 5, 'facetDistribution' => ['genre' => ['jdr' => 2, 'plateau' => 3]]],
            'odd',
        ]]]), 'http://meili');
        $result = $index->search('games', SearchQuery::of('jeu', ['genre' => ['jdr'], 'locale' => 'fr'], 20, 20, ['genre', 'year']));

        self::assertSame([['a', 'b'], 2], [$result->ids, $result->total]);
        self::assertSame([['plateau', 3], ['jdr', 2]], array_map(static fn (FacetValue $value): array => [$value->value, $value->count], $result->facet('genre')));
        self::assertSame([['2026', 2]], array_map(static fn (FacetValue $value): array => [$value->value, $value->count], $result->facet('year')));
        self::assertSame([
            ['POST', 'http://meili/multi-search', ['queries' => [
                ['indexUid' => 'games', 'q' => 'jeu', 'filter' => ['genre IN ["jdr"]', 'locale = "fr"'], 'limit' => 20, 'offset' => 20, 'attributesToRetrieve' => ['id'], 'facets' => ['year']],
                ['indexUid' => 'games', 'q' => 'jeu', 'filter' => ['locale = "fr"'], 'limit' => 0, 'facets' => ['genre']],
            ]], null],
        ], $this->sent);

        $odd = new MeilisearchIndex($this->client(answers: ['POST /multi-search' => ['results' => 'x']]), 'http://meili')->search('games', SearchQuery::of('', ['genre' => 'jdr'], facets: ['genre']));
        self::assertSame([[], 0, []], [$odd->ids, $odd->total, $odd->facet('genre')]);
        self::assertSame([], new MeilisearchIndex($this->client(answers: ['POST /multi-search' => []]), 'http://meili')->search('games', SearchQuery::of('', ['genre' => 'jdr'], facets: ['genre']))->ids);
    }

    public function testSynonymsAreTheIndexSetting(): void
    {
        $index = new MeilisearchIndex($this->client(), 'http://meili', 'k');
        $index->replaceSynonyms('games', ['jdr' => ['jeu de rôle'], 'jeu de rôle' => ['jdr']]);
        $index->replaceSynonyms('games', []);

        self::assertSame([
            ['PUT', 'http://meili/indexes/games/settings/synonyms', ['jdr' => ['jeu de rôle'], 'jeu de rôle' => ['jdr']], 'Bearer k'],
            ['PUT', 'http://meili/indexes/games/settings/synonyms', [], 'Bearer k'],
        ], $this->sent);
        self::assertSame('{}', $this->bodies[1]);

        $this->expectException(InvalidSearch::class);
        $index->replaceSynonyms('Games', []);
    }

    public function testARefusalIsAFailure(): void
    {
        foreach ([399 => false, 400 => true] as $status => $fails) {
            try {
                new MeilisearchIndex(new MockHttpClient(new MockResponse('{}', ['http_code' => $status])), 'http://meili')->clear('a');
                self::assertFalse($fails);
            } catch (SearchFailed $searchFailed) {
                self::assertTrue($fails);
                self::assertSame('Meilisearch answered a clearing with 400.', $searchFailed->getMessage());
                self::assertInstanceOf(SearchException::class, $searchFailed);
            }
        }
    }

    /**
     * @param array<array-key, mixed>                    $answer  what every request is answered
     * @param array<string, array<array-key, mixed>|int> $answers by method and path: an answer, or a status alone
     */
    private function client(array $answer = ['taskUid' => 1], array $answers = []): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url, array $options) use ($answer, $answers): MockResponse {
            $headers = $options['normalized_headers'] ?? [];
            self::assertIsArray($headers);
            $authorization = $headers['authorization'] ?? [];
            self::assertIsArray($authorization);
            $body = $options['body'] ?? '';
            self::assertIsString($body);
            $this->bodies[] = $body;
            $this->sent[] = [$method, $url, '' === $body ? null : json_decode($body, true), \is_string($authorization[0] ?? null) ? substr($authorization[0], \strlen('Authorization: ')) : null];

            $given = $answers[$method.' '.parse_url($url, \PHP_URL_PATH)] ?? $answer;

            return \is_int($given) ? new MockResponse('{}', ['http_code' => $given]) : new MockResponse((string) json_encode($given), ['http_code' => 202]);
        });
    }

    public function testAnAnswerThatIsNoListOfFacetsIsSkippedWhereverItIs(): void
    {
        $index = new MeilisearchIndex($this->client(answers: ['POST /multi-search' => ['results' => [
            'odd',
            ['hits' => [['id' => 'a']], 'estimatedTotalHits' => 1, 'facetDistribution' => ['year' => ['2026' => 1]]],
            ['hits' => [], 'facetDistribution' => ['genre' => ['jdr' => 1]]],
        ]]]), 'http://meili');
        $result = $index->search('games', SearchQuery::of('', ['genre' => 'jdr'], facets: ['genre', 'year']));

        self::assertSame([['a'], 1], [$result->ids, $result->total]);
        self::assertSame([['jdr', 1]], array_map(static fn (FacetValue $value): array => [$value->value, $value->count], $result->facet('genre')));
    }
}
