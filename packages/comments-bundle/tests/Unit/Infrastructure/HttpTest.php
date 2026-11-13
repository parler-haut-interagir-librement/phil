<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Unit\Infrastructure;

use LogicException;
use Phil\CommentsBundle\Application\Comments;
use Phil\CommentsBundle\Infrastructure\Http\ClientConfig;
use Phil\CommentsBundle\Infrastructure\Http\CommentsController;
use Phil\CommentsBundle\Infrastructure\Http\CommentsRequest;
use Phil\CommentsBundle\Infrastructure\Http\DiscussionJson;
use Phil\CommentsBundle\Infrastructure\Http\Routes;
use Phil\CommentsBundle\Infrastructure\Memory\InMemoryCommentRepository;
use Phil\CommentsBundle\Infrastructure\Memory\InMemoryThreadRepository;
use Phil\CommentsBundle\Tests\Unit\Fixture\FixedAuthor;
use Phil\CommentsBundle\Tests\Unit\Fixture\Json;
use Phil\CommentsBundle\Tests\Unit\Fixture\Names;
use Phil\CommentsBundle\Tests\Unit\Fixture\Policy;
use Phil\CommentsBundle\Tests\Unit\Fixture\Router;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Csrf\SameOriginCsrfTokenManager;

#[CoversClass(CommentsController::class)]
#[CoversClass(CommentsRequest::class)]
#[CoversClass(DiscussionJson::class)]
#[CoversClass(ClientConfig::class)]
#[CoversClass(Routes::class)]
final class HttpTest extends TestCase
{
    private const string TOKEN = 'a-token-of-24-characters';

    private FixedAuthor $author;

    private MockClock $clock;

    private int $next = 0;

    private RequestStack $requests;

    private CommentsRequest $guard;

    protected function setUp(): void
    {
        $this->author = new FixedAuthor(ResourceReference::of('user', 'bob'));
        $this->clock = new MockClock('2026-11-06 10:00:00 UTC');
        $this->requests = new RequestStack();
        $this->guard = new CommentsRequest(new SameOriginCsrfTokenManager($this->requests, null, null, [CommentsRequest::TOKEN_ID]));
    }

