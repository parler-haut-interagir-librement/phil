<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\AccessLinks\AccessLinks;
use Phil\AccessLinks\Contract\InvitationStore;
use Phil\AccessLinks\Dbal\DbalInvitationStore;
use Phil\AccessLinks\InMemoryInvitationStore;
use Phil\AccessLinks\LinkFormat;
use Phil\AccessLinks\TokenHasher;
use Phil\PollBundle\Domain\Status\ResultsVisibility;
use Phil\Resource\ResourceReference;
use Phil\SchedulingPollBundle\Application\ProposedSlot;
use Phil\SchedulingPollBundle\Application\SchedulingPollDraft;
use Phil\SchedulingPollBundle\Application\SchedulingPolls;
use Phil\SchedulingPollBundle\Domain\Contract\RespondentRepository;
use Phil\SchedulingPollBundle\Domain\Contract\SchedulingPollRepository;
use Phil\SchedulingPollBundle\Domain\Model\Respondent;
use Phil\SchedulingPollBundle\Domain\Model\SchedulingPoll;
use Phil\SchedulingPollBundle\Domain\Model\Slot;
use Phil\SchedulingPollBundle\Domain\Tally\AvailabilityCount;
use Phil\SchedulingPollBundle\Infrastructure\Doctrine\DoctrineRespondentRepository;
use Phil\SchedulingPollBundle\Infrastructure\Doctrine\DoctrineSchedulingPollRepository;
use Phil\SchedulingPollBundle\Infrastructure\Memory\InMemoryRespondentRepository;
use Phil\SchedulingPollBundle\Infrastructure\Memory\InMemorySchedulingPollRepository;
use Phil\Url\Url;
use Symfony\Component\Clock\MockClock;

/**
 * The services of a test: the polls on stores in memory or on SQLite, the links of `phil/access-links` on the same
 * stores, a clock that the test moves, a bus and an event dispatcher that keep what they are given.
 */
final readonly class Fixtures
{
    public const string NOW = '2026-11-21 10:00:00 UTC';

    public const string KEY = 'a key of thirty-two bytes or more, for the tests only';

    public const string PAGE = 'https://example.org/scheduling-poll/answer';

    public MockClock $clock;

    public RecordingBus $bus;

    public RecordingEvents $events;

    public SchedulingPolls $polls;

    public AccessLinks $links;

    public LinkFormat $format;

    /**
     * @param positive-int $maxRespondents
     */
    private function __construct(
        public SchedulingPollRepository $pollStore,
        public RespondentRepository $respondentStore,
        InvitationStore $invitations,
        public ?EntityManager $entityManager = null,
        ?MockClock $clock = null,
        string $lifetime = 'P30D',
        int $maxRespondents = 500,
    ) {
        $this->clock = $clock ?? new MockClock(self::NOW);
        $this->bus = new RecordingBus();
        $this->events = new RecordingEvents();
        $this->format = new LinkFormat(Url::fromString(self::PAGE));
        $this->links = new AccessLinks($invitations, new TokenHasher(self::KEY), $this->format, $this->clock);
        $this->polls = new SchedulingPolls($pollStore, $respondentStore, $this->links, $this->clock, new AvailabilityCount(), $this->bus, $this->events, $lifetime, $maxRespondents);
    }

    /**
     * @param positive-int $maxRespondents
     */
    public static function memory(string $lifetime = 'P30D', int $maxRespondents = 500, ?InvitationStore $invitations = null): self
    {
        $polls = new InMemorySchedulingPollRepository();

        return new self($polls, new InMemoryRespondentRepository($polls), $invitations ?? new InMemoryInvitationStore(), lifetime: $lifetime, maxRespondents: $maxRespondents);
    }

    public static function doctrine(?EntityManager $entityManager = null, ?MockClock $clock = null): self
    {
        $entityManager ??= self::entityManager();

        return new self(new DoctrineSchedulingPollRepository($entityManager), new DoctrineRespondentRepository($entityManager), new DbalInvitationStore($entityManager->getConnection()), $entityManager, $clock);
    }

    /**
     * An entity manager on a SQLite database in memory, its tables made — those of the links too.
     */
    public static function entityManager(?string $path = null, bool $schema = true): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $entityManager = new EntityManager(DriverManager::getConnection(null === $path ? ['driver' => 'pdo_sqlite', 'memory' => true] : ['driver' => 'pdo_sqlite', 'path' => $path]), $config);

        if (!$schema) {
            return $entityManager;
        }

        $tables = new SchemaTool($entityManager)->getSchemaFromMetadata([$entityManager->getClassMetadata(SchedulingPoll::class), $entityManager->getClassMetadata(Slot::class), $entityManager->getClassMetadata(Respondent::class)]);
        $tables = new DbalInvitationStore($entityManager->getConnection())->configureSchema($tables);

        foreach ($tables->toSql($entityManager->getConnection()->getDatabasePlatform()) as $statement) {
            $entityManager->getConnection()->executeStatement($statement);
        }

        return $entityManager;
    }

    public static function organizer(): ResourceReference
    {
        return ResourceReference::of('user', 'ada');
    }

    /**
     * A poll in Toronto on three evenings, its deadline a week away.
     *
     * @param list<string> $starts the starts of its dates, two hours each, in Toronto
     */
    public function poll(array $starts = ['2026-12-01 19:00', '2026-12-02 19:00', '2026-12-03 19:00'], bool $organizerSeesAnswers = true, ResultsVisibility $results = ResultsVisibility::Always, string $deadline = '2026-11-28 10:00:00 UTC', ?string $location = null): SchedulingPoll
    {
        $zone = new DateTimeZone('America/Toronto');
        $slots = array_map(static fn (string $start): ProposedSlot => new ProposedSlot(new DateTimeImmutable($start, $zone), new DateTimeImmutable($start, $zone)->modify('+2 hours')), $starts);

        return $this->polls->create(new SchedulingPollDraft('Game night', $slots, 'America/Toronto', new DateTimeImmutable($deadline), 'Bring dice.', $location, $organizerSeesAnswers, $results), self::organizer());
    }

    /**
     * The identifiers of the poll's slots, in order.
     *
     * @return list<string>
     */
    public function slotIds(SchedulingPoll $poll): array
    {
        return array_map(static fn (Slot $slot): string => $slot->id, $this->polls->slots($poll->id));
    }
}
