<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Fixture;

use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Phil\CoursesBundle\Application\ActivityType\Cmi5Activity;
use Phil\CoursesBundle\Application\ActivityType\LinkActivity;
use Phil\CoursesBundle\Application\ActivityType\PageActivity;
use Phil\CoursesBundle\Application\ActivityType\ScormActivity;
use Phil\CoursesBundle\Application\ActivityType\XapiActivity;
use Phil\CoursesBundle\Application\ActivityTypes;
use Phil\CoursesBundle\Application\Availabilities;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Launcher;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Settings;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Statements;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Tokens;
use Phil\CoursesBundle\Application\ContentUrls;
use Phil\CoursesBundle\Application\Courses;
use Phil\CoursesBundle\Application\Enrollments;
use Phil\CoursesBundle\Application\Import\CourseImporter;
use Phil\CoursesBundle\Application\Import\ResultImporter;
use Phil\CoursesBundle\Application\Learning;
use Phil\CoursesBundle\Application\Scorm\ScormRuntime;
use Phil\CoursesBundle\Application\Scorm\ScormTokens;
use Phil\CoursesBundle\Application\SecondChances;
use Phil\CoursesBundle\Application\Training\Assignments;
use Phil\CoursesBundle\Application\Training\TrainingCompletions;
use Phil\CoursesBundle\Application\Training\TrainingRun;
use Phil\CoursesBundle\Application\Training\TrainingSettings;
use Phil\CoursesBundle\Application\Training\Transcripts;
use Phil\CoursesBundle\Application\Xapi\XapiLauncher;
use Phil\CoursesBundle\Application\Xapi\XapiStatements;
use Phil\CoursesBundle\Infrastructure\Doctrine\CourseTables;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalAssignmentRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalCourseRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalEnrollmentRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalLaunchSessionRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalLearningRecordStore;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalRetryRequestRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalScormAttemptRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalTranscriptRepository;
use Phil\ElearningPackages\Archive\ZipPackageOpener;
use Phil\ElearningPackages\PackageLimits;
use Symfony\Component\Clock\MockClock;

/**
 * The bundle over SQLite in memory, or over the connection given, its tables created: the DBAL stores, a mock clock
 * at 2026-11-13 10:00 UTC, a recording dispatcher, a content storage in memory, a site in the Toronto time zone at
 * https://learn.example.org whose content origin is https://content.example.org, and every entry point.
 */
