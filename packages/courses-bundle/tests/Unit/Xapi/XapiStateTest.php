<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Xapi;

use Phil\CoursesBundle\Application\ActivityType\XapiActivity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\Module;
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

    private Module $module;

    private Cmi5Controller $controller;

    private string $token;

    protected function setUp(): void
    {
        $this->store = new Store();
        $course = $this->store->courses->create('Sailing');
        $this->module = $this->store->courses->addModule($course->id, 'Rigging');
        $knots = $this->store->courses->addActivity($this->module->id, 'Knots', XapiActivity::NAME, ['activity_id' => self::IRI, 'path' => 'knots/index.html'], CompletionRequirement::Completed);
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

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function foreignRequests(): iterable
    {
        yield 'another activity' => [['activityId' => 'https://example.org/xapi/other']];
        yield 'another registration' => [['registration' => 'other']];
        yield 'another home page' => [['agent' => '{"account":{"homePage":"https://other.example.org/","name":"ada"}}']];
        yield 'another learner' => [['agent' => '{"account":{"homePage":"https://learn.example.org/","name":"bob"}}']];
        yield 'an agent without account' => [['agent' => '{}']];
        yield 'an agent that is not an object' => [['agent' => '"ada"']];
        yield 'an account that is not an object' => [['agent' => '{"account":"ada"}']];
        yield 'no agent' => [['agent' => '']];
        yield 'an agent nested too deep' => [['agent' => '{"account":{"homePage":"https://learn.example.org/","name":"ada","x":{"y":{"z":1}}}}']];
    }

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('foreignRequests')]
    public function testAStateOfAnotherActivityRegistrationOrAgentIsRefused(array $query): void
    {
        $this->send('PUT', '{"a":1}');
        $response = $this->send('GET', '', $query);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('{"error":"The state belongs to the activity, agent and registration of the session."}', $response->getContent());
    }

    public function testAnAgentNestedWithinTheBoundIsRead(): void
    {
        $this->send('PUT', '{"a":1}');

        self::assertSame(200, $this->send('GET', '', ['agent' => '{"account":{"homePage":"https://learn.example.org/","name":"ada","x":{"y":1}}}'])->getStatusCode());
    }

    public function testAStateIsNamedByOneToOneHundredNinetyBytes(): void
    {
        $refused = '{"error":"A state is named by a \u0022stateId\u0022 of 1 to 190 bytes."}';

        foreach (['', str_repeat('s', 191)] as $stateId) {
            $response = $this->send('PUT', '{"a":1}', ['stateId' => $stateId]);
            self::assertSame(400, $response->getStatusCode());
            self::assertSame($refused, $response->getContent());
        }

        self::assertSame(204, $this->send('PUT', '{"a":1}', ['stateId' => str_repeat('s', 190)])->getStatusCode());
        self::assertSame(204, $this->send('PUT', '{"a":1}', ['stateId' => 's'])->getStatusCode());
        self::assertSame('{"a":1}', $this->store->lrs->state(self::IRI, $this->enrollment->registration, str_repeat('s', 190)));
    }

    public function testAStateIsServedAsJson(): void
    {
        $this->send('PUT', '{"a":1}');
        $response = $this->send('GET', '');

        self::assertSame('{"a":1}', $response->getContent());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
    }

    public function testAPutDocumentIsBoundedAtTheSizeOfAState(): void
    {
        $exact = str_repeat('a', Cmi5Controller::STATE_BYTES);

        self::assertSame(204, $this->send('PUT', $exact)->getStatusCode());
        self::assertSame($exact, $this->kept());

        $response = $this->send('PUT', $exact.'a');

        self::assertSame(413, $response->getStatusCode());
        self::assertSame('{"error":"A state document is 65536 bytes at most."}', $response->getContent());
        self::assertSame($exact, $this->kept());
    }

    public function testTheStateOfACmi5SessionIsServedByTheCmi5Rules(): void
    {
        $unit = $this->store->courses->addActivity($this->module->id, 'Charts', 'cmi5', ['au_id' => 'https://example.org/aus/charts', 'path' => 'charts/start.html'], CompletionRequirement::Completed);
        $this->store->learning->launch($this->enrollment, $unit->id);
        $session = $this->store->sessionStore->open($this->enrollment->id, $unit->id)[0];
        $this->store->tokens->fetch($session->id->value);
        $token = 'Basic '.$this->store->tokens->token($session->id);
        $iri = 'https://learn.example.org/courses/activities/'.$unit->id->value;

        foreach ([null, $this->store->xapi] as $xapi) {
            $controller = new Cmi5Controller($this->store->tokens, $this->store->statements, $this->store->lrs, [], $xapi);
            $query = http_build_query(['activityId' => $iri, 'agent' => '{"account":{"homePage":"https://learn.example.org/","name":"ada"}}', 'registration' => $this->enrollment->registration, 'stateId' => 'suspend']);
            $server = ['HTTP_AUTHORIZATION' => $token, 'HTTP_X_EXPERIENCE_API_VERSION' => '1.0.3'];

            self::assertSame(204, $controller->state(Request::create('/s?'.$query, 'PUT', server: $server, content: '{"a":1}'))->getStatusCode());
            self::assertSame('{"a":1}', $this->store->lrs->state($iri, $this->enrollment->registration, 'suspend'));
            self::assertSame(200, $controller->state(Request::create('/s?'.$query, 'GET', server: $server))->getStatusCode());
        }
    }

    public function testTheHeadersOfTheAnswersNameTheVersionAndTheOriginsAllowed(): void
    {
        $controller = new Cmi5Controller($this->store->tokens, $this->store->statements, $this->store->lrs, ['https://content.example.net']);

        $plain = $controller->about(Request::create('/about'));
        self::assertSame('{"version":["1.0.3"]}', $plain->getContent());
        self::assertSame('1.0.3', $plain->headers->get('X-Experience-API-Version'));
        self::assertStringContainsString('no-store', (string) $plain->headers->get('Cache-Control'));
        self::assertSame(['Origin'], $plain->getVary());
        self::assertFalse($plain->headers->has('Access-Control-Allow-Origin'));

        $foreign = $controller->about(Request::create('/about', server: ['HTTP_ORIGIN' => 'https://evil.example.net']));
        self::assertFalse($foreign->headers->has('Access-Control-Allow-Origin'));
        self::assertFalse($foreign->headers->has('Access-Control-Allow-Methods'));

        $allowed = $controller->about(Request::create('/about', server: ['HTTP_ORIGIN' => 'https://content.example.net']));
        self::assertSame('https://content.example.net', $allowed->headers->get('Access-Control-Allow-Origin'));
        self::assertSame('Authorization, Content-Type, X-Experience-API-Version', $allowed->headers->get('Access-Control-Allow-Headers'));
        self::assertSame('GET, PUT, POST, DELETE, OPTIONS', $allowed->headers->get('Access-Control-Allow-Methods'));
        self::assertSame('X-Experience-API-Version', $allowed->headers->get('Access-Control-Expose-Headers'));

        $open = new Cmi5Controller($this->store->tokens, $this->store->statements, $this->store->lrs);
        self::assertFalse($open->about(Request::create('/about', server: ['HTTP_ORIGIN' => 'https://content.example.net']))->headers->has('Access-Control-Allow-Origin'));
    }

    /**
     * @param array<string, string> $query
     */
    private function send(string $method, string $body, array $query = []): Response
    {
        $request = Request::create('/cmi5/xapi/activities/state?'.http_build_query($query + [
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