    public function testAThreadThroughTheEndpoints(): void
    {
        $controller = $this->controller();
        $anchor = ['block' => 'b1', 'field' => 'text', 'start' => 4, 'quote' => 'cat', 'prefix' => 'the ', 'suffix' => ' sat'];

        [$status, $opened] = $this->call($controller->open($this->request(['subject' => 'page:1', 'anchor' => $anchor, 'body' => 'Dog?', 'suggestion' => 'dog'])));

        self::assertSame(Response::HTTP_CREATED, $status);
        self::assertSame(['thread' => [
            'id' => 'id1',
            'subject' => 'page:1',
            'anchor' => ['block' => 'b1', 'field' => 'text', 'start' => 4, 'end' => 7, 'quote' => 'cat', 'prefix' => 'the ', 'suffix' => ' sat'],
            'status' => 'open',
            'detached' => false,
            'suggestion' => ['text' => 'dog', 'state' => 'pending'],
            'author' => ['ref' => 'user:bob', 'name' => 'Bob'],
            'createdAt' => '2026-11-06T10:00:00+00:00',
            'resolvedBy' => null,
            'resolvedAt' => null,
            'may' => ['resolve' => false, 'decide' => false, 'reply' => true],
            'comments' => [['id' => 'id2', 'author' => ['ref' => 'user:bob', 'name' => 'Bob'], 'body' => 'Dog?', 'createdAt' => '2026-11-06T10:00:00+00:00', 'editedAt' => null, 'mine' => true, 'may' => true]],
        ]], $opened);

        $this->author->author = ResourceReference::of('user', 'cy');
        $this->clock->sleep(60);
        [$status, $replied] = $this->call($controller->reply($this->request(['body' => 'No.']), 'id1'));
        self::assertSame([Response::HTTP_CREATED, ['ref' => 'user:cy', 'name' => 'user:cy'], false, true, false], [$status, Json::at($replied, 'thread.comments.1.author'), Json::at($replied, 'thread.comments.0.may'), Json::at($replied, 'thread.comments.1.may'), Json::at($replied, 'thread.comments.0.mine')]);

        [, $edited] = $this->call($controller->edit($this->request(['body' => 'Maybe.']), 'id3'));
        self::assertSame(['Maybe.', '2026-11-06T10:01:00+00:00'], [Json::at($edited, 'thread.comments.1.body'), Json::at($edited, 'thread.comments.1.editedAt')]);

        $this->author->author = ResourceReference::of('user', 'ada');
        [, $list] = $this->call($controller->threads($this->read(['subject' => 'page:1'])));
        self::assertSame(['id1'], Json::column($list, 'threads', 'id'));
        self::assertSame(['resolve' => true, 'decide' => true, 'reply' => true], Json::at($list, 'threads.0.may'));
        self::assertTrue(Json::at($list, 'threads.0.comments.0.may'));

        [$status, $resolved] = $this->call($controller->resolve($this->request(), 'id1'));
        self::assertSame([200, 'resolved', ['ref' => 'user:ada', 'name' => 'Ada'], '2026-11-06T10:01:00+00:00'], [$status, Json::at($resolved, 'thread.status'), Json::at($resolved, 'thread.resolvedBy'), Json::at($resolved, 'thread.resolvedAt')]);
        self::assertSame('open', Json::at($this->call($controller->reopen($this->request(), 'id1'))[1], 'thread.status'));
        self::assertSame('rejected', Json::at($this->call($controller->reject($this->request(), 'id1'))[1], 'thread.suggestion.state'));

        $this->call($controller->open($this->request(['subject' => 'page:1', 'anchor' => ['start' => 0, 'quote' => 'x'], 'body' => 'Plain'])));
        [, $plain] = $this->call($controller->accept($this->request(), 'id4'));
        self::assertSame([400, 'invalid', 'The thread has no suggestion waiting for a decision.'], [$this->call($controller->accept($this->request(), 'id4'))[0], Json::at($plain, 'error'), Json::at($plain, 'message')]);
        self::assertSame([null, null, '', ''], [Json::at($this->thread($controller, 'id4'), 'anchor.block'), Json::at($this->thread($controller, 'id4'), 'suggestion'), Json::at($this->thread($controller, 'id4'), 'anchor.prefix'), Json::at($this->thread($controller, 'id4'), 'anchor.suffix')]);

        $this->call($controller->open($this->request(['subject' => 'page:1', 'anchor' => ['start' => 0, 'quote' => 'y', 'block' => null, 'field' => null], 'body' => 'Take', 'suggestion' => 'z'])));
        self::assertSame([200, 'accepted'], [$this->call($controller->accept($this->request(), 'id6'))[0], Json::at($this->thread($controller, 'id6'), 'suggestion.state')]);

        [$status, $deleted] = $this->call($controller->delete($this->request(), 'id2'));
        self::assertSame([200, ['id3']], [$status, Json::column($deleted, 'thread.comments', 'id')]);
        self::assertSame([200, ['thread' => null]], $this->call($controller->delete($this->request(), 'id3')));
    }

