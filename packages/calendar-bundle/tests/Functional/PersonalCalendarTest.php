<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CalendarBundle\Application\Attendees;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Application\CalendarChoices;
use Phil\CalendarBundle\Application\EventDraft;
use Phil\CalendarBundle\Application\ExpansionBudget;
use Phil\CalendarBundle\Application\PersonalFeeds;
use Phil\CalendarBundle\Domain\Choice\ChoiceSource;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\Repository\AttendanceRepository;
use Phil\CalendarBundle\Domain\Repository\EventRepository;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Phil\CalendarBundle\Infrastructure\Doctrine\DoctrineAttendanceRepository;
use Phil\CalendarBundle\Infrastructure\Doctrine\DoctrineCalendarChoiceRepository;
use Phil\CalendarBundle\Infrastructure\Doctrine\DoctrineEventRepository;
use Phil\CalendarBundle\Infrastructure\Doctrine\DoctrinePersonalFeedTokenRepository;
use Phil\CalendarBundle\Infrastructure\Form\CalendarChoiceType;
use Phil\CalendarBundle\PhilCalendarBundle;
use Phil\CalendarBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Environment;

/**
 * A member's own calendar on Doctrine with SQLite: the choices and the secret kept, the feed at its address, the
 * choices drawn with the recipes of the kit, the component of a member, and a member's data forgotten.
 */
