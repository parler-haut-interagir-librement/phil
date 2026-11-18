<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Scorm;

use Override;
use Phil\CoursesBundle\Application\ContentUrls;
use Phil\CoursesBundle\Application\Contract\CurrentLearner;
use Phil\CoursesBundle\Domain\Model\ScormAttempt;
use Phil\CoursesBundle\Domain\Scorm\AttemptState;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\ScormAttemptId;
use Phil\CoursesBundle\Infrastructure\Doctrine\CourseTables;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalScormAttemptRepository;
use Phil\CoursesBundle\Infrastructure\Http\CourseRequest;
use Phil\CoursesBundle\Infrastructure\Http\ScormCommitPayload;
use Phil\CoursesBundle\Infrastructure\Http\ScormController;
use Phil\CoursesBundle\Infrastructure\Http\ScormFrameController;
use Phil\CoursesBundle\Infrastructure\Privacy\ScormPrivacy;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[CoversClass(DbalScormAttemptRepository::class)]
#[CoversClass(ScormPrivacy::class)]
#[CoversClass(ScormFrameController::class)]
#[CoversClass(ScormController::class)]
#[CoversClass(ScormCommitPayload::class)]
#[CoversClass(CourseRequest::class)]
#[CoversClass(ContentUrls::class)]
final class ScormInfrastructureTest extends TestCase
{
    public function testTheAttemptsOfALearnerAreExportedAndErased(): void
    {
        $store = new Store();
        $course = $store->courses->create('C');
        $activity = $store->courses->addActivity($store->courses->addModule($course->id, 'M')->id, 'A', 'scorm', ['path' => 'a.html', 'scorm_version' => 'scorm2004']);
        $ada = $store->enrollments->enroll($course->id, LearnerId::of('ada'));
        $eve = $store->enrollments->enroll($course->id, LearnerId::of('eve'));
        $launch = $store->scorm->launch($activity, $ada);
        $store->scorm->commit($launch->token, ['cmi.location' => 'p2', 'cmi.session_time' => 'PT2M'], true);
        $store->scorm->launch($activity, $eve);

        $privacy = new ScormPrivacy($store->enrollmentStore, $store->attemptStore);

        self::assertSame('courses_scorm', $privacy->module()->value);
        /** @var array{attempts: list<array<string, mixed>>} $export */
        $export = $privacy->export(DataSubject::fromString('ada'));
        self::assertCount(1, $export['attempts']);
        $attempt = $export['attempts'][0];
        self::assertSame([$launch->attempt->value, $ada->id->value, $activity->id->value, 1, '2004', 'ended', 'unknown', 'unknown', null, 'PT0H2M0S', '2026-11-13T10:00:00+00:00', '2026-11-13T10:00:00+00:00', '2026-11-13T10:00:00+00:00'], array_values(array_diff_key($attempt, ['tracking_data' => 0])));
        self::assertSame(['cmi.location' => 'p2'], array_intersect_key((array) $attempt['tracking_data'], ['cmi.location' => 0]));
        self::assertSame([], $privacy->export(DataSubject::fromString('nobody')));
        self::assertSame([], $privacy->export(DataSubject::fromString('ada@example.org')));

        /** @var array{attempts: list<array<string, mixed>>} $running */
        $running = $privacy->export(DataSubject::fromString('eve'));
        self::assertNull($running['attempts'][0]['ended_at']);

        $report = $privacy->erase(DataSubject::fromString('ada'));
        self::assertSame(['phil_course_scorm_attempt' => ErasureEffect::Deleted, 'phil_course_scorm_data' => ErasureEffect::Deleted], $report->effects);
        self::assertSame([], $privacy->export(DataSubject::fromString('ada')));
        self::assertCount(1, $store->attemptStore->ofEnrollments($eve->id));
        self::assertSame(1, Store::count($store->connection, 'SELECT COUNT(*) FROM '.CourseTables::SCORM_DATA));
        $privacy->erase(DataSubject::fromString('ada'));
        $privacy->erase(DataSubject::fromString('ada@example.org'));
        self::assertSame([], $store->attemptStore->ofEnrollments());
        $store->attemptStore->removeOfEnrollments();
        self::assertCount(1, $store->attemptStore->ofEnrollments($eve->id, $ada->id));
    }