    public function testACommentOnTheWholePage(): void
    {
        $controller = $this->controller();

        [$status, $opened] = $this->call($controller->open($this->request(['subject' => 'page:1', 'anchor' => null, 'body' => 'Nice page'])));
        self::assertSame([Response::HTTP_CREATED, 'id1', null, false, null, ['Nice page']], [$status, Json::at($opened, 'thread.id'), Json::at($opened, 'thread.anchor'), Json::at($opened, 'thread.detached'), Json::at($opened, 'thread.suggestion'), Json::column($opened, 'thread.comments', 'body')]);

        [$status, $without] = $this->call($controller->open($this->request(['subject' => 'page:1', 'body' => 'No anchor at all'])));
        self::assertSame([Response::HTTP_CREATED, null], [$status, Json::at($without, 'thread.anchor')]);

        [$status, $replied] = $this->call($controller->reply($this->request(['body' => 'Thanks']), 'id1'));
        self::assertSame([Response::HTTP_CREATED, ['Nice page', 'Thanks']], [$status, Json::column($replied, 'thread.comments', 'body')]);
        self::assertSame([null, null], Json::column($this->call($controller->threads($this->read(['subject' => 'page:1'])))[1], 'threads', 'anchor'));
    }

    public function testRequestsTheEndpointsRefuse(): void
    {
        $controller = $this->controller();

        self::assertSame([404, ['error' => 'disabled']], $this->call($this->controller(enabled: false)->threads($this->read())));
        self::assertSame([404, ['error' => 'disabled']], $this->call(new CommentsController($this->comments(), new DiscussionJson(new Policy()), $this->guard, $this->author)->threads($this->read())));
        self::assertSame([404, ['error' => 'disabled']], $this->call(new CommentsController($this->comments(), new DiscussionJson(new Policy()), $this->guard, null, true)->threads($this->read())));
        self::assertSame([403, ['error' => 'forbidden']], $this->call($controller->threads($this->current(Request::create('/')))));
        self::assertSame([403, ['error' => 'forbidden']], $this->call($controller->resolve($this->request([], [], ['HTTP_CSRF_TOKEN' => '']), 't1')), 'no token');
        self::assertSame([403, ['error' => 'forbidden']], $this->call($controller->resolve($this->request([], [], [], []), 't1')), 'a token without its cookie');
        self::assertSame([403, ['error' => 'forbidden']], $this->call($controller->resolve($this->request([], [], ['HTTP_ORIGIN' => 'https://elsewhere.example']), 't1')), 'another origin');
        self::assertSame([403, ['error' => 'forbidden']], $this->call($controller->threads($this->read(['subject' => 'page:1'], ['HTTP_X_PHIL_COMMENTS' => '0']))), 'without the header');
        self::assertSame([404, ['error' => 'missing']], $this->call($controller->resolve($this->request([], [], ['HTTP_ORIGIN' => 'http://localhost'], []), 't1')), 'a token and the origin of this site');
        self::assertSame(200, $this->call($controller->threads($this->read(['subject' => 'page:1'])))[0], 'a read carries no token');
        self::assertSame(200, $this->call($controller->threads($this->current(Request::create('/?subject=page:1', server: ['HTTP_X_PHIL_COMMENTS' => '1', 'HTTP_SEC_FETCH_SITE' => 'same-origin']))))[0], 'a read without any cookie');
        self::assertSame([403, ['error' => 'forbidden']], $this->call($controller->threads($this->read([], ['HTTP_SEC_FETCH_SITE' => 'cross-site']))));
        self::assertSame(200, $this->call($controller->threads($this->read(['subject' => 'page:1'], ['HTTP_SEC_FETCH_SITE' => 'same-origin'])))[0]);

        $this->author->author = null;
        self::assertSame([401, ['error' => 'anonymous']], $this->call($controller->threads($this->read())));
        $this->author->author = ResourceReference::of('user', 'nobody');
        self::assertSame([403, ['error' => 'refused']], $this->call($controller->threads($this->read(['subject' => 'page:1']))));
        $this->author->author = ResourceReference::of('user', 'bob');

        self::assertSame([404, ['error' => 'missing']], $this->call($controller->reply($this->request(['body' => 'x']), 't9')));
        self::assertSame([404, ['error' => 'missing']], $this->call($controller->delete($this->request(), 'c9')));

        foreach ([
            ['"nope" is not a resource reference', $controller->threads($this->read(['subject' => 'nope']))],
            ['Could not decode request body.', $controller->open($this->current(new Request(cookies: ['csrf-token_'.self::TOKEN => 'csrf-token'], server: ['HTTP_X_PHIL_COMMENTS' => '1', 'HTTP_CSRF_TOKEN' => self::TOKEN], content: '{')))],
            ['A thread is opened on a subject, with a suggestion or none, as texts.', $controller->open($this->request(['subject' => 1]))],
            ['A thread is opened on a subject, with a suggestion or none, as texts.', $controller->open($this->request(['subject' => 'page:1', 'suggestion' => 1]))],
            ['An anchor is {"block", "field", "start", "quote", "prefix", "suffix"}: texts or null, and a whole number.', $controller->open($this->request(['subject' => 'page:1', 'body' => 'x', 'suggestion' => 'y']))],
            ['An anchor is {"block", "field", "start", "quote", "prefix", "suffix"}: texts or null, and a whole number.', $controller->open($this->request(['subject' => 'page:1', 'anchor' => 'b1', 'body' => 'x']))],
            ['A comment is sent as the text "body".', $controller->open($this->request(['subject' => 'page:1', 'anchor' => null]))],
            ['A comment is sent as the text "body".', $controller->open($this->request(['subject' => 'page:1', 'anchor' => ['start' => 0, 'quote' => 'x']]))],
            ['A comment is sent as the text "body".', $controller->reply($this->request(['body' => 5]), 't1')],
            ['A passage is 1 to 2000 characters.', $controller->open($this->request(['subject' => 'page:1', 'anchor' => ['start' => 0, 'quote' => ''], 'body' => 'x']))],
        ] as [$message, $response]) {
            [$status, $body] = $this->call($response);
            self::assertSame([400, 'invalid'], [$status, Json::at($body, 'error')], $message);
            self::assertStringStartsWith($message, Json::text($body, 'message') ?? '');
        }

        foreach ([['block' => 1], ['field' => 1], ['start' => '0'], ['quote' => 1], ['prefix' => 1], ['suffix' => 1]] as $bad) {
            $body = $this->call($controller->open($this->request(['subject' => 'page:1', 'anchor' => [...['start' => 0, 'quote' => 'x'], ...$bad], 'body' => 'x'])))[1];
            self::assertStringStartsWith('An anchor is', Json::text($body, 'message') ?? '', (string) json_encode($bad));
        }

        self::assertSame([], $this->comments()->discussions(ResourceReference::of('page', '1'), ResourceReference::of('user', 'bob')));
    }