final readonly class Store
{
    public const string HOME = 'https://learn.example.org';

    public const string CONTENT = 'https://content.example.org';

    public const string SECRET = 'a test secret of the cmi5 tokens';

    public Connection $connection;

    public MockClock $clock;

    public RecordingEvents $events;

    public MemoryStorage $storage;

    public DbalCourseRepository $courseStore;

    public DbalEnrollmentRepository $enrollmentStore;

    public DbalRetryRequestRepository $retryStore;

    public DbalLaunchSessionRepository $sessionStore;

    public DbalLearningRecordStore $lrs;

    public ContentUrls $urls;

    public ActivityTypes $types;

    public Courses $courses;

    public Enrollments $enrollments;

    public Availabilities $availabilities;

    public Learning $learning;

    public SecondChances $secondChances;

    public Cmi5Settings $settings;

    public Cmi5Statements $statements;

    public Cmi5Tokens $tokens;

    public Cmi5Launcher $launcher;

    public CourseImporter $importer;

    public ResultImporter $results;

    public DbalScormAttemptRepository $attemptStore;

    public ScormTokens $scormTokens;

    public ScormRuntime $scorm;

    public XapiLauncher $xapiLauncher;

    public XapiStatements $xapi;

    public DbalAssignmentRepository $assignmentStore;

    public DbalTranscriptRepository $transcriptStore;

    public RecordingNotifier $notices;

    public Members $members;

    public TrainingSettings $training;

    public Assignments $assignments;

    public Transcripts $transcripts;

    public TrainingRun $run;

    public TrainingCompletions $completions;

    public function __construct(?Connection $connection = null, PackageLimits $limits = new PackageLimits())
    {
        $this->connection = $connection ?? self::sqlite();
        $this->clock = new MockClock('2026-11-13 10:00:00 UTC');
        $this->events = new RecordingEvents();
        $this->storage = new MemoryStorage();
        $this->courseStore = new DbalCourseRepository($this->connection);
        $this->enrollmentStore = new DbalEnrollmentRepository($this->connection);
        $this->retryStore = new DbalRetryRequestRepository($this->connection);
        $this->sessionStore = new DbalLaunchSessionRepository($this->connection);
        $this->lrs = new DbalLearningRecordStore($this->connection, $this->clock);
        $this->urls = new ContentUrls(self::CONTENT);
        $this->settings = new Cmi5Settings(self::HOME);
        $this->enrollments = new Enrollments($this->courseStore, $this->enrollmentStore, $this->clock, $this->events);
        $this->statements = new Cmi5Statements($this->lrs, $this->sessionStore, $this->enrollmentStore, $this->courseStore, $this->enrollments, $this->settings, $this->clock, $this->events);
        $this->tokens = new Cmi5Tokens($this->sessionStore, $this->clock, self::SECRET);
        $this->launcher = new Cmi5Launcher($this->sessionStore, $this->statements, new FixedEndpoints(), $this->urls, $this->settings, $this->clock);
        $this->xapiLauncher = new XapiLauncher($this->sessionStore, $this->tokens, new FixedEndpoints(), $this->urls, $this->settings, $this->clock);
        $this->types = new ActivityTypes([new PageActivity(), new LinkActivity(), new ScormActivity($this->urls), new Cmi5Activity($this->launcher), new XapiActivity($this->xapiLauncher)]);
        $this->courses = new Courses($this->courseStore, $this->types, $this->clock);
        $this->availabilities = new Availabilities($this->courseStore, $this->enrollmentStore, $this->retryStore, $this->clock, new DateTimeZone('America/Toronto'));
        $this->learning = new Learning($this->enrollments, $this->availabilities, $this->types);
        $this->secondChances = new SecondChances($this->retryStore, $this->courses, $this->enrollments, $this->availabilities, $this->clock, $this->events);
        $this->importer = new CourseImporter(new ZipPackageOpener(), $this->courses, $this->storage, $limits, $this->events, 'fr');
        $this->results = new ResultImporter($this->courses, $this->enrollments);
        $this->attemptStore = new DbalScormAttemptRepository($this->connection);
        $this->scormTokens = new ScormTokens($this->clock, self::SECRET);
        $this->scorm = new ScormRuntime($this->attemptStore, $this->courseStore, $this->enrollmentStore, $this->enrollments, $this->scormTokens, $this->clock, $this->events);
        $this->xapi = new XapiStatements($this->lrs, $this->enrollmentStore, $this->courseStore, $this->enrollments, $this->settings, $this->events);
        $this->assignmentStore = new DbalAssignmentRepository($this->connection);
        $this->transcriptStore = new DbalTranscriptRepository($this->connection);
        $this->notices = new RecordingNotifier();
        $this->members = new Members();
        $this->training = new TrainingSettings(7, 30, 3, 2);
        $this->assignments = new Assignments($this->assignmentStore, $this->courseStore, $this->enrollments, $this->training, $this->clock, [$this->members], $this->notices, $this->events);
        $this->transcripts = new Transcripts($this->transcriptStore, $this->courseStore, $this->events);
        $this->run = new TrainingRun($this->assignmentStore, $this->assignments, $this->enrollmentStore, $this->enrollments, $this->training, $this->clock, $this->notices, $this->events);
        $this->completions = new TrainingCompletions($this->enrollmentStore, $this->assignments, $this->transcripts);
    }

    public static function sqlite(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        self::create($connection);

        return $connection;
    }

    public static function create(Connection $connection): void
    {
        foreach (CourseTables::configure(Schema::editor()->create(), $connection->getDatabasePlatform())->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
    }

    /**
     * The tables as the schema had them before the blocks, the satisfied blocks, the notes of the decisions and the SCORM
     * attempts.
     */
    public static function createEarlier(Connection $connection): void
    {
        $schema = CourseTables::configure(Schema::editor()->create());
        $editor = $schema->edit()->dropTableByUnquotedName(CourseTables::BLOCK)->dropTableByUnquotedName(CourseTables::SATISFACTION)
            ->dropTableByUnquotedName(CourseTables::SCORM_ATTEMPT)->dropTableByUnquotedName(CourseTables::SCORM_DATA);

        foreach ([CourseTables::ACTIVITY => 'block_id', CourseTables::RETRY => 'note'] as $table => $column) {
            $editor->dropTableByUnquotedName($table)->addTable($schema->getTable($table)->edit()->dropColumnByUnquotedName($column)->create());
        }

        // An index the earlier schema lacked, which the upgrade adds.
        $retry = $schema->getTable(CourseTables::RETRY)->edit()->dropColumnByUnquotedName('note')->dropIndexByUnquotedName('idx_phil_course_retry_pending')->create();
        $editor->dropTableByUnquotedName(CourseTables::RETRY)->addTable($retry);

        foreach ($editor->create()->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
    }

    /**
     * The tables as the schema had them before the time of the SCORM attempts.
     */
    public static function createWithoutTime(Connection $connection): void
    {
        $schema = CourseTables::configure(Schema::editor()->create(), $connection->getDatabasePlatform());
        $attempt = $schema->getTable(CourseTables::SCORM_ATTEMPT)->edit()->dropColumnByUnquotedName('elapsed')->dropColumnByUnquotedName('session_opened_at')->create();

        foreach ($schema->edit()->dropTableByUnquotedName(CourseTables::SCORM_ATTEMPT)->addTable($attempt)->create()->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
    }

    /**
     * The number the query counts.
     *
     * @param list<mixed> $parameters
     */
    public static function count(Connection $connection, string $sql, array $parameters = []): int
    {
        $count = $connection->fetchOne($sql, $parameters);

        return is_numeric($count) ? (int) $count : -1;
    }

    public static function drop(Connection $connection): void
    {
        foreach (CourseTables::ALL as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }
    }
}
