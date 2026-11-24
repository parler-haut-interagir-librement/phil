<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Phil\ReviewsBundle\Application\Ratings;
use Phil\ReviewsBundle\Application\RemovalCriteria;
use Phil\ReviewsBundle\Application\Reviews;
use Phil\ReviewsBundle\Application\SubjectType;
use Phil\ReviewsBundle\Application\SubjectTypes;
use Phil\ReviewsBundle\Domain\Contract\ReviewRepository;
use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewContent;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;
use Phil\ReviewsBundle\Infrastructure\Doctrine\DbalRatingRepository;
use Phil\ReviewsBundle\Infrastructure\Doctrine\DbalReviewRepository;
use Phil\ReviewsBundle\Infrastructure\Doctrine\ReviewTables;
use Symfony\Component\Clock\MockClock;

/**
 * The bundle over SQLite in memory, or over the connection given, its tables created: three types — `game`, rated on
 * two criteria with four superlatives and a prior of two fictitious reviews shown from two reviews; `event`, reserved
 * to its participants for 30 days; `product`, on ten stars —, a mock clock, a recording dispatcher, the participations
 * and the responders the test sets, and the entry points.
 */
final readonly class Store
{
    public Connection $connection;

    public MockClock $clock;

    public RecordingEvents $events;

    public DbalReviewRepository $repository;

    public DbalRatingRepository $ratingRepository;

    public SubjectTypes $types;

    public Ratings $ratings;

    public Reviews $reviews;

    public FixedParticipations $participations;

    public FixedResponders $responders;

    public function __construct(?Connection $connection = null, ?ReviewRepository $repository = null, ?MockClock $clock = null)
    {
        $this->connection = $connection ?? self::sqlite();
        $this->clock = $clock ?? new MockClock('2026-11-21 10:00:00 UTC');
        $this->events = new RecordingEvents();
        $this->repository = new DbalReviewRepository($this->connection);
        $this->ratingRepository = new DbalRatingRepository($this->connection);
        $this->types = self::types();
        $this->ratings = new Ratings($this->ratingRepository, $this->types);
        $this->participations = new FixedParticipations();
        $this->responders = new FixedResponders();
        $this->reviews = new Reviews(
            $repository ?? $this->repository,
            $this->ratings,
            $this->types,
            new RemovalCriteria(),
            $this->participations,
            $this->responders,
            $this->clock,
            $this->events,
        );
    }

    public static function types(): SubjectTypes
    {
        return new SubjectTypes(
            new SubjectType('game', criteria: ['rules', 'replayability'], superlatives: ['gorgeous', 'quick', 'family', 'thinky'], priorCount: 2, minimum: 2),
            new SubjectType('event', participantsOnly: true),
            new SubjectType('product', scale: 10, superlatives: ['sturdy'], maxSuperlatives: 1, bodyLength: 40),
        );
    }

    public static function sqlite(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        self::create($connection);

        return $connection;
    }

    public static function create(Connection $connection): void
    {
        foreach (ReviewTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
    }

    public static function member(string $value): MemberId
    {
        return MemberId::fromString($value);
    }

    public static function game(string $id = 'catan'): SubjectRef
    {
        return SubjectRef::of('game', $id);
    }

    /**
     * @param array<string, int> $criteria
     * @param list<string>       $superlatives
     */
    public static function content(int $overall, array $criteria = [], ?string $body = null, array $superlatives = []): ReviewContent
    {
        return new ReviewContent($overall, $criteria, null, $body, $superlatives);
    }
}