    public function testTheControllersConfiguration(): void
    {
        self::assertSame(['subject' => 'page:1', 'viewer' => 'user:bob', 'urls' => null, 'csrfCookie' => null, 'mentions' => null, 'page' => true, 'ariaRoles' => false], new ClientConfig()->for(ResourceReference::of('page', '1'), ResourceReference::of('user', 'bob')));
        self::assertTrue(new ClientConfig(ariaRoles: true)->for(ResourceReference::of('page', '1'), ResourceReference::of('user', 'bob'))['ariaRoles']);
        self::assertSame([false, true], [new ClientConfig()->hasAriaRoles(), new ClientConfig(ariaRoles: true)->hasAriaRoles()]);
        self::assertFalse(new ClientConfig()->for(ResourceReference::of('page', '1'), ResourceReference::of('user', 'bob'), false)['page']);
        self::assertSame('csrf-token', new ClientConfig(null, $this->guard)->for(ResourceReference::of('page', '1'), ResourceReference::of('user', 'bob'))['csrfCookie']);
        self::assertSame(['X-Phil-Comments', 'phil_comments'], [CommentsRequest::HEADER, CommentsRequest::TOKEN_ID]);
        self::assertSame([
            'threads' => '/_c/phil_comments_threads',
            'open' => '/_c/phil_comments_open',
            'reply' => '/_c/phil_comments_reply?thread=__id__',
            'resolve' => '/_c/phil_comments_resolve?thread=__id__',
            'reopen' => '/_c/phil_comments_reopen?thread=__id__',
            'accept' => '/_c/phil_comments_accept?thread=__id__',
            'reject' => '/_c/phil_comments_reject?thread=__id__',
            'edit' => '/_c/phil_comments_edit?comment=__id__',
            'delete' => '/_c/phil_comments_delete?comment=__id__',
        ], new ClientConfig(new Router())->for(ResourceReference::of('page', '1'), ResourceReference::of('user', 'bob'))['urls']);
    }