    public function testAnAttemptIsReadBackWhateverItsRowHolds(): void
    {
        $store = new Store();
        $course = $store->courses->create('C');
        $activity = $store->courses->addActivity($store->courses->addModule($course->id, 'M')->id, 'A', 'scorm', ['path' => 'a.html', 'scorm_version' => 'scorm12']);
        $launch = $store->scorm->launch($activity, $store->enrollments->enroll($course->id, LearnerId::of('ada')));

        foreach (['{"cmi.core.lesson_location":"p","x":3,"3":"y"}' => ['cmi.core.lesson_location' => 'p'], 'not json' => [], '"text"' => [], '{"x":[1],"cmi.core.lesson_location":"p"}' => []] as $data => $expected) {
            $store->connection->update(CourseTables::SCORM_DATA, ['data' => $data], ['attempt_id' => $launch->attempt->value]);
            self::assertSame($expected, $store->attemptStore->find($launch->attempt)?->data);
        }

        $store->connection->update(CourseTables::SCORM_ATTEMPT, ['state' => 'gone', 'version' => '9', 'number' => 0, 'session' => -3], ['id' => $launch->attempt->value]);
        $attempt = $store->attemptStore->find($launch->attempt);
        self::assertInstanceOf(ScormAttempt::class, $attempt);
        self::assertSame([AttemptState::Ended, '2004', 1, 0], [$attempt->state, $attempt->version->value, $attempt->number, $attempt->session]);
        self::assertNull($store->attemptStore->find(ScormAttemptId::generate()));
    }

    public function testTheFrameIsServedOnTheContentOriginOnly(): void
    {
        $frame = new ScormFrameController(new ContentUrls('https://content.example.org/content'), 'https://www.example.org');
        $page = $frame->serve(Request::create('https://content.example.org/content/_phil/scorm/frame.html'), 'frame.html');

        self::assertStringContainsString('<html lang="en" data-parent="https://www.example.org">', (string) $page->getContent());
        self::assertStringContainsString('<script type="module" src="scorm_frame.js"></script>', (string) $page->getContent());
        self::assertSame("default-src 'none'; script-src 'self'; style-src 'unsafe-inline'; frame-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors https://www.example.org", $page->headers->get('Content-Security-Policy'));
        self::assertSame(['text/html; charset=UTF-8', 'nosniff', 'no-referrer', 'no-cache, private'], [$page->headers->get('Content-Type'), $page->headers->get('X-Content-Type-Options'), $page->headers->get('Referrer-Policy'), $page->headers->get('Cache-Control')]);

        foreach (array_keys(ScormFrameController::SCRIPTS) as $script) {
            $response = $frame->serve(Request::create('https://content.example.org/content/_phil/scorm/'.$script), $script);
            self::assertSame('text/javascript; charset=UTF-8', $response->headers->get('Content-Type'));
            self::assertStringEqualsFile(\dirname(__DIR__, 3).'/assets/src/'.$script, (string) $response->getContent());
        }

        self::assertSame('https://content.example.org/content/_phil/scorm/frame.html', new ContentUrls('https://content.example.org/content/')->runtime('frame.html'));

        foreach ([
            [$frame, 'https://www.example.org/content/_phil/scorm/frame.html', 'frame.html'],
            [$frame, 'https://content.example.org/content/_phil/scorm/other.js', 'other.js'],
            [new ScormFrameController(new ContentUrls('https://content.example.org'), 'https://www.example.org', '/nowhere'), 'https://content.example.org/_phil/scorm/lib/cmi.js', 'lib/cmi.js'],
            [new ScormFrameController(new ContentUrls('https://content.example.org'), null), 'https://content.example.org/_phil/scorm/frame.html', 'frame.html'],
            [new ScormFrameController(new ContentUrls(null), 'https://www.example.org'), 'https://content.example.org/_phil/scorm/frame.html', 'frame.html'],
        ] as [$controller, $url, $file]) {
            try {
                $controller->serve(Request::create($url), $file);
                self::fail('Served: '.$url);
            } catch (NotFoundHttpException $notFoundHttpException) {
                self::assertContains($notFoundHttpException->getMessage(), ['No such file.', 'The frame of the SCORM run time is served from the content origin only.']);
            }
        }
    }

