<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Fixture;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Phil\ModerationBundle\Application\Contract\WordScreen;
use Phil\ModerationBundle\Application\Decisions;
use Phil\ModerationBundle\Application\FirstPostSandbox;
use Phil\ModerationBundle\Application\MemberNotes;
use Phil\ModerationBundle\Application\ModerationQueue;
use Phil\ModerationBundle\Application\ModerationTargets;
use Phil\ModerationBundle\Application\NoWordScreen;
use Phil\ModerationBundle\Application\PublicJournal;
use Phil\ModerationBundle\Application\ReasonGrounds;
use Phil\ModerationBundle\Application\Reasons;
use Phil\ModerationBundle\Application\Redress;
use Phil\ModerationBundle\Application\Reports;
use Phil\ModerationBundle\Application\ReportSurge;
use Phil\ModerationBundle\Application\Role;
use Phil\ModerationBundle\Application\StatementsOfReasons;
use Phil\ModerationBundle\Application\Submissions;
use Phil\ModerationBundle\Application\TemporaryHides;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalDecisionRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalMemberNoteRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalReportRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalSandboxRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalStatementRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\ModerationTables;
use Symfony\Component\Clock\MockClock;

/**
 * The services of the bundle on SQLite in memory, with two targets, the people of the test and a clock it moves.
 */
final class Stage
{
    public Connection $connection;

    public MockClock $clock;

    public People $people;

    public RecordingEvents $events;

    public FakeTarget $posts;

    public FakeTarget $comments;

    public DbalReportRepository $reportRepository;

    public DbalDecisionRepository $decisionRepository;

    public DbalMemberNoteRepository $noteRepository;

    public DbalSandboxRepository $sandboxRepository;

    public Reports $reports;

    public Decisions $decisions;

    public DbalStatementRepository $statementRepository;

    public StatementsOfReasons $statements;

    /** The decisions that come with a statement of reasons when they restrict the author. */
    public Decisions $reasoned;

    public ModerationQueue $queue;

    public Submissions $submissions;

    public TemporaryHides $hides;

    public MemberNotes $notes;

    public PublicJournal $journal;

    public ReportSurge $surge;

    public Role $moderator;

    public Role $commentsOnly;

    /**
     * @param list<string> $sandboxTypes
     */
    public function __construct(?WordScreen $words = null, array $sandboxTypes = ['forum.post'], ?Connection $connection = null, bool $dispatching = true)
    {
        $this->connection = $connection ?? DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $manager = $this->connection->createSchemaManager();

        foreach (ModerationTables::tables() as $table) {
            $name = $table->getObjectName()->toString();

            if ($manager->tablesExist([$name])) {
                $manager->dropTable($name);
            }

            $manager->createTable($table);
        }

        $this->clock = new MockClock(new DateTimeImmutable('2026-11-13 10:00:00 UTC'));
        $this->people = new People();
        $this->events = new RecordingEvents();
        $this->posts = new FakeTarget('forum.post');
        $this->comments = new FakeTarget('comment');
        $this->moderator = new Role('moderator', 'ROLE_MODERATOR');
        $this->commentsOnly = new Role('comment-keeper', 'ROLE_COMMENTS', ['comment']);
        $targets = new ModerationTargets([$this->posts, $this->comments]);
        $this->reportRepository = new DbalReportRepository($this->connection);
        $this->decisionRepository = new DbalDecisionRepository($this->connection);
        $this->noteRepository = new DbalMemberNoteRepository($this->connection);
        $this->sandboxRepository = new DbalSandboxRepository($this->connection);
        $events = $dispatching ? $this->events : null;
        $this->reports = new Reports($this->reportRepository, $targets, new Reasons(), $this->people, $this->clock, $events);
        $this->decisions = new Decisions($this->decisionRepository, $this->reportRepository, $this->sandboxRepository, $targets, $this->people, $this->clock, $events);
        $this->statementRepository = new DbalStatementRepository($this->connection);
        $this->statements = new StatementsOfReasons(
            $this->statementRepository,
            new Reasons(),
            new ReasonGrounds(['spam' => ['ground' => 'terms', 'reference' => 'Terms, section 4']]),
            $this->people,
            new Redress('/terms', '/contact', [['name' => 'A settlement body', 'url' => 'https://settlement.example']]),
            $events,
        );
        $this->reasoned = new Decisions($this->decisionRepository, $this->reportRepository, $this->sandboxRepository, $targets, $this->people, $this->clock, $events, $this->statements);
        $this->queue = new ModerationQueue($this->reportRepository, $this->decisionRepository, $targets, $this->people);
        $this->submissions = new Submissions($this->reports, $words ?? new NoWordScreen(), new FirstPostSandbox($this->sandboxRepository, $sandboxTypes), $events);
        $this->hides = new TemporaryHides($this->reports, $targets, $this->people, $this->people, $events);
        $this->notes = new MemberNotes($this->noteRepository, $this->people, $this->clock);
        $this->journal = new PublicJournal($this->decisionRepository);
        $this->surge = new ReportSurge($this->reportRepository, $this->clock, 3, 3600);
    }
}