    public function testTheMemberSearchIsGivenWhenItsRouteExists(): void
    {
        $page = ResourceReference::of('page', '1');
        $viewer = ResourceReference::of('user', 'bob');

        self::assertNull(new ClientConfig(new Router())->for($page, $viewer)['mentions']);
        self::assertNull(new ClientConfig(null, null, 'members')->for($page, $viewer)['mentions']);
        self::assertSame('/_c/members', new ClientConfig(new Router(), null, 'members')->for($page, $viewer)['mentions']);
        self::assertSame('/_c/members', new ClientConfig(new Router(), null, 'members')->mentionSearch());
        self::assertNull(new ClientConfig(new class implements UrlGeneratorInterface {
            public function setContext(RequestContext $context): void
            {
            }

            public function getContext(): RequestContext
            {
                return new RequestContext();
            }

            /**
             * @param array<mixed> $parameters
             */
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return 'members' === $name ? throw new RouteNotFoundException($name) : '/_c/'.$name;
            }
        }, null, 'members')->for($page, $viewer)['mentions']);
    }

    private ?Comments $comments = null;

    private function comments(): Comments
    {
        return $this->comments ??= new Comments(new InMemoryThreadRepository(), new InMemoryCommentRepository(), $this->clock, new Policy(readers: ['user:ada', 'user:bob', 'user:cy'], writers: ['user:ada', 'user:bob', 'user:cy']), fn (): string => 'id'.++$this->next);
    }

    private function controller(bool $enabled = true): CommentsController
    {
        return new CommentsController($this->comments(), new DiscussionJson(new Policy(), new Names()), $this->guard, $this->author, $enabled);
    }

    private function thread(CommentsController $controller, string $id): mixed
    {
        foreach (Json::column($this->call($controller->threads($this->read(['subject' => 'page:1'])))[1], 'threads', 'id') as $index => $found) {
            if ($found === $id) {
                return Json::at($this->call($controller->threads($this->read(['subject' => 'page:1'])))[1], 'threads.'.$index);
            }
        }

        throw new LogicException($id);
    }

    /**
     * A request of the controller, with its header and a double-submitted token unless told otherwise, made the
     * current one, where the token manager reads it.
     *
     * @param ?array<string, mixed> $body
     * @param array<string, string> $query
     * @param array<string, string> $server
     * @param array<string, string> $cookies
     */
    private function request(?array $body = null, array $query = [], array $server = [], array $cookies = ['csrf-token_'.self::TOKEN => 'csrf-token']): Request
    {
        return $this->current(new Request($query, cookies: $cookies, server: ['REQUEST_METHOD' => 'POST', 'HTTP_HOST' => 'localhost', 'HTTP_X_PHIL_COMMENTS' => '1', 'HTTP_CSRF_TOKEN' => self::TOKEN, ...$server], content: null === $body ? '' : (string) json_encode($body)));
    }

    /**
     * A `GET` of the controller, with its header and no token.
     *
     * @param array<string, string> $query
     * @param array<string, string> $server
     */
    private function read(array $query = [], array $server = []): Request
    {
        return $this->current(new Request($query, server: ['HTTP_HOST' => 'localhost', 'HTTP_X_PHIL_COMMENTS' => '1', ...$server]));
    }

    private function current(Request $request): Request
    {
        $this->requests->push($request);

        return $request;
    }

    /**
     * @return array{int, mixed}
     */
    private function call(Response $response): array
    {
        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true)];
    }
}
