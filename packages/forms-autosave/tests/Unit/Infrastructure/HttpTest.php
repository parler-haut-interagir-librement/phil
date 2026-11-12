<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Tests\Unit\Infrastructure;

use Phil\FormsAutosave\Application\DraftKeys;
use Phil\FormsAutosave\Application\Drafts;
use Phil\FormsAutosave\Application\PlainSealer;
use Phil\FormsAutosave\Domain\ValueObject\DraftContent;
use Phil\FormsAutosave\Domain\ValueObject\DraftKey;
use Phil\FormsAutosave\Infrastructure\Http\DraftController;
use Phil\FormsAutosave\Infrastructure\Http\DraftRequest;
use Phil\FormsAutosave\Infrastructure\Http\Routes;
use Phil\FormsAutosave\Infrastructure\Memory\InMemoryDraftStore;
use Phil\FormsAutosave\Tests\Unit\Fixture\FixedOwner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\SameOriginCsrfTokenManager;

#[CoversClass(DraftController::class)]
#[CoversClass(DraftRequest::class)]
#[CoversClass(Routes::class)]
final class HttpTest extends TestCase
{
    private const string TOKEN = 'a-token-of-24-characters';

    private const string KEY = 'abcdefghijklmnop';

    private RequestStack $requests;

    private MockClock $clock;

    private Drafts $drafts;

    private FixedOwner $owner;

    private DraftController $controller;

    protected function setUp(): void
    {
        $this->requests = new RequestStack();
        $this->clock = new MockClock('2026-11-08 15:00:00 UTC');
        $this->drafts = new Drafts(new InMemoryDraftStore(), new PlainSealer(), new DraftKeys('secret'), $this->clock, 60, 200);
        $this->owner = new FixedOwner();
        $guard = new DraftRequest(new SameOriginCsrfTokenManager($this->requests, null, null, [DraftRequest::TOKEN_ID]));
        $this->controller = new DraftController($this->drafts, $guard, $this->owner, 200);
    }

    public function testADraftThroughTheEndpoints(): void
    {
        self::assertSame([204, ''], $this->call('GET'));
        self::assertSame([200, ['revision' => 1]], $this->call('PUT', ['revision' => null, 'version' => 'v1', 'fields' => ['title' => ['Hello']]]));
        $this->clock->sleep(5);
        self::assertSame([200, ['revision' => 2]], $this->call('PUT', ['revision' => 1, 'version' => 'v1', 'fields' => ['title' => ['Hello!'], '0' => []]]));
        self::assertSame([200, ['revision' => 2, 'savedAt' => '2026-11-08T15:00:05+00:00', 'version' => 'v1', 'fields' => ['title' => ['Hello!'], '0' => []]]], $this->call('GET'));
        self::assertSame([409, ['error' => 'The draft is at revision 2.', 'revision' => 2]], $this->call('PUT', ['revision' => 1, 'version' => 'v1', 'fields' => []]));
        self::assertSame([409, ['error' => 'The draft is at revision 2.', 'revision' => 2]], $this->call('PUT', ['version' => 'v1', 'fields' => []]));
        self::assertSame([204, ''], $this->call('DELETE'));
        self::assertSame([204, ''], $this->call('GET'));
    }

    public function testAnEmptyDraftIsAnsweredAsAnObject(): void
    {
        $this->drafts->save('ada', DraftKey::of(self::KEY), null, DraftContent::of('v', []));
        $response = $this->response('GET');

        self::assertStringContainsString('"fields":{}', (string) $response->getContent());
    }

    public function testEveryAnswerIsPrivateAndNeverCached(): void
    {
        foreach (['GET', 'PUT', 'DELETE'] as $method) {
            self::assertSame('no-store, private', $this->response($method, ['version' => 'v', 'fields' => []])->headers->get('Cache-Control'), $method);
        }

        self::assertSame('no-store, private', $this->response('PUT', '{')->headers->get('Cache-Control'));
        self::assertSame('no-store, private', $this->response('GET', trusted: false)->headers->get('Cache-Control'));
    }

    public function testABodyThatIsNoDraftIsRefused(): void
    {
        foreach ([
            ['{', 'The body is a JSON object.'],
            ['"text"', 'The body is a JSON object.'],
            ['{"version":"v","fields":{"a":[["b"]]}}', 'The body is a JSON object.'],
            [['revision' => '1', 'version' => 'v', 'fields' => []], 'The revision read is a number or null.'],
            ['{"revision":1.5,"version":"v","fields":{}}', 'The revision read is a number or null.'],
            [['fields' => []], 'The version of a draft is 1 to 64 letters, digits, "-" or "_".'],
            [['version' => 'v'], 'The fields of a draft are an object of at most 1000 names.'],
        ] as [$body, $message]) {
            self::assertSame([400, ['error' => $message]], $this->call('PUT', $body), $message);
        }

        self::assertSame([400, ['error' => 'A draft key is 16 to 64 letters, digits, "-" or "_".']], $this->call('GET', key: 'short'));
    }

