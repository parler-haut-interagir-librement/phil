<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Xapi;

use Phil\CoursesBundle\Application\ActivityType\XapiActivity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Infrastructure\Http\Cmi5Controller;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The state documents of an xAPI activity, put whole or posted as JSON objects merged into the one kept.
 */
#[CoversClass(Cmi5Controller::class)]
final class XapiStateTest extends TestCase
{
    private const string IRI = 'https://example.org/xapi/knots';

    private Store $store;

    private Enrollment $enrollment;

    private Cmi5Controller $controller;

    private string $token;

    protected function setUp(): void
    {
        $this->store = new Store();
        $course = $this->store->courses->create('Sailing');
        $module = $this->store->courses->addModule($course->id, 'Rigging');
        $knots = $this->store->courses->addActivity($module->id, 'Knots', XapiActivity::NAME, ['activity_id' => self::IRI, 'path' => 'knots/index.html'], CompletionRequirement::Completed);
        $this->enrollment = $this->store->enrollments->enroll($course->id, LearnerId::of('ada'));
        $this->store->learning->launch($this->enrollment, $knots->id);
        $session = $this->store->sessionStore->open($this->enrollment->id, $knots->id)[0];
        $this->token = 'Basic '.$this->store->tokens->token($session->id);
        $this->controller = new Cmi5Controller($this->store->tokens, $this->store->statements, $this->store->lrs, [], $this->store->xapi);
    }

    public function testAPostedObjectIsKeptAsWrittenThenMergedAtTheTopLevel(): void
    {
        self::assertSame(204, $this->send('POST', ' {"a": {"x": 1}, "list": [1, 2]} ')->getStatusCode());
        self::assertSame(' {"a": {"x": 1}, "list": [1, 2]} ', $this->kept());

        self::assertSame(204, $this->send('POST', '{"a":{"y":"é/2"},"b":1.0,"":"empty","0":"zero"}')->getStatusCode());
        self::assertSame('{"a":{"y":"é/2"},"list":[1,2],"b":1.0,"":"empty","0":"zero"}', $this->kept());

        self::assertSame(204, $this->send('PUT', '[1]')->getStatusCode());
        self::assertSame('[1]', $this->kept());
    }

    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function refusedPosts(): iterable
    {
        yield 'a list posted' => [null, '[{"a":1}]'];
        yield 'text posted' => [null, 'page=4'];
        yield 'nothing posted' => [null, ''];
        yield 'a number posted' => [null, '4'];
        yield 'too deep' => [null, str_repeat('{"a":', 64).'1'.str_repeat('}', 64)];
        yield 'a list kept' => ['[1]', '{"a":1}'];
        yield 'text kept' => ['page=4', '{"a":1}'];
        yield 'an empty object posted over text' => ['page=4', '{}'];
    }

    #[DataProvider('refusedPosts')]
    public function testAPostThatIsNotAnObjectOrOverAnotherKindIsRefused(?string $kept, string $posted): void
    {
        if (null !== $kept) {
            $this->send('PUT', $kept);
        }

        $response = $this->send('POST', $posted);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('{"error":"A state is posted as a JSON object, merged into the JSON object kept; a document of another kind is put."}', $response->getContent());
        self::assertSame($kept, $this->kept());
    }

    public function testAnObjectNestedWithinTheBoundIsMerged(): void
    {
        $deep = str_repeat('{"a":', 63).'1'.str_repeat('}', 63);

        self::assertSame(204, $this->send('POST', $deep)->getStatusCode());
        self::assertSame($deep, $this->kept());
    }

    public function testTheMergedDocumentIsBounded(): void
    {
        $half = '"'.str_repeat('a', intdiv(Cmi5Controller::STATE_BYTES, 2)).'"';
        $this->send('PUT', '{"a":'.$half.'}');

        $response = $this->send('POST', '{"b":'.$half.'}');

        self::assertSame(413, $response->getStatusCode());
        self::assertSame('{"error":"A state document is 65536 bytes at most."}', $response->getContent());
        self::assertSame('{"a":'.$half.'}', $this->kept());

        $exact = '{"b":"'.str_repeat('b', Cmi5Controller::STATE_BYTES - \strlen('{"a":'.$half.',"b":""}')).'"}';
        self::assertSame(204, $this->send('POST', $exact)->getStatusCode());
        self::assertSame(Cmi5Controller::STATE_BYTES, \strlen((string) $this->kept()));
    }

    private function send(string $method, string $body): Response
    {
        $request = Request::create('/cmi5/xapi/activities/state?'.http_build_query([
            'activityId' => self::IRI,
            'agent' => '{"objectType":"Agent","account":{"homePage":"https://learn.example.org/","name":"ada"}}',
            'registration' => $this->enrollment->registration,
            'stateId' => 'suspend',
        ]), $method, server: ['HTTP_AUTHORIZATION' => $this->token, 'HTTP_X_EXPERIENCE_API_VERSION' => '1.0.3', 'CONTENT_TYPE' => 'application/json'], content: $body);

        return $this->controller->state($request);
    }

    private function kept(): ?string
    {
        return $this->store->lrs->state(self::IRI, $this->enrollment->registration, 'suspend');
    }
}
