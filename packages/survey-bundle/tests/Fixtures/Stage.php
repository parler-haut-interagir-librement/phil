<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Fixtures;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Phil\SurveyBundle\Application\Addenda;
use Phil\SurveyBundle\Application\Contract\FeedbackSessions;
use Phil\SurveyBundle\Application\NoSessions;
use Phil\SurveyBundle\Application\Participations;
use Phil\SurveyBundle\Application\Questionnaires;
use Phil\SurveyBundle\Application\RedirectPolicy;
use Phil\SurveyBundle\Application\Results;
use Phil\SurveyBundle\Application\Submissions;
use Phil\SurveyBundle\Infrastructure\Doctrine\Column;
use Phil\SurveyBundle\Infrastructure\Doctrine\DbalQuestionnaireRepository;
use Phil\SurveyBundle\Infrastructure\Doctrine\DbalResponseRepository;
use Phil\SurveyBundle\Infrastructure\Doctrine\SurveyTables;
use Symfony\Component\Clock\MockClock;

/**
 * The bundle on SQLite in memory — or on the connection given —, its tables created from their definition, the clock
 * at 2026-11-21 19:30 UTC, redirects allowed to https://example.org.
 */
final class Stage
{
    public const string SECRET = 'a secret of the tests, long enough';

    public readonly Connection $connection;

    public readonly MockClock $clock;

    public readonly DbalQuestionnaireRepository $questionnaireRepository;

    public readonly DbalResponseRepository $responses;

    public readonly Participations $participations;

    public readonly RedirectPolicy $redirects;

    public readonly Questionnaires $questionnaires;

    public readonly Submissions $submissions;

    public readonly Results $results;

    public readonly Addenda $addenda;

    public function __construct(?Connection $connection = null, FeedbackSessions $sessions = new NoSessions(), bool $create = true)
    {
        $this->connection = $connection ?? DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        if ($create) {
            self::create($this->connection);
        }

        $this->clock = new MockClock('2026-11-21 19:30:00 UTC');
        $this->questionnaireRepository = new DbalQuestionnaireRepository($this->connection, $this->clock);
        $this->responses = new DbalResponseRepository($this->connection);
        $this->participations = new Participations(self::SECRET);
        $this->redirects = new RedirectPolicy(['https://example.org']);
        $this->questionnaires = new Questionnaires($this->questionnaireRepository, $this->redirects);
        $this->submissions = new Submissions($this->responses, $this->participations, $this->redirects, $this->clock, $sessions);
        $this->results = new Results($this->responses);
        $this->addenda = new Addenda($this->responses, $this->clock);
    }

    /**
     * How many rows the table holds.
     */
    public function rows(string $table): int
    {
        return Column::int($this->connection->fetchOne('SELECT COUNT(*) FROM '.$table));
    }

    /**
     * Creates the tables the connection lacks.
     */
    public static function create(Connection $connection): void
    {
        $manager = $connection->createSchemaManager();
        $current = $manager->introspectSchema();

        foreach (SurveyTables::configure(Schema::editor()->create(), $connection->getDatabasePlatform())->getTables() as $table) {
            if (!$current->hasTable($table->getObjectName()->toString())) {
                $manager->createTable($table);
            }
        }
    }

    /**
     * Drops the bundle's tables, for a test on a server.
     */
    public static function drop(Connection $connection): void
    {
        $manager = $connection->createSchemaManager();

        foreach (SurveyTables::ALL as $table) {
            if ($manager->tablesExist([$table])) {
                $manager->dropTable($table);
            }
        }
    }
}