    public function testADraftTooLargeIsRefused(): void
    {
        $fits = ['version' => 'v', 'fields' => ['a' => [str_repeat('x', 200 - 35)]]];
        self::assertSame(200, \strlen(DraftContent::of('v', $fits['fields'])->toJson()));
        self::assertSame([200, ['revision' => 1]], $this->call('PUT', $fits));

        self::assertSame([413, ['error' => 'A draft is at most 200 bytes.']], $this->call('PUT', ['revision' => 1, 'version' => 'v', 'fields' => ['a' => [str_repeat('x', 166)]]]));
        self::assertSame([413, ['error' => 'A draft is at most 200 bytes.']], $this->call('PUT', '{"version":"v","fields":{}}'.str_repeat(' ', 400 - 27 + 1)), 'a body larger than twice the bound is not even read');
        self::assertSame([200, ['revision' => 2]], $this->call('PUT', '{"revision":1,"version":"v","fields":{}}'.str_repeat(' ', 400 - 40)));
    }

    public function testOnlyTheControllerOfASignedInUserIsAnswered(): void
    {
        $refused = [403, ['error' => 'This request is not allowed.']];

        self::assertSame($refused, $this->call('GET', trusted: false));
        self::assertSame($refused, $this->call('GET', site: 'cross-site'));
        self::assertSame([204, ''], $this->call('GET', site: 'same-origin', token: false), 'reading carries no token');
        self::assertSame($refused, $this->call('PUT', ['version' => 'v', 'fields' => []], token: false));
        self::assertSame($refused, $this->call('DELETE', token: null));
        self::assertSame($refused, $this->call('PUT', ['version' => 'v', 'fields' => []], token: 'another-token-of-24-chars'));
        self::assertSame([204, ''], $this->call('DELETE', site: 'same-origin'));

        $this->owner->user = null;
        self::assertSame($refused, $this->call('GET'));
    }

    public function testEachUserHasTheirOwnDrafts(): void
    {
        $this->call('PUT', ['version' => 'v', 'fields' => ['a' => ['ada']]]);
        $this->owner->user = 'bob';

        self::assertSame([204, ''], $this->call('GET'));
        self::assertSame([200, ['revision' => 1]], $this->call('PUT', ['version' => 'v', 'fields' => ['a' => ['bob']]]));
    }

    public function testTheGuardNamesTheCookieOfTheToken(): void
    {
        $guard = new DraftRequest(new SameOriginCsrfTokenManager($this->requests, null, null, [DraftRequest::TOKEN_ID]));

        self::assertSame('csrf-token', $guard->cookieName());
        self::assertSame('phil_forms_autosave', DraftRequest::TOKEN_ID);
        self::assertSame('X-Phil-Forms-Autosave', DraftRequest::HEADER);
        self::assertSame(['phil_forms_autosave_draft', 'phil_forms_autosave_draft_save', 'phil_forms_autosave_draft_discard'], [Routes::DRAFT, Routes::SAVE, Routes::DISCARD]);
    }

    /**
     * @param array<string, mixed>|string $body
     *
     * @return array{int, mixed}
     */
    private function call(string $method, array|string $body = [], bool $trusted = true, ?string $site = null, string|false|null $token = self::TOKEN, string $key = self::KEY): array
    {
        $response = $this->response($method, $body, $trusted, $site, $token, $key);
        $content = (string) $response->getContent();

        return Response::HTTP_NO_CONTENT === $response->getStatusCode() ? [204, $content] : [$response->getStatusCode(), json_decode($content, true, 512, \JSON_THROW_ON_ERROR)];
    }

    /**
     * @param array<string, mixed>|string $body
     */
    private function response(string $method, array|string $body = [], bool $trusted = true, ?string $site = null, string|false|null $token = self::TOKEN, string $key = self::KEY): Response
    {
        $cookies = \is_string($token) ? ['csrf-token_'.$token => 'csrf-token'] : [];
        $request = new Request([], [], [], $cookies, [], ['REQUEST_METHOD' => $method], \is_string($body) ? $body : json_encode($body, \JSON_THROW_ON_ERROR));
        $this->requests->push($request);

        if ($trusted) {
            $request->headers->set(DraftRequest::HEADER, '1');
        }

        if (false !== $token) {
            $request->headers->set('csrf-token', self::TOKEN);
        }

        if (null !== $site) {
            $request->headers->set('Sec-Fetch-Site', $site);
        }

        return match ($method) {
            'GET' => $this->controller->read($request, $key),
            'PUT' => $this->controller->save($request, $key),
            default => $this->controller->discard($request, $key),
        };
    }
}
