<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Override;
use Phil\CoursesBundle\Application\ActivityType\XapiActivity;
use Phil\CoursesBundle\Application\Courses;
use Phil\CoursesBundle\Application\Enrollments;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\CoursesBundle\Domain\Xapi\XapiVerb;
use Phil\CoursesBundle\Tests\Functional\App\TestKernel;
use Phil\CoursesBundle\Tests\Unit\Fixture\Json;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * The bundle in an application, with its clock held: an xAPI activity launched from its page and talking to the xAPI
 * endpoint with the token of its launch; a course assigned from the console, its credits set, its reminders sent by a
 * run, its overdue state on the pages of the learner and of the manager, and the transcript as a page, JSON and CSV.
 */
#[CoversNothing]
final class TrainingAndXapiTest extends WebTestCase
{
    private const array ADA = ['HTTP_X_USER' => 'ada'];

    private const array MANAGER = ['HTTP_X_USER' => 'grace', 'HTTP_X_ROLES' => 'ROLE_COURSES_MANAGER'];

    private KernelBrowser $client;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        Store::create($this->connection());
        $this->clock()->modify('2026-11-13 10:00:00 UTC');
    }

    public function testAnXapiActivityTalksToTheEndpointWithTheTokenOfItsLaunch(): void
    {
        $course = $this->courses()->create('Sailing');
        $module = $this->courses()->addModule($course->id, 'Rigging');
        $activity = $this->courses()->addActivity($module->id, 'Knots', XapiActivity::NAME, ['activity_id' => 'https://example.org/xapi/knots', 'path' => 'knots/index.html'], CompletionRequirement::Completed);
        $this->enrollments()->enroll($course->id, LearnerId::of('ada'));

        $crawler = $this->client->request('GET', '/courses/'.$course->id->value, server: self::ADA);
        self::assertSelectorNotExists('form[action$="/complete"]');
        $this->client->submit($crawler->filter('form[action$="/'.$activity->id->value.'/launch"]')->form(), [], self::ADA);
        self::assertResponseIsSuccessful();
        $src = (string) $this->client->getCrawler()->filter('iframe.phil-courses__frame')->attr('src');
        self::assertStringStartsWith('http://content.localhost/content/'.$course->id->value.'/knots/index.html?endpoint=http%3A%2F%2Flocalhost%2Fcmi5%2Fxapi%2F&auth=Basic%20', $src);
        parse_str((string) parse_url($src, \PHP_URL_QUERY), $query);
        $xapi = ['HTTP_AUTHORIZATION' => Json::text($query, 'auth'), 'HTTP_X_EXPERIENCE_API_VERSION' => '1.0.3', 'CONTENT_TYPE' => 'application/json'];
        $actor = Json::decode(Json::text($query, 'actor'));

        $state = '/cmi5/xapi/activities/state?'.http_build_query(['activityId' => 'https://example.org/xapi/knots', 'agent' => Json::text($query, 'actor'), 'registration' => Json::text($query, 'registration'), 'stateId' => 'bookmark']);
        $this->client->request('PUT', $state, server: $xapi, content: '{"page":4}');
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', $state, server: $xapi);
        self::assertSame('{"page":4}', $this->client->getResponse()->getContent());
        $this->client->request('POST', $state, server: $xapi, content: '{"score":0.5,"page":5}');
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', $state, server: $xapi);
        self::assertSame('{"page":5,"score":0.5}', $this->client->getResponse()->getContent());
        $this->client->request('POST', $state, server: $xapi, content: '["page"]');
        self::assertResponseStatusCodeSame(400);
        $this->client->request('GET', str_replace('xapi%2Fknots', 'xapi%2Fsails', $state), server: $xapi);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/cmi5/xapi/statements', server: $xapi, content: (string) json_encode(['actor' => $actor, 'verb' => ['id' => 'https://example.org/verbs/experienced'], 'object' => ['id' => 'https://example.org/xapi/knots/page-1']]));
        self::assertResponseIsSuccessful();
        self::assertCount(1, Json::decode($this->client->getResponse()->getContent()));
        $this->client->request('POST', '/cmi5/xapi/statements', server: $xapi, content: (string) json_encode(['actor' => ['account' => ['homePage' => 'http://localhost/', 'name' => 'eve']], 'verb' => ['id' => XapiVerb::Completed->value], 'object' => ['id' => 'https://example.org/xapi/knots']]));
        self::assertResponseStatusCodeSame(403);
        $this->client->request('PUT', '/cmi5/xapi/statements?statementId=a7d1c6b8-0e0f-4c3a-9a37-2b8f1d4e5c6a', server: $xapi, content: (string) json_encode(['actor' => $actor, 'verb' => ['id' => XapiVerb::Completed->value], 'object' => ['id' => 'https://example.org/xapi/knots']]));
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/courses/'.$course->id->value, server: self::ADA);
        self::assertSelectorExists('.phil-courses__activity.is-satisfied');
        self::assertSelectorTextContains('.phil-courses__completed', 'You completed this course.');
    }

    public function testAnAssignedCourseIsRemindedOverdueAndEntersTheTranscript(): void
    {
        $course = $this->course();
        $console = $this->console();
        self::assertSame(0, $console->run(['command' => 'phil:courses:credits', 'course' => $course->id->value, 'amount' => '1.5', '--unit' => 'ceu']));
        self::assertStringContainsString('The course grants 1.5 ceu.', $this->flat($console));
        self::assertSame(1, $console->run(['command' => 'phil:courses:credits', 'course' => $course->id->value, 'amount' => '1.5', '--unit' => 'days']));
        self::assertSame(1, $console->run(['command' => 'phil:courses:credits', 'course' => $course->id->value, 'amount' => 'x']));
        self::assertSame(0, $console->run(['command' => 'phil:courses:assign', 'course' => $course->id->value, 'kind' => 'learner', 'assignee' => 'ada', 'due' => '2026-11-19', '--renew-months' => '12', '--by' => 'grace']));
        self::assertStringContainsString('the course is due before 2026-11-20 05:00 UTC.', $this->flat($console));
        self::assertSame(1, $console->run(['command' => 'phil:courses:assign', 'course' => $course->id->value, 'kind' => 'team', 'assignee' => 'ada', 'due' => '2026-11-20']));
        self::assertSame(1, $console->run(['command' => 'phil:courses:assign', 'course' => $course->id->value, 'kind' => 'learner', 'assignee' => 'ada', 'due' => '2026-02-30']));
        self::assertSame(1, $console->run(['command' => 'phil:courses:assign', 'course' => $course->id->value, 'kind' => 'group', 'assignee' => 'crew', 'due' => '2026-11-20']));
        self::assertStringContainsString('No AssigneeMembers service names the members of a group.', $this->flat($console));

        $this->client->request('GET', '/courses/training', server: self::ADA);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.phil-courses__obligation.is-due-soon [data-slot="badge"]', 'Due soon');
        self::assertSame('no-store, private', $this->client->getResponse()->headers->get('Cache-Control'));

        self::assertSame(0, $console->run(['command' => 'phil:courses:training']));
        self::assertStringContainsString('0 members reached, 1 reminders sent, 0 completions renewed.', $this->flat($console));

        $this->clock()->modify('+9 days');
        $this->client->request('GET', '/courses/training', server: self::ADA);
        self::assertSelectorTextContains('.phil-courses__obligation.is-overdue [data-slot="badge"]', 'Overdue');
        $this->client->request('GET', '/admin/courses/training', server: self::MANAGER);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.phil-courses__obligation.is-overdue th a', 'ada');
        $this->client->request('GET', '/admin/courses/training', server: self::ADA);
        self::assertResponseStatusCodeSame(403);

        $enrollment = $this->enrollments()->of($course->id, LearnerId::of('ada'));
        self::assertNotNull($enrollment);
        $this->enrollments()->record($enrollment, $this->courses()->outline($course->id)->activities()[0], Outcome::completed());

        $this->client->request('GET', '/courses/training', server: self::ADA);
        self::assertSelectorTextContains('.phil-courses__obligation.is-completed [data-slot="badge"]', 'Completed');
        $this->client->request('GET', '/courses/transcript', server: self::ADA);
        self::assertSelectorTextContains('.phil-courses__transcript tbody th', 'Fire safety');
        self::assertSelectorTextContains('.phil-courses__total td:last-child', '1.5 CEU');
        self::assertSelectorExists('.phil-courses__total th[scope="row"]:first-child + td:empty');
        self::assertSelectorNotExists('.phil-courses__transcript [colspan]');
        $this->client->request('GET', '/courses/transcript.json', server: self::ADA);
        self::assertSame('ceu', Json::at(Json::decode($this->client->getResponse()->getContent()), 'entries', 0, 'unit'));
        $this->client->request('GET', '/courses/transcript.csv', server: self::ADA);
        self::assertSame('text/csv; charset=UTF-8', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertSame('attachment; filename="transcript-ada.csv"', $this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringStartsWith("course,title,completed_at,credits,unit,assignment,cycle\r\n".$course->id->value.',Fire safety,2026-11-22T10:00:00+00:00,1.5,ceu,', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/admin/courses/transcripts/ada', server: self::MANAGER);
        self::assertSelectorTextContains('.phil-courses__learner', 'ada');
        self::assertSelectorExists('a[href="/admin/courses/transcripts/ada/csv"]');
        $this->client->request('GET', '/admin/courses/transcripts/ada/json', server: self::MANAGER);
        self::assertSame('ada', Json::at(Json::decode($this->client->getResponse()->getContent()), 'learner'));
        $this->client->request('GET', '/admin/courses/transcripts/ada/json', server: self::ADA);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/courses/transcript.xml', server: self::ADA);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/courses/training');
        self::assertResponseStatusCodeSame(403);
    }

    private function course(): Course
    {
        $course = $this->courses()->create('Fire safety');
        $this->courses()->addActivity($this->courses()->addModule($course->id, 'Basics')->id, 'Exits', 'page');

        return $course;
    }

    private function flat(ApplicationTester $console): string
    {
        return (string) preg_replace('/\s+/', ' ', $console->getDisplay(true));
    }

    private function console(): ApplicationTester
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $application = new Application($kernel);
        $application->setAutoExit(false);

        return new ApplicationTester($application);
    }

    private function clock(): MockClock
    {
        $clock = self::getContainer()->get('clock');
        self::assertInstanceOf(MockClock::class, $clock);

        return $clock;
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }

    private function courses(): Courses
    {
        $courses = self::getContainer()->get(Courses::class);
        self::assertInstanceOf(Courses::class, $courses);

        return $courses;
    }

    private function enrollments(): Enrollments
    {
        $enrollments = self::getContainer()->get(Enrollments::class);
        self::assertInstanceOf(Enrollments::class, $enrollments);

        return $enrollments;
    }
}
