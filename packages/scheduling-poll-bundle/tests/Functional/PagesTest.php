<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Tests\Functional;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\PollBundle\Domain\Status\ResultsVisibility;
use Phil\SchedulingPollBundle\Application\ProposedSlot;
use Phil\SchedulingPollBundle\Application\SchedulingPollDraft;
use Phil\SchedulingPollBundle\Application\SchedulingPolls;
use Phil\SchedulingPollBundle\Domain\Availability;
use Phil\SchedulingPollBundle\Domain\Model\SchedulingPoll;
use Phil\SchedulingPollBundle\Domain\Model\Slot;
use Phil\SchedulingPollBundle\Infrastructure\Http\AnswerController;
use Phil\SchedulingPollBundle\Tests\Functional\App\TestKernel;
use Phil\SchedulingPollBundle\Tests\Unit\Fixture\Fixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * The pages of a respondent in a booted kernel: joining by name, answering by the personal link, without JavaScript,
 * and the table of the answers; and the templates drawn with the recipes of the PHIL UX kit.
 */
#[CoversClass(AnswerController::class)]
final class PagesTest extends KernelTestCase
{
    private SchedulingPolls $polls;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $polls = self::getContainer()->get(SchedulingPolls::class);
        self::assertInstanceOf(SchedulingPolls::class, $polls);
        $this->polls = $polls;
    }

    public function testSomeoneJoinsByNameAndIsTakenToTheirOwnLink(): void
    {
        $poll = $this->poll();
        $page = $this->request('GET', '/scheduling-poll/'.$poll->id.'/join');

        self::assertSame(Response::HTTP_OK, $page->getStatusCode());
        $this->assertSecure($page);
        $html = new Crawler((string) $page->getContent());
        self::assertSame('Game night', $html->filter('h2')->text());
        self::assertCount(2, $html->filter('ul time'));
        self::assertSame('2026-12-01T19:00:00-05:00', $html->filter('ul time')->first()->attr('datetime'));
        self::assertSame('name', $html->filter('input[name="name"][required][data-slot="input"]')->attr('autocomplete'));
        self::assertSame('email', $html->filter('input[name="email"]')->attr('type'));
        self::assertCount(1, $html->filter('button[type="submit"][data-slot="button"]'));

        $joined = $this->request('POST', '/scheduling-poll/'.$poll->id.'/join', ['name' => 'Bob', 'email' => 'bob@example.org']);
        self::assertSame(Response::HTTP_SEE_OTHER, $joined->getStatusCode());
        $this->assertSecure($joined);
        self::assertStringStartsWith('https://example.org/scheduling-poll/answer?token=', (string) $joined->headers->get('Location'));

        $refused = $this->request('POST', '/scheduling-poll/'.$poll->id.'/join', ['name' => ' ', 'email' => 'nope']);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $refused->getStatusCode());
        self::assertStringContainsString('The name of a respondent is 1 to 100 characters.', (string) $refused->getContent());
        self::assertSame('nope', new Crawler((string) $refused->getContent())->filter('input[name="email"]')->attr('value'));

        self::assertSame(Response::HTTP_NOT_FOUND, $this->request('GET', '/scheduling-poll/0195a6c4-0000-7000-8000-000000000000/join')->getStatusCode());
    }

    public function testTheAnswerPageIsATableOfRadioButtonsThatWorksWithoutJavaScript(): void
    {
        $poll = $this->poll();
        [$first, $second] = array_map(static fn (Slot $slot): string => $slot->id, $this->polls->slots($poll->id));
        $token = $this->polls->join($poll->id, 'Bob')->token;
        $page = $this->request('GET', '/scheduling-poll/answer?token='.$token);

        self::assertSame(Response::HTTP_OK, $page->getStatusCode());
        $this->assertSecure($page);
        $html = new Crawler((string) $page->getContent());
        $section = $html->filter('section.phil-scheduling-poll');
        self::assertSame('phil--scheduling-poll-bundle--timezone', $section->attr('data-controller'));
        self::assertSame('false', $section->attr('data-phil--scheduling-poll-bundle--timezone-chosen-value'));
        self::assertSame($token, $html->filter('input[type="hidden"][name="token"]')->attr('value'));
        self::assertSame('America/Toronto', $html->filter('select[name="timezone"] option[selected]')->attr('value'));
        self::assertSame('zone', $html->filter('select[name="timezone"]')->attr('data-phil--scheduling-poll-bundle--timezone-target'));
        self::assertCount(1, $html->filter('form div.isScrollable table.isTable caption'));
        self::assertSame('Proposed dates, in the time zone America/Toronto', $html->filter('form caption')->text());
        self::assertSame(['Date', 'Yes', 'If need be', 'No', 'Answers'], $html->filter('form thead th')->each(static fn (Crawler $head): string => $head->text()));

        $rows = $html->filter('form tbody tr');
        self::assertCount(2, $rows);
        self::assertSame('row', $rows->first()->filter('th')->attr('scope'));
        self::assertSame('time', $rows->first()->filter('th time')->attr('data-phil--scheduling-poll-bundle--timezone-target'));
        $id = 'phil-scheduling-poll-'.$poll->id;
        $yes = $rows->first()->filter('input[type="radio"][value="yes"]');
        self::assertSame('answers['.$first.']', $yes->attr('name'));
        self::assertSame($id.'-'.$first.' '.$id.'-yes', $yes->attr('aria-labelledby'));
        self::assertNull($yes->attr('checked'));
        self::assertSame('0 yes, 0 if need be, 0 no', $rows->first()->filter('td')->last()->text());

        $saved = $this->request('POST', '/scheduling-poll/answer', ['token' => $token, 'timezone' => 'Europe/Paris', 'answers' => [$first => 'yes', $second => 'if_need_be']]);
        self::assertSame(Response::HTTP_SEE_OTHER, $saved->getStatusCode());
        self::assertSame('https://example.org/scheduling-poll/answer?token='.$token.'&saved=1', $saved->headers->get('Location'));

        $again = new Crawler((string) $this->request('GET', '/scheduling-poll/answer?token='.$token.'&saved=1')->getContent());
        self::assertStringStartsWith('Your answers are saved.', $again->filter('p[role="status"]')->text());
        self::assertSame('Europe/Paris', $again->filter('select[name="timezone"] option[selected]')->attr('value'));
        self::assertSame('2026-12-02T01:00:00+01:00', $again->filter('tbody time')->first()->attr('datetime'));
        self::assertNotNull($again->filter('input[name="answers['.$first.']"][value="yes"]')->attr('checked'));
        self::assertNotNull($again->filter('input[name="answers['.$second.']"][value="if_need_be"]')->attr('checked'));
        self::assertSame('true', $again->filter('tbody tr')->first()->attr('data-best'));
        self::assertSame('Change my answers', $again->filter('button[type="submit"]')->text());
        self::assertSame(['Bob (you)'], $again->filter('.phil-scheduling-poll-results tbody th')->each(static fn (Crawler $head): string => $head->text()));
        self::assertSame('0', $again->filter('.phil-scheduling-poll-results [role="region"]')->attr('tabindex'));

        $refused = $this->request('POST', '/scheduling-poll/answer', ['token' => $token, 'answers' => [$first => 'maybe']]);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $refused->getStatusCode());
        self::assertStringContainsString('The answers are a map of dates to', (string) $refused->getContent());
    }

    public function testALinkThatOpensNothingSaysSoWithoutSayingWhy(): void
    {
        foreach (['GET' => '/scheduling-poll/answer?token=nope', 'POST' => '/scheduling-poll/answer'] as $method => $uri) {
            $page = $this->request($method, $uri, 'POST' === $method ? ['token' => str_repeat('A', 43)] : []);

            self::assertSame(Response::HTTP_NOT_FOUND, $page->getStatusCode());
            $this->assertSecure($page);
            self::assertStringContainsString('This link opens no poll', (string) $page->getContent());
        }
    }

    public function testAClosedPollShowsTheDateChosenAndItsEventWithoutAForm(): void
    {
        $poll = $this->poll(ResultsVisibility::AfterClosing);
        [$first] = array_map(static fn (Slot $slot): string => $slot->id, $this->polls->slots($poll->id));
        $token = $this->polls->join($poll->id, 'Bob')->token;
        $this->polls->answer($token, [$first => Availability::Yes]);

        $hidden = new Crawler((string) $this->request('GET', '/scheduling-poll/answer?token='.$token)->getContent());
        self::assertCount(0, $hidden->filter('.phil-scheduling-poll-results'));
        self::assertSame(['Date', 'Yes', 'If need be', 'No'], $hidden->filter('thead th')->each(static fn (Crawler $head): string => $head->text()));

        $this->polls->close($poll->id, Fixtures::organizer(), $first);
        $html = new Crawler((string) $this->request('GET', '/scheduling-poll/answer?token='.$token)->getContent());
        $chosen = $html->filter('.phil-scheduling-poll__chosen');

        self::assertSame('phil--scheduling-poll-bundle--chosen', $chosen->attr('data-controller'));
        self::assertSame($poll->id, $chosen->attr('data-phil--scheduling-poll-bundle--chosen-poll-value'));
        self::assertSame('2026-12-01T19:00:00-05:00', $chosen->attr('data-phil--scheduling-poll-bundle--chosen-date-value'));
        self::assertCount(1, $html->filter('[data-controller="phil--scheduling-poll-bundle--chosen"]'));
        self::assertSame('This poll is closed.', $html->filter('p.phil-scheduling-poll__status')->text());
        self::assertCount(0, $html->filter('button[type="submit"]'));
        self::assertCount(2, $html->filter('input[type="radio"][disabled][value="yes"]'));
        self::assertSame('true', $html->filter('tbody tr')->first()->attr('data-chosen'));
        self::assertCount(1, $html->filter('.phil-scheduling-poll-results'));
    }

    public function testTheOrganizerSeesTheAnswersOrASentenceWhileTheyAreHidden(): void
    {
        $poll = $this->poll(organizerSeesAnswers: false);
        [$first] = array_map(static fn (Slot $slot): string => $slot->id, $this->polls->slots($poll->id));
        $this->polls->answer($this->polls->join($poll->id, 'Bob')->token, [$first => Availability::IfNeedBe]);
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $draw = static fn (): Crawler => new Crawler($twig->createTemplate("{{ include('@PhilSchedulingPoll/results.html.twig', {results: phil_scheduling_poll_results(poll, 'user:ada')}) }}")->render(['poll' => $poll->id]));

        $hidden = $draw();
        self::assertSame('The answers are shown once the poll is closed.', $hidden->filter('[role="status"]')->text());
        self::assertSame('1 person has answered.', $hidden->filter('.phil-scheduling-poll-results__answered')->text());
        self::assertCount(0, $hidden->filter('table'));

        $this->polls->close($poll->id, Fixtures::organizer(), $first);
        $shown = $draw();
        self::assertSame(['Bob'], $shown->filter('tbody th')->each(static fn (Crawler $head): string => $head->text()));
        self::assertSame('~If need be', $shown->filter('tbody td')->first()->text());
        self::assertSame('if_need_be', $shown->filter('tbody td')->first()->attr('data-answer'));
        self::assertSame('Total', $shown->filter('tfoot th')->text());
        self::assertSame('0 yes, 1 if need be, 0 no', $shown->filter('tfoot td')->first()->text());
        self::assertCount(1, $shown->filter('[data-controller="phil--scheduling-poll-bundle--chosen"]'));
    }

    private function poll(ResultsVisibility $results = ResultsVisibility::Always, bool $organizerSeesAnswers = true): SchedulingPoll
    {
        $zone = new DateTimeZone('America/Toronto');
        $slots = [new ProposedSlot(new DateTimeImmutable('2026-12-01 19:00', $zone), new DateTimeImmutable('2026-12-01 21:00', $zone)), new ProposedSlot(new DateTimeImmutable('2026-12-02 19:00', $zone), new DateTimeImmutable('2026-12-02 21:00', $zone))];

        return $this->polls->create(new SchedulingPollDraft('Game night', $slots, 'America/Toronto', new DateTimeImmutable('2026-11-28 10:00 UTC'), organizerSeesAnswers: $organizerSeesAnswers, results: $results), Fixtures::organizer());
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function request(string $method, string $uri, array $fields = []): Response
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        return $kernel->handle(Request::create('http://localhost'.$uri, $method, $fields));
    }

    private function assertSecure(Response $response): void
    {
        self::assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        self::assertSame('noindex', $response->headers->get('X-Robots-Tag'));
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }
}
