<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\TypeRegistry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CalendarBundle\Application\Attendees;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Application\CalendarChoices;
use Phil\CalendarBundle\Application\EventDraft;
use Phil\CalendarBundle\Application\ExpansionBudget;
use Phil\CalendarBundle\Application\PersonalCalendar;
use Phil\CalendarBundle\Application\PersonalFeeds;
use Phil\CalendarBundle\Domain\Choice\ChoiceSource;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Phil\CalendarBundle\Domain\ValueObject\Occurrence;
use Phil\CalendarBundle\Infrastructure\Doctrine\DoctrineAttendanceRepository;
use Phil\CalendarBundle\Infrastructure\Doctrine\DoctrineCalendarChoiceRepository;
use Phil\CalendarBundle\Infrastructure\Doctrine\DoctrineEventRepository;
use Phil\CalendarBundle\Infrastructure\Doctrine\DoctrinePersonalFeedTokenRepository;
use Phil\RecurrenceBundle\Infrastructure\Doctrine\RecurrenceRuleColumnType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * The calendar's tables and queries on a real server, read from the environment: a member's choices, the secret of
 * their feed and its moment in UTC, their calendar from the events they attend, host and follow, and their data
 * forgotten.
 */
abstract class ServerTestCase extends TestCase
{
    private EntityManager $entityManager;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    #[Override]
    protected function setUp(): void
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        // A configuration without a type provider of its own gives the static registry of DBAL.
        $registry = new DbalConfiguration()->getTypeProvider();
        $this->assertInstanceOf(TypeRegistry::class, $registry);

        if (!$registry->has(RecurrenceRuleColumnType::NAME)) {
            $registry->register(RecurrenceRuleColumnType::NAME, RecurrenceRuleColumnType::class);
        }

        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $parameters = new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url);
        $this->entityManager = new EntityManager(DriverManager::getConnection($parameters), $configuration);
        $tool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->entityManager->getConnection()->close();
        }
    }

    public function testAMembersCalendarChoicesAndSecret(): void
    {
        $clock = new MockClock('2026-11-12 15:00:00', 'America/Toronto');
        $events = new DoctrineEventRepository($this->entityManager);
        $attendances = new DoctrineAttendanceRepository($this->entityManager);
        $choiceRepository = new DoctrineCalendarChoiceRepository($this->entityManager);
        $calendar = new Calendar($events, null, new ExpansionBudget());
        $choices = new CalendarChoices($choiceRepository, $clock);
        $feeds = new PersonalFeeds(new DoctrinePersonalFeedTokenRepository($this->entityManager), $clock);
        $ana = Member::of('member', 'ana');

        $club = $calendar->schedule($this->draft('Club', '2026-11-10 19:00'));
        $jeux = $calendar->schedule($this->draft('Jeux', '2026-11-11 19:00'));
        $jeux->tag(['jeux']);

        $events->save($jeux);
        $hidden = $calendar->schedule($this->draft('Masqué', '2026-11-12 19:00'));
        $hidden->tag(['jeux']);

        $events->save($hidden);
        $calendar->schedule($this->draft('Autre', '2026-11-13 19:00'));
        $proposal = $calendar->schedule($this->draft('Proposé', '2026-11-14 19:00'), $ana);

        new Attendees($attendances, $clock)->attend($club, $ana);
        $choices->follow($ana, ChoiceSource::Tag, 'jeux');
        $choices->hide($ana, ChoiceSource::Event, $hidden->id);

        $secret = $feeds->issue($ana);
        $this->entityManager->clear();

        self::assertSame('2026-11-12 20:00:00 UTC', $feeds->issuedAt($ana)?->format('Y-m-d H:i:s T'), 'a moment in UTC');
        self::assertSame('2026-11-12 20:00:00 UTC', $choiceRepository->of($ana, ChoiceSource::Tag, 'jeux')?->chosenAt()->format('Y-m-d H:i:s T'));
        self::assertSame('member:ana', $feeds->memberOf($secret)?->toString());
        self::assertSame(2, $choiceRepository->count($ana));

        $personal = new PersonalCalendar($calendar, $attendances, $events, $choices);
        $zone = new DateTimeZone('America/Toronto');
        self::assertSame(['Club', 'Jeux'], array_map(static fn (Occurrence $occurrence): string => $occurrence->event->title(), $personal->occurrences($ana, new DateTimeImmutable('2026-11-01', $zone), new DateTimeImmutable('2026-12-01', $zone))));
        self::assertSame([$proposal->id], array_map(static fn (Event $event): string => $event->id, $events->proposalsOf($ana, 10)));

        $choiceRepository->forgetMember($ana);
        $feeds->revoke($ana);
        $attendances->forgetMember($ana);
        self::assertSame(1, $events->forgetProposer($ana));
        $this->entityManager->clear();

        self::assertSame([0, null, []], [$choiceRepository->count($ana), $feeds->memberOf($secret), $attendances->attendedBy($ana)]);
        self::assertNull($events->get($proposal->id)?->proposedBy());
    }

    private function draft(string $title, string $start): EventDraft
    {
        $draft = new EventDraft();
        $draft->title = $title;
        $draft->start = new DateTimeImmutable($start);
        $draft->end = new DateTimeImmutable($start)->modify('+2 hours');
        $draft->timezone = 'America/Toronto';

        return $draft;
    }
}
