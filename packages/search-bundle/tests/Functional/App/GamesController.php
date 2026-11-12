<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Functional\App;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Phil\SearchBundle\Application\Search;
use Phil\SearchBundle\Application\SearchIndex;
use Phil\SearchBundle\Application\SearchRequest;
use Phil\SearchBundle\Domain\ValueObject\Document;
use Phil\SearchBundle\Infrastructure\Doctrine\SearchTable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * A results page of games, filtered by genre and year: the documents are written afresh at each request, the database
 * being in memory.
 */
final readonly class GamesController
{
    public const array GAMES = [
        ['a', 'Un jeu de rôle', ['jdr', 'fantasy'], 2026],
        ['b', 'Un JDR de science-fiction', ['jdr', 'sf'], 2025],
        ['c', 'Un jeu de plateau', ['plateau'], 2026],
        ['d', 'Un jeu de cartes', ['cartes', 'fantasy'], 2024],
        ['e', 'Un jeu de dés', ['dés'], 2026],
    ];

    public function __construct(
        private Connection $connection,
        private SearchIndex $index,
        private Search $search,
        private Environment $twig,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->connection->createSchemaManager()->tablesExist([SearchTable::NAME])) {
            foreach ($this->connection->getDatabasePlatform()->getCreateTablesSQL(SearchTable::configure(Schema::editor()->create())->getTables()) as $sql) {
                $this->connection->executeStatement($sql);
            }

            foreach (self::GAMES as [$id, $title, $genres, $year]) {
                $this->index->index(Document::of('games', $id, ['title' => $title], ['genre' => $genres, 'year' => $year, 'locale' => 'fr']));
            }
        }

        $asked = SearchRequest::fromQuery($request->query->all(), ['genre', 'year'], 10);
        $result = $this->search->search('games', $asked->query(['genre', 'year'], ['locale' => 'fr']));
        $titles = array_column(self::GAMES, 1, 0);

        return new Response($this->twig->render('games.html.twig', [
            'request' => $asked,
            'result' => $result,
            'titles' => array_map(static fn (string $id): string => $titles[$id] ?? $id, $result->ids),
        ]));
    }
}