    public function testTheParentOriginIsEscapedInTheFramePage(): void
    {
        $frame = new ScormFrameController(new ContentUrls('https://content.example.org'), 'https://www.example.org"><b>');
        $page = (string) $frame->serve(Request::create('https://content.example.org/_phil/scorm/frame.html'), 'frame.html')->getContent();

        self::assertStringContainsString('data-parent="https://www.example.org&quot;&gt;&lt;b&gt;"', $page);
    }

    public function testTheTrackingDataIsKeptAsReadableJson(): void
    {
        $store = new Store();
        $course = $store->courses->create('C');
        $activity = $store->courses->addActivity($store->courses->addModule($course->id, 'M')->id, 'A', 'scorm', ['path' => 'a.html', 'scorm_version' => 'scorm12']);
        $launch = $store->scorm->launch($activity, $store->enrollments->enroll($course->id, LearnerId::of('ada')));
        $store->scorm->commit($launch->token, ['cmi.suspend_data' => 'a/b é'], false);

        $json = $store->connection->fetchOne('SELECT data FROM '.CourseTables::SCORM_DATA);
        self::assertIsString($json);
        self::assertStringContainsString('"cmi.suspend_data":"a/b é"', $json);
    }

    /**
     * @return iterable<string, array{string, ?array{string, string, array<array-key, string|int|float>, bool}}>
     */
    public static function payloads(): iterable
    {
        yield 'a commit' => ['{"token":"t","csrf":"c","values":{"cmi.location":"p","cmi.score.raw":7,"3":1.5},"finish":true}', ['t', 'c', ['cmi.location' => 'p', 'cmi.score.raw' => 7, '3' => 1.5], true]];
        yield 'a number too large for an integer' => ['{"token":"t","csrf":"c","values":{"a":12345678901234567890123}}', ['t', 'c', ['a' => '12345678901234567890123'], false]];
        yield 'no values, no finish' => ['{"token":"t","csrf":"c"}', ['t', 'c', [], false]];
        yield 'a list of values' => ['{"token":"t","csrf":"c","values":["a"]}', ['t', 'c', ['0' => 'a'], false]];
        yield 'not JSON' => ['{', null];
        yield 'not an object' => ['"t"', null];
        yield 'no token' => ['{"csrf":"c"}', null];
        yield 'a number as a token' => ['{"token":1,"csrf":"c"}', null];
        yield 'no CSRF token' => ['{"token":"t"}', null];
        yield 'a finish that is text' => ['{"token":"t","csrf":"c","finish":"yes"}', null];
        yield 'values that are text' => ['{"token":"t","csrf":"c","values":"x"}', null];
        yield 'a value that is an object' => ['{"token":"t","csrf":"c","values":{"a":{"b":1}}}', null];
        yield 'a value that is null' => ['{"token":"t","csrf":"c","values":{"a":null}}', null];
        yield 'a value that is true' => ['{"token":"t","csrf":"c","values":{"a":true}}', null];
        yield 'too deep' => ['{"token":"t","csrf":"c","values":{"a":[[1]]}}', null];
    }

    /**
     * @param ?array{string, string, array<array-key, string|int|float>, bool} $expected
     */
    #[DataProvider('payloads')]
    public function testACommitIsReadFromItsBody(string $json, ?array $expected): void
    {
        $payload = ScormCommitPayload::fromJson($json);

        self::assertSame($expected, $payload instanceof ScormCommitPayload ? [$payload->token, $payload->csrf, $payload->values, $payload->finish] : null);
    }

