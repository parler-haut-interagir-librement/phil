<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Override;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Statements;
use Phil\CoursesBundle\Application\Courses;
use Phil\CoursesBundle\Application\Enrollments;
use Phil\CoursesBundle\Application\SecondChances;
use Phil\CoursesBundle\Domain\Cmi5\Cmi5Iri;
use Phil\CoursesBundle\Domain\Cmi5\Cmi5Verb;
use Phil\CoursesBundle\Domain\Contract\LaunchSessionRepository;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\CoursesBundle\Tests\Functional\App\SwitchableStore;
use Phil\CoursesBundle\Tests\Functional\App\TestKernel;
use Phil\CoursesBundle\Tests\Unit\Fixture\Json;
use Phil\CoursesBundle\Tests\Unit\Fixture\Packages;
use Phil\CoursesBundle\Tests\Unit\Fixture\Statements;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The bundle in an application: packages imported from the console, a learner taking a SCORM course on its pages and
 * marking its content done, the content served on the content origin only, a cmi5 unit launched, its token fetched and
 * its statements received through the xAPI endpoint, and a second chance asked for on the page and granted from the
 * console.
 */
#[CoversNothing]
final class CoursesBundleTest extends WebTestCase
{
    private const array ADA = ['HTTP_X_USER' => 'ada'];

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
    }

    public function testAScormCourseIsImportedTakenAndItsContentServedApart(): void
    {
        $console = $this->console();
        self::assertSame(0, $console->run(['command' => 'phil:courses:import', 'path' => Packages::zip(Packages::scorm12Files())]));
        self::assertStringContainsString('4 activities, 5 files', $console->getDisplay(true));
        self::assertStringContainsString('carries no learner results', $console->getDisplay(true));

        $course = $this->courses()->all()[0];
        $this->client->request('GET', '/courses/'.$course->id->value, server: self::ADA);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Workshop safety');
        self::assertSelectorTextContains('.phil-courses__not-enrolled', 'You are not enrolled');
        self::assertSelectorNotExists('form[action$="/enroll"]');

        $this->enrollments()->enroll($course->id, LearnerId::of('ada'));
        $crawler = $this->client->request('GET', '/courses/'.$course->id->value, server: self::ADA);
        self::assertSelectorExists('progress[data-slot="progress"][value="0"]');
        self::assertSelectorCount(4, '.phil-courses__activity.is-open');
        self::assertSame('no-store, private', $this->client->getResponse()->headers->get('Cache-Control'));

        $saws = $this->activity($course->id, 'saws');
        $this->client->submit($crawler->filter('form[action$="/'.$saws->id->value.'/launch"]')->form(), [], self::ADA);
        self::assertResponseIsSuccessful();
        $frame = $this->client->getCrawler()->filter('iframe.phil-courses__frame');
        self::assertSame('http://content.localhost/content/'.$course->id->value.'/content/tools/saws.html?lang=en&page=2', $frame->attr('src'));
        self::assertSame('allow-scripts allow-same-origin allow-forms allow-popups allow-downloads', $frame->attr('sandbox'));

        $this->client->submit($this->client->getCrawler()->filter('form[action$="/complete"]')->form(), [], self::ADA);
        self::assertResponseRedirects('/courses/'.$course->id->value, 303);
        $this->client->request('GET', '/courses/'.$course->id->value, server: self::ADA);
        self::assertSelectorTextContains('.phil-courses__activity.is-satisfied [data-slot="badge"]', 'Done');
        self::assertSelectorExists('progress[value="25"]');

        $this->client->request('GET', 'http://content.localhost/content/'.$course->id->value.'/content/tools/saws.html');
        self::assertResponseIsSuccessful();
        $headers = $this->client->getResponse()->headers;
        self::assertSame('<h1>Saws</h1>', $this->client->getResponse()->getContent());
        self::assertSame('text/html; charset=UTF-8', $headers->get('Content-Type'));
        self::assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        self::assertSame('no-referrer', $headers->get('Referrer-Policy'));
        self::assertStringContainsString('frame-ancestors http://localhost; ', $headers->get('Content-Security-Policy').'; ');
        self::assertStringContainsString("connect-src 'self' http://localhost", (string) $headers->get('Content-Security-Policy'));
        self::assertStringContainsString("object-src 'none'", (string) $headers->get('Content-Security-Policy'));

        $this->client->request('GET', 'http://content.localhost/content/'.$course->id->value.'/imsmanifest.xml');
        self::assertSame('application/xml', $this->client->getResponse()->headers->get('Content-Type'));
        file_put_contents($this->kernel()->storage().'/'.$course->id->value.'/run.exe', 'MZ');
        $this->client->request('GET', 'http://content.localhost/content/'.$course->id->value.'/run.exe');
        self::assertSame('application/octet-stream', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertSame('attachment', $this->client->getResponse()->headers->get('Content-Disposition'));

        foreach (['http://localhost/content/'.$course->id->value.'/content/tools/saws.html', 'http://content.localhost/content/'.$course->id->value.'/missing.html', 'http://content.localhost/content/'.$course->id->value.'/a/%2E%2E/b'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(404, $url);
        }
    }

    public function testAFormWithoutItsTokenOrForAnotherCourseIsRefused(): void
    {
        $course = $this->courses()->create('C');
        $activity = $this->courses()->addActivity($this->courses()->addModule($course->id, 'M')->id, 'A', 'page', ['body' => "Line one\nLine two"]);
        $this->enrollments()->enroll($course->id, LearnerId::of('ada'));

        $this->client->request('POST', '/courses/'.$course->id->value.'/activities/'.$activity->id->value.'/launch', server: self::ADA);
        self::assertResponseStatusCodeSame(400);
        $this->client->request('POST', '/courses/'.$course->id->value.'/enroll', server: self::ADA);
        self::assertResponseStatusCodeSame(400);

        $crawler = $this->client->request('GET', '/courses/'.$course->id->value, server: self::ADA);
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', '/courses/'.$course->id->value.'/activities/'.$activity->id->value.'/launch', ['_token' => $token], server: self::ADA);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.phil-courses__page', 'Line one');
        self::assertStringContainsString('Line one<br />', (string) $this->client->getResponse()->getContent());

        foreach (['/courses/0192f0c1-5c5e-7a3b-9e1d-0123456789ab', '/courses/'.$course->id->value.'/activities/0192f0c1-5c5e-7a3b-9e1d-0123456789ab/launch'] as $path) {
            $this->client->request(str_ends_with($path, 'launch') ? 'POST' : 'GET', $path, ['_token' => $token], server: self::ADA);
            self::assertResponseStatusCodeSame(404, $path);
        }

        $this->client->request('POST', '/courses/'.$course->id->value.'/activities/'.$activity->id->value.'/launch', ['_token' => $token], server: self::ADA + ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
        self::assertResponseStatusCodeSame(400);
        $this->client->request('POST', '/courses/'.$course->id->value.'/activities/'.$activity->id->value.'/retry', ['_token' => $token, 'reason' => 'Why not'], server: self::ADA);
        self::assertResponseStatusCodeSame(400);
    }

    public function testACmi5UnitIsLaunchedAndTalksToTheEndpoint(): void
    {
        $console = $this->console();
        self::assertSame(0, $console->run(['command' => 'phil:courses:import', 'path' => Packages::zip(Packages::cmi5Files()), '--title' => 'Charts']));
        $course = $this->courses()->all()[0];
        self::assertSame('Charts', $course->title);
        $enrollment = $this->enrollments()->enroll($course->id, LearnerId::of('ada'));
        $tides = $this->activity($course->id, 'https://example.org/aus/tides');

        $crawler = $this->client->request('GET', '/courses/'.$course->id->value, server: self::ADA);
        $this->client->submit($crawler->filter('form[action$="/'.$tides->id->value.'/launch"]')->form(), [], self::ADA);
        $src = (string) $this->client->getCrawler()->filter('iframe.phil-courses__frame')->attr('src');
        self::assertStringStartsWith('http://content.localhost/content/'.$course->id->value.'/tides/start.html?endpoint=http%3A%2F%2Flocalhost%2Fcmi5%2Fxapi%2F&fetch=', $src);
        self::assertSelectorNotExists('form[action$="/complete"]');
        parse_str((string) parse_url($src, \PHP_URL_QUERY), $query);
        $fetch = Json::text($query, 'fetch');

        $this->client->request('GET', $fetch);
        self::assertSame(['error-code' => '3', 'error-text' => 'The fetch URL is posted to.'], Json::decode($this->client->getResponse()->getContent()));
        $this->client->request('POST', $fetch, server: ['HTTP_ORIGIN' => 'http://content.localhost']);
        $token = Json::text(Json::decode($this->client->getResponse()->getContent()), 'auth-token');
        self::assertNotSame('', $token);
        self::assertSame('http://content.localhost', $this->client->getResponse()->headers->get('Access-Control-Allow-Origin'));
        $this->client->request('POST', $fetch);
        self::assertSame('1', Json::at(Json::decode($this->client->getResponse()->getContent()), 'error-code'));

        $xapi = ['HTTP_AUTHORIZATION' => 'Basic '.$token, 'HTTP_X_EXPERIENCE_API_VERSION' => '1.0.3', 'CONTENT_TYPE' => 'application/json'];
        $sessions = self::getContainer()->get(LaunchSessionRepository::class);
        $statements = self::getContainer()->get(Cmi5Statements::class);
        self::assertInstanceOf(LaunchSessionRepository::class, $sessions);
        self::assertInstanceOf(Cmi5Statements::class, $statements);
        $context = $statements->context($sessions->open($enrollment->id, $tides->id)[0]);

        $agent = (string) json_encode($context->actor());
        $state = '/cmi5/xapi/activities/state?'.http_build_query(['activityId' => $context->activityIri, 'agent' => $agent, 'registration' => $enrollment->registration, 'stateId' => Cmi5Iri::LAUNCH_DATA]);
        $this->client->request('GET', $state, server: $xapi);
        self::assertResponseIsSuccessful();
        self::assertSame('Completed', Json::at(Json::decode($this->client->getResponse()->getContent()), 'moveOn'));
        self::assertSame('1.0.3', $this->client->getResponse()->headers->get('X-Experience-API-Version'));
        $this->client->request('PUT', $state, server: $xapi, content: '{}');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', str_replace('registration=', 'registration=x', $state), server: $xapi);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', str_replace(urlencode('"name":"ada"'), urlencode('"name":"bob"'), $state), server: $xapi);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/cmi5/xapi/activities/state?agent=%7B&activityId='.urlencode($context->activityIri), server: $xapi);
        self::assertResponseStatusCodeSame(403);

        $suspend = str_replace('stateId='.urlencode(Cmi5Iri::LAUNCH_DATA), 'stateId=suspend', $state);
        $this->client->request('GET', $suspend, server: $xapi);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('PUT', $suspend, server: $xapi, content: '{"page":3}');
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', $suspend, server: $xapi);
        self::assertSame('{"page":3}', $this->client->getResponse()->getContent());
        self::assertSame('application/json', $this->client->getResponse()->headers->get('Content-Type'));
        $this->client->request('POST', $suspend, server: $xapi, content: str_repeat('a', 65_537));
        self::assertResponseStatusCodeSame(413);
        $this->client->request('DELETE', $suspend, server: $xapi);
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', str_replace('stateId=suspend', 'stateId=', $suspend), server: $xapi);
        self::assertResponseStatusCodeSame(400);

        $this->client->request('POST', '/cmi5/xapi/statements', server: $xapi, content: (string) json_encode(Statements::of($context, Cmi5Verb::Initialized)));
        self::assertResponseIsSuccessful();
        self::assertCount(1, Json::decode($this->client->getResponse()->getContent()));
        $this->client->request('POST', '/cmi5/xapi/statements', server: $xapi, content: (string) json_encode(Statements::of($context, Cmi5Verb::Initialized)));
        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'A session is initialized once.'], Json::decode($this->client->getResponse()->getContent()));
        $this->client->request('PUT', '/cmi5/xapi/statements?statementId=a7d1c6b8-0e0f-4c3a-9a37-2b8f1d4e5c6a', server: $xapi, content: (string) json_encode(Statements::completed($context)));
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', '/cmi5/xapi/statements', server: $xapi);
        self::assertResponseStatusCodeSame(403);

        $store = self::getContainer()->get(SwitchableStore::class);
        self::assertInstanceOf(SwitchableStore::class, $store);
        $store->refuse = true;
        $this->client->request('POST', '/cmi5/xapi/statements', server: $xapi, content: (string) json_encode(Statements::of($context, Cmi5Verb::Terminated, ['duration' => 'PT1M'])));
        self::assertResponseStatusCodeSame(503);
        self::assertSame(['error' => 'The statements could not be kept: send them again later.'], Json::decode($this->client->getResponse()->getContent()));
        $this->client->request('POST', '/courses/'.$course->id->value.'/activities/'.$this->activity($course->id, 'https://example.org/aus/welcome')->id->value.'/launch', ['_token' => (string) $crawler->filter('input[name="_token"]')->attr('value')], server: self::ADA);
        self::assertResponseStatusCodeSame(503);
        self::assertSelectorTextContains('.phil-courses__error', 'The launch could not be recorded: try again later.');
        $store->refuse = false;

        $this->client->request('GET', '/cmi5/xapi/agents/profile?profileId=cmi5LearnerPreferences', server: $xapi);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('PUT', '/cmi5/xapi/agents/profile?profileId=cmi5LearnerPreferences', server: $xapi, content: '{}');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/courses/'.$course->id->value, server: self::ADA);
        self::assertSelectorCount(1, '.phil-courses__activity.is-satisfied');
        self::assertSelectorCount(2, '.phil-courses__activity.is-open');

        $this->client->request('POST', '/cmi5/xapi/statements', server: ['HTTP_X_EXPERIENCE_API_VERSION' => '1.0.3'], content: '{}');
        self::assertResponseStatusCodeSame(401);
        $this->client->request('POST', '/cmi5/xapi/statements', server: ['HTTP_AUTHORIZATION' => 'Basic '.$token], content: '{}');
        self::assertResponseStatusCodeSame(400);
        $this->client->request('GET', '/cmi5/xapi/agents/profile', server: ['HTTP_X_EXPERIENCE_API_VERSION' => '0.9']);
        self::assertResponseStatusCodeSame(400);
        $this->client->request('GET', '/cmi5/xapi/activities/state', server: ['HTTP_X_EXPERIENCE_API_VERSION' => '1.0.3']);
        self::assertResponseStatusCodeSame(401);

        $this->client->request('OPTIONS', '/cmi5/xapi/statements', server: ['HTTP_ORIGIN' => 'https://units.example.net']);
        self::assertResponseStatusCodeSame(204);
        $headers = $this->client->getResponse()->headers;
        self::assertSame('https://units.example.net', $headers->get('Access-Control-Allow-Origin'));
        self::assertSame('Authorization, Content-Type, X-Experience-API-Version', $headers->get('Access-Control-Allow-Headers'));
        self::assertSame('GET, PUT, POST, DELETE, OPTIONS', $headers->get('Access-Control-Allow-Methods'));
        self::assertSame('X-Experience-API-Version', $headers->get('Access-Control-Expose-Headers'));
        self::assertSame('600', $headers->get('Access-Control-Max-Age'));
        self::assertContains('Origin', $this->client->getResponse()->getVary());
        $this->client->request('OPTIONS', '/cmi5/xapi/statements', server: ['HTTP_ORIGIN' => 'https://evil.example.net']);
        self::assertNull($this->client->getResponse()->headers->get('Access-Control-Allow-Origin'));

        foreach (['/cmi5/xapi/', '/cmi5/xapi/about'] as $about) {
            $this->client->request('GET', $about);
            self::assertSame(['version' => ['1.0.3']], Json::decode($this->client->getResponse()->getContent()));
        }
    }

    public function testAUnitOfTheContentOriginIsNotFramedOnTheSiteItself(): void
    {
        $course = $this->courses()->create('C');
        $activity = $this->courses()->addActivity($this->courses()->addModule($course->id, 'M')->id, 'A', 'scorm', ['path' => 'a.html']);
        $this->enrollments()->enroll($course->id, LearnerId::of('ada'));
        $crawler = $this->client->request('GET', 'http://content.localhost/courses/'.$course->id->value, server: self::ADA);

        $this->client->submit($crawler->filter('form[action$="/launch"]')->form(), [], self::ADA);
        self::assertResponseStatusCodeSame(409);
        self::assertSelectorTextContains('.phil-courses__error', 'The content origin is the site itself');
        self::assertSame($activity->title, $this->client->getCrawler()->filter('h1')->text());
    }

    public function testASecondChanceIsAskedForOnThePageAndGrantedFromTheConsole(): void
    {
        $course = $this->courses()->create('Exam', '', true);
        $module = $this->courses()->addModule($course->id, 'M');
        $exam = $this->courses()->addActivity($module->id, 'Exam', 'page', [], CompletionRequirement::Passed, true, 1);
        $after = $this->courses()->addActivity($module->id, 'After', 'page');
        $enrollment = $this->enrollments()->enroll($course->id, LearnerId::of('ada'));
        $this->enrollments()->record($enrollment, $exam, Outcome::failed());

        $crawler = $this->client->request('GET', '/courses/'.$course->id->value, server: self::ADA);
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');
        self::assertSelectorTextContains('.phil-courses__activity.is-retry-needed [data-slot="badge"]', 'Out of tries');
        self::assertSelectorTextContains('.phil-courses__activity.is-locked .phil-courses__waiting', 'Opens once the activity before it is done.');
        $this->client->submit($crawler->filter('form.phil-courses__retry')->form(['reason' => 'I was ill.']), [], self::ADA);
        self::assertResponseRedirects('/courses/'.$course->id->value, 303);

        $this->client->request('GET', '/courses/'.$course->id->value, server: self::ADA);
        self::assertSelectorTextContains('.phil-courses__activity.is-retry-requested [data-slot="badge"]', 'Another try asked for');
        $this->client->request('POST', '/courses/'.$course->id->value.'/activities/'.$after->id->value.'/launch', ['_token' => $token], server: self::ADA);
        self::assertResponseStatusCodeSame(409);
        self::assertSelectorTextContains('.phil-courses__error', 'This activity is not open: it is locked.');
        $this->client->request('POST', '/courses/'.$course->id->value.'/activities/'.$after->id->value.'/complete', ['_token' => $token], server: self::ADA);
        self::assertResponseStatusCodeSame(400);

        $console = $this->console();
        self::assertSame(0, $console->run(['command' => 'phil:courses:retries']));
        self::assertStringContainsString('I was ill.', $console->getDisplay(true));
        $request = Json::text([$this->connection()->fetchOne('SELECT id FROM phil_course_retry')], 0);
        self::assertSame(2, $console->run(['command' => 'phil:courses:retries', '--grant' => $request]));
        self::assertStringContainsString('Give one of --grant, --refuse or --waive', $console->getDisplay(true));
        self::assertSame(0, $console->run(['command' => 'phil:courses:retries', '--grant' => $request, '--by' => 'teacher-1']));
        self::assertStringContainsString('is granted', $console->getDisplay(true));
        self::assertSame(1, $console->run(['command' => 'phil:courses:retries', '--refuse' => $request, '--by' => 'teacher-1']));
        self::assertStringContainsString('is decided already', $console->getDisplay(true));
        self::assertSame(2, $console->run(['command' => 'phil:courses:retries', '--refuse' => $request, '--waive' => $request, '--by' => 'teacher-1']));
        self::assertStringContainsString('Give one of --grant, --refuse or --waive', $console->getDisplay(true));

        $this->client->request('GET', '/courses/'.$course->id->value, server: self::ADA);
        self::assertSelectorCount(1, '.phil-courses__activity.is-open');
    }

    public function testATeacherDecidesTheSecondChancesOnTheAdministrationPage(): void
    {
        $course = $this->courses()->create('Exam');
        $exam = $this->courses()->addActivity($this->courses()->addModule($course->id, 'M')->id, 'Final exam', 'page', [], CompletionRequirement::Passed, true, 1);
        $requests = [];

        foreach (['ada', 'bob', 'cy'] as $learner) {
            $enrollment = $this->enrollments()->enroll($course->id, LearnerId::of($learner));
            $this->enrollments()->record($enrollment, $exam, Outcome::failed());
            $requests[$learner] = $this->secondChances()->request($enrollment, $exam->id, 'I was ill, '.$learner.'.')->id->value;
        }

        $teacher = ['HTTP_X_USER' => 'teacher-1', 'HTTP_X_ROLES' => 'ROLE_USER,ROLE_COURSES_TEACHER'];

        foreach ([self::ADA, ['HTTP_X_USER' => 'teacher-1', 'HTTP_X_ROLES' => 'ROLE_USER'], ['HTTP_X_ROLES' => 'ROLE_COURSES_TEACHER']] as $server) {
            $this->client->request('GET', '/admin/courses/second-chances', server: $server);
            self::assertResponseStatusCodeSame(403);
        }

        $crawler = $this->client->request('GET', '/admin/courses/second-chances', server: $teacher);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertSelectorTextContains('h1', 'Second chances waiting for a decision');
        self::assertSelectorCount(3, '.phil-courses__retry');
        self::assertSelectorTextContains('.phil-courses__retry h2', 'Exam — Final exam');
        self::assertSelectorTextContains('.phil-courses__retry [data-slot="badge"]', 'Waiting');
        self::assertSelectorTextContains('.phil-courses__retry dl', 'I was ill, ada.');
        self::assertSelectorTextContains('.phil-courses__retry dl', '1 failed of 1');
        self::assertSame('1', $crawler->filter('.phil-courses__retry progress')->first()->attr('value'));
        self::assertSame(['grant', 'refuse', 'waive'], $crawler->filter('.phil-courses__retry')->first()->filter('button[name="decision"]')->each(static fn (Crawler $button): string => (string) $button->attr('value')));
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->submit($crawler->filter('.phil-courses__retry form')->first()->selectButton('Grant another try')->form(['note' => 'Once more.']), [], $teacher);
        self::assertResponseRedirects('/admin/courses/second-chances', 303);
        $this->client->request('POST', '/admin/courses/second-chances/'.$requests['bob'], ['_token' => $token, 'decision' => 'refuse', 'note' => 'Not this time.'], server: $teacher);
        self::assertResponseRedirects('/admin/courses/second-chances', 303);

        foreach ([
            [['_token' => $token, 'decision' => 'forgive'], $requests['cy'], 400],
            [['decision' => 'waive'], $requests['cy'], 400],
            [['_token' => $token, 'decision' => 'waive'], '01234567-89ab-cdef-0123-456789abcdef', 404],
            [['_token' => $token, 'decision' => 'grant'], $requests['bob'], 400],
            [['_token' => $token, 'decision' => 'waive', 'note' => str_repeat('é', 2001)], $requests['cy'], 400],
        ] as [$form, $request, $status]) {
            $this->client->request('POST', '/admin/courses/second-chances/'.$request, $form, server: $teacher);
            self::assertResponseStatusCodeSame($status);
        }

        $this->client->request('POST', '/admin/courses/second-chances/'.$requests['cy'], ['_token' => $token, 'decision' => 'waive'], server: ['HTTP_X_USER' => 'ada']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/admin/courses/second-chances/'.$requests['cy'], ['_token' => $token, 'decision' => 'waive', 'note' => 'Done at the workshop.'], server: $teacher);
        self::assertResponseRedirects('/admin/courses/second-chances', 303);

        $decided = $this->connection()->fetchAllKeyValue("SELECT id, decision || '|' || decided_by || '|' || COALESCE(note, '') FROM phil_course_retry");
        self::assertSame(['granted|teacher-1|Once more.', 'refused|teacher-1|Not this time.', 'waived|teacher-1|Done at the workshop.'], [$decided[$requests['ada']], $decided[$requests['bob']], $decided[$requests['cy']]]);

        $this->client->request('GET', '/admin/courses/second-chances', server: $teacher);
        self::assertSelectorTextContains('.phil-courses__none', 'No request is waiting for a decision.');
    }

    public function testTheResultsOfAnotherPlatformAreImportedFromTheConsole(): void
    {
        $console = $this->console();
        $console->run(['command' => 'phil:courses:import', 'path' => Packages::zip(Packages::scorm12Files())]);

        $course = $this->courses()->all()[0];
        $csv = sys_get_temp_dir().'/phil-courses-results-'.bin2hex(random_bytes(4)).'.csv';
        file_put_contents($csv, "learner,item,status,score,date\nada,intro,completed,,2025-06-01\n\nbob,saws,passed,90,2025-06-02T10:00:00Z\ncy,nowhere,passed,,\n");

        self::assertSame(1, $console->run(['command' => 'phil:courses:import-results', 'course' => $course->id->value, 'file' => $csv]));
        self::assertStringContainsString('2 results imported.', $console->getDisplay(true));
        self::assertStringContainsString('No activity of the course comes from the item "nowhere".', $console->getDisplay(true));
        self::assertNotNull($this->enrollments()->of($course->id, LearnerId::of('bob')));

        file_put_contents($csv, "status,item,learner\ncompleted,outro,dee\n");
        self::assertSame(0, $console->run(['command' => 'phil:courses:import-results', 'course' => $course->id->value, 'file' => $csv]));
        self::assertSame(1, $console->run(['command' => 'phil:courses:import-results', 'course' => 'not a course', 'file' => $csv]));
        self::assertSame(1, $console->run(['command' => 'phil:courses:import-results', 'course' => $course->id->value, 'file' => $csv.'.missing']));
        self::assertStringContainsString('The file cannot be read', $console->getDisplay(true));
        self::assertSame(1, $console->run(['command' => 'phil:courses:import', 'path' => $csv]));
        self::assertStringContainsString('not a ZIP archive', $console->getDisplay(true));
    }

    private function console(): ApplicationTester
    {
        $application = new Application($this->kernel());
        $application->setAutoExit(false);

        return new ApplicationTester($application);
    }

    private function kernel(): TestKernel
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(TestKernel::class, $kernel);

        return $kernel;
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

    private function secondChances(): SecondChances
    {
        $secondChances = self::getContainer()->get(SecondChances::class);
        self::assertInstanceOf(SecondChances::class, $secondChances);

        return $secondChances;
    }

    private function enrollments(): Enrollments
    {
        $enrollments = self::getContainer()->get(Enrollments::class);
        self::assertInstanceOf(Enrollments::class, $enrollments);

        return $enrollments;
    }

    private function activity(CourseId $course, string $source): Activity
    {
        $activity = array_find($this->courses()->outline($course)->activities(), static fn (Activity $activity): bool => $source === $activity->source);
        self::assertInstanceOf(Activity::class, $activity);

        return $activity;
    }
}