#[CoversClass(PhilCalendarBundle::class)]
#[CoversClass(DoctrineCalendarChoiceRepository::class)]
#[CoversClass(DoctrinePersonalFeedTokenRepository::class)]
#[CoversClass(DoctrineAttendanceRepository::class)]
#[CoversClass(DoctrineEventRepository::class)]
final class PersonalCalendarTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $entityManager = $this->service(EntityManagerInterface::class, 'doctrine.orm.entity_manager');
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    public function testChoicesAndSecretsAreKept(): void
    {
        $choices = $this->service(CalendarChoices::class);
        $ana = Member::of('member', 'ana');
        $ben = Member::of('member', 'ben');
        $choices->follow($ana, ChoiceSource::Tag, 'jeux');
        $choices->follow($ana, ChoiceSource::Host, 'association:42');
        $choices->hide($ana, ChoiceSource::Tag, 'jeux');
        $choices->follow($ben, ChoiceSource::Tag, 'jeux');
        $this->entityManager()->clear();

        $repository = $this->service(DoctrineCalendarChoiceRepository::class);
        self::assertSame(2, $repository->count($ana));
        self::assertSame([['tag', 'jeux', true], ['host', 'association:42', false]], array_map(static fn (object $choice): array => [$choice->source()->value, $choice->value(), $choice->isHidden()], $repository->choicesOf($ana, 10)), 'by when they were chosen, then made');
        self::assertCount(1, $repository->choicesOf($ana, 1));
        self::assertSame('UTC', $repository->of($ana, ChoiceSource::Tag, 'jeux')?->chosenAt()->getTimezone()->getName());
        self::assertNull($repository->of($ana, ChoiceSource::Tag, 'autre'));

        $choices->forget($ana, ChoiceSource::Host, 'association:42');
        self::assertSame(1, $repository->count($ana));
        $repository->forgetMember($ana);
        self::assertSame([0, 1], [$repository->count($ana), $repository->count($ben)]);

        $feeds = $this->service(PersonalFeeds::class);
        $first = $feeds->issue($ana);
        $second = $feeds->issue($ana);
        $this->entityManager()->clear();
        self::assertNull($feeds->memberOf($first));
        self::assertSame('member:ana', $feeds->memberOf($second)?->toString());
        self::assertSame('UTC', $feeds->issuedAt($ana)?->getTimezone()->getName());
        $feeds->revoke($ana);
        $feeds->revoke($ana);
        self::assertNull($feeds->memberOf($second));
    }

    public function testTheFeedOfAMemberAnswersAtItsAddress(): void
    {
        $calendar = $this->service(Calendar::class);
        $now = new DateTimeImmutable();
        $club = $calendar->schedule($this->draft('Club', $now->modify('+3 days')));
        $calendar->schedule($this->draft('Autre', $now->modify('+4 days')));
        $this->service(Attendees::class)->attend($club, Member::of('member', 'ana'));
        $secret = $this->service(PersonalFeeds::class)->issue(Member::of('member', 'ana'));

        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $response = $kernel->handle(Request::create('/calendar/personal/'.$secret.'.ics'));
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringContainsString('SUMMARY:Club', (string) $response->getContent());
        self::assertStringNotContainsString('Autre', (string) $response->getContent());
        self::assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));

        self::assertSame(404, $kernel->handle(Request::create('/calendar/personal/'.str_repeat('A', 43).'.ics'))->getStatusCode());
        self::assertSame(404, $kernel->handle(Request::create('/calendar/personal/short.ics'))->getStatusCode(), 'a malformed secret matches no route');
        self::assertSame(405, $kernel->handle(Request::create('/calendar/personal/'.$secret.'.ics', 'POST'))->getStatusCode());
    }

    public function testTheChoicesAreDrawnWithTheKit(): void
    {
        $choices = $this->service(CalendarChoices::class);
        $choices->follow(Member::of('member', 'ana'), ChoiceSource::Tag, 'jeux');
        $choices->hide(Member::of('member', 'ana'), ChoiceSource::Host, 'association:42');

        $twig = $this->service(Environment::class, 'twig');
        $form = $this->service(FormFactoryInterface::class, 'test.form.factory')->createNamed('choice', CalendarChoiceType::class)->createView();

        $html = $twig->createTemplate("{{ include('@PhilCalendar/calendar/_choices.html.twig', {choices: calendar_choices('member', 'ana'), form: form, forget_route: 'phil_calendar_ics', forget_token: 'abc'}) }}")->render(['form' => $form]);
        self::assertStringContainsString('data-slot="card"', $html);
        self::assertStringContainsString('Ce que montre mon calendrier', $html);
        self::assertStringContainsString('Étiquette · jeux', $html);
        self::assertStringContainsString('Organisateur · association:42', $html);
        self::assertStringContainsString('aria-label="Oublier Étiquette jeux"', $html);
        self::assertStringContainsString('action="/calendar/calendar.ics"', $html);
        self::assertStringContainsString('name="_token" value="abc"', $html);
        self::assertStringContainsString('name="choice[value]"', $html);
        self::assertSame(1, substr_count($html, 'data-variant="outline"'), 'the hidden source');

        $bare = $twig->createTemplate("{{ include('@PhilCalendar/calendar/_choices.html.twig', {choices: calendar_choices('member', 'ben')}) }}")->render();
        self::assertSame(2, substr_count($bare, 'Rien pour l’instant.'));
        self::assertStringNotContainsString('<form', $bare);
    }

    public function testTheComponentOfAMemberAndTheBudgetOfARequest(): void
    {
        $calendar = $this->service(Calendar::class);
        $club = $calendar->schedule($this->draft('Club', new DateTimeImmutable('2026-11-10 19:00')));
        $calendar->schedule($this->draft('Autre', new DateTimeImmutable('2026-11-11 19:00')));
        $this->service(Attendees::class)->attend($club, Member::of('member', 'ana'));
        $twig = $this->service(Environment::class, 'twig');

        $mine = $twig->createTemplate('<twig:PhilCalendar:Calendar view="list" date="2026-11-12" member="member:ana" />')->render();
        self::assertStringContainsString('Club', $mine);
        self::assertStringNotContainsString('Autre', $mine);
        self::assertStringNotContainsString('phil-calendar__cut', $mine);

        $budget = $this->service(ExpansionBudget::class);
        self::assertSame(2, $budget->occurrences(), 'what the request unrolled so far');
        self::assertFalse($budget->isExhausted());
        $this->service(ResetInterface::class, 'services_resetter')->reset();
        self::assertSame(0, $budget->occurrences(), 'reset between requests');
    }

    public function testAMembersDataIsForgotten(): void
    {
        $calendar = $this->service(Calendar::class);
        $ana = Member::of('member', 'ana');
        $club = $calendar->schedule($this->draft('Club', new DateTimeImmutable('2026-11-10 19:00')));
        $proposal = $calendar->schedule($this->draft('Proposé', new DateTimeImmutable('2026-11-12 19:00')), $ana);
        $this->service(Attendees::class)->attend($club, $ana);
        $this->service(Attendees::class)->attend($club, Member::of('member', 'ben'));
        $events = $this->service(EventRepository::class);
        $attendances = $this->service(AttendanceRepository::class);

        self::assertSame([$proposal->id], array_map(static fn (Event $event): string => $event->id, $events->proposalsOf($ana, 10)));
        self::assertSame([], $events->proposalsOf(Member::of('member', 'ben'), 10));

        $attendances->forgetMember($ana);
        self::assertSame([], $attendances->attendedBy($ana));
        self::assertSame(1, $attendances->count($club, ''));
        self::assertSame(1, $events->forgetProposer($ana));
        self::assertSame(0, $events->forgetProposer($ana));
        $this->entityManager()->clear();
        self::assertNull($events->get($proposal->id)?->proposedBy());
        self::assertSame(Event::PROPOSED, $events->get($proposal->id)?->getProposalState());
    }

    private function draft(string $title, DateTimeImmutable $start): EventDraft
    {
        $draft = new EventDraft();
        $draft->title = $title;
        $draft->start = $start;
        $draft->end = $start->modify('+2 hours');
        $draft->timezone = 'America/Toronto';

        return $draft;
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->service(EntityManagerInterface::class, 'doctrine.orm.entity_manager');
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $class, ?string $id = null): object
    {
        $service = self::getContainer()->get($id ?? $class);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