    public function testTheEndpointRefusesWhatItCannotRead(): void
    {
        $store = new Store();
        $tokens = self::createStub(CsrfTokenManagerInterface::class);
        $tokens->method('isTokenValid')->willReturnCallback(static fn (CsrfToken $token): bool => 'csrf-token' === $token->getValue());
        $learner = new class implements CurrentLearner {
            #[Override]
            public function current(): ?LearnerId
            {
                return null;
            }
        };
        $controller = new ScormController($store->scorm, new CourseRequest($tokens), $learner);
        $post = static fn (string $body, array $server = []): Request => Request::create('/courses/scorm/commit', 'POST', server: $server, content: $body);

        $large = $controller->commit($post('{}', ['HTTP_CONTENT_LENGTH' => (string) (ScormController::MAX_BYTES + 1)]));
        self::assertSame([413, '{"error":"The commit is too large."}', 'no-store, private'], [$large->getStatusCode(), $large->getContent(), $large->headers->get('Cache-Control')]);
        self::assertSame(413, $controller->commit($post(str_repeat(' ', ScormController::MAX_BYTES + 1)))->getStatusCode());
        self::assertSame(400, $controller->commit($post(str_repeat(' ', ScormController::MAX_BYTES)))->getStatusCode());
        self::assertSame(400, $controller->commit($post('{}', ['HTTP_CONTENT_LENGTH' => '2']))->getStatusCode());
        self::assertSame(400, $controller->commit($post('{}', ['HTTP_CONTENT_LENGTH' => (string) ScormController::MAX_BYTES]))->getStatusCode());
        self::assertSame(403, $controller->commit($post('{"token":"t","csrf":"other"}'))->getStatusCode());
        self::assertSame('{"error":"The page expired: load it again."}', $controller->commit($post('{"token":"t","csrf":"other"}'))->getContent());
        self::assertSame('{"error":"The page expired: load it again."}', $controller->commit($post('{"token":"t","csrf":"other"}', ['HTTP_SEC_FETCH_SITE' => 'same-origin']))->getContent());
        self::assertSame(403, $controller->commit($post('{"token":"t","csrf":"csrf-token"}', ['HTTP_SEC_FETCH_SITE' => 'cross-site']))->getStatusCode());
        self::assertSame('{"error":"The attempt token is not one this site gives."}', $controller->commit($post('{"token":"t","csrf":"csrf-token"}'))->getContent());

        $course = $store->courses->create('C');
        $activity = $store->courses->addActivity($store->courses->addModule($course->id, 'M')->id, 'A', 'scorm', ['path' => 'a.html', 'scorm_version' => 'scorm12']);
        $launch = $store->scorm->launch($activity, $store->enrollments->enroll($course->id, LearnerId::of('ada')));
        $answer = $controller->commit($post((string) json_encode(['token' => $launch->token, 'csrf' => 'csrf-token', 'values' => ['cmi.core.lesson_location' => 'p', 'cmi.mood' => 'x']])));
        self::assertSame(200, $answer->getStatusCode());
        self::assertSame('no-store, private', $answer->headers->get('Cache-Control'));
        self::assertStringContainsString('"next":null', (string) $answer->getContent());
        /** @var array{errors: array<string, int>, token: string, ended: bool, next: null, exit: bool} $body */
        $body = json_decode((string) $answer->getContent(), true);
        self::assertSame([['cmi.mood' => 401], false, null, false], [$body['errors'], $body['ended'], $body['next'], $body['exit']]);
        $finished = $controller->commit($post((string) json_encode(['token' => $body['token'], 'csrf' => 'csrf-token', 'finish' => true])));
        self::assertSame(200, $finished->getStatusCode());
        self::assertSame('{"errors":{},"token":null,"ended":true,"next":null,"exit":false}', $finished->getContent());
        self::assertSame(409, $controller->commit($post((string) json_encode(['token' => $body['token'], 'csrf' => 'csrf-token'])))->getStatusCode());
    }
}
