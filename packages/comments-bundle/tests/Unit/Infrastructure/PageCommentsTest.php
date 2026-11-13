<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Unit\Infrastructure;

use Phil\CommentsBundle\Application\Comments;
use Phil\CommentsBundle\Domain\Model\Comment;
use Phil\CommentsBundle\Domain\ValueObject\Anchor;
use Phil\CommentsBundle\Infrastructure\Http\ClientConfig;
use Phil\CommentsBundle\Infrastructure\Http\DiscussionJson;
use Phil\CommentsBundle\Infrastructure\Http\PageController;
use Phil\CommentsBundle\Infrastructure\Http\PageForm;
use Phil\CommentsBundle\Infrastructure\Memory\InMemoryCommentRepository;
use Phil\CommentsBundle\Infrastructure\Memory\InMemoryThreadRepository;
use Phil\CommentsBundle\Infrastructure\Twig\Components\PageCommentsComponent;
use Phil\CommentsBundle\Infrastructure\Twig\KitComponents;
use Phil\CommentsBundle\Tests\Unit\Fixture\FixedAuthor;
use Phil\CommentsBundle\Tests\Unit\Fixture\Policy;
use Phil\CommentsBundle\Tests\Unit\Fixture\Router;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Csrf\SameOriginCsrfTokenManager;
use Symfony\Component\Translation\IdentityTranslator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(PageCommentsComponent::class)]
#[CoversClass(PageController::class)]
#[CoversClass(PageForm::class)]
#[CoversClass(DiscussionJson::class)]
final class PageCommentsTest extends TestCase
{
    private RequestStack $requests;

    private Comments $comments;

    private PageForm $form;

    protected function setUp(): void
    {
        $this->requests = new RequestStack();
        $this->comments = new Comments(new InMemoryThreadRepository(), new InMemoryCommentRepository(), new MockClock('2026-11-06 10:00:00 UTC'), new Policy(readers: ['user:ada', 'user:bob'], writers: ['user:ada']));
        $this->form = new PageForm(new SameOriginCsrfTokenManager($this->requests, null, null, ['phil_comments']), new UriSigner('secret'), new class implements UrlGeneratorInterface {
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
                return (self::ABSOLUTE_URL === $referenceType ? 'http://localhost' : '').new Router()->generate($name, $parameters);
            }
        });
    }

    public function testWhatTheSectionShowsToEachViewer(): void
    {
        $page = ResourceReference::of('page', '1');
        $this->comments->open($page, Anchor::of(null, null, 0, 'cat'), ResourceReference::of('user', 'ada'), 'On a passage');
        $this->comments->openOnPage($page, ResourceReference::of('user', 'ada'), 'On the page');

        $this->requests->push(Request::create('/article?x=1'));

        $ada = $this->component('ada');
        $ada->mount($page);
        self::assertSame(['page:1', '/article?x=1'], [$ada->subject, $ada->back]);
        self::assertSame([true, true, true], [$ada->isSignedIn(), $ada->isReadable(), $ada->mayComment()]);
        self::assertSame(['On the page'], array_map(static fn (array $thread): mixed => \is_array($thread['comments'] ?? null) && \is_array($thread['comments'][0] ?? null) ? $thread['comments'][0]['body'] ?? null : null, $ada->getThreads()));
        self::assertSame([0], array_keys($ada->getThreads()));
        self::assertSame(['ref' => 'user:ada', 'name' => 'user:ada'], $ada->getThreads()[0]['author'] ?? null, 'without a directory, the reference');
        self::assertSame(['phil-comments-page', 'csrf-token', 'ui:Card'], [$ada->getSection(), $ada->getToken(), $ada->kit('Card')]);
        self::assertStringStartsWith('http://localhost/_c/phil_comments_page?', $ada->getFormAction());
        self::assertStringContainsString('back=%2Farticle%3Fx%3D1', $ada->getFormAction());
        self::assertStringContainsString('subject=page%3A1', $ada->getFormAction());
        self::assertStringContainsString('_hash=', $ada->getFormAction());
        self::assertSame([false, null], [$ada->hasAriaRoles(), $ada->getMentions()]);

        $bob = $this->component('bob', new ClientConfig(new Router(), null, 'members', true));
        $bob->mount('page:1');
        self::assertSame([true, true, false], [$bob->isSignedIn(), $bob->isReadable(), $bob->mayComment()]);
        self::assertSame([true, '/_c/members'], [$bob->hasAriaRoles(), $bob->getMentions()]);

        $cy = $this->component('cy');
        $cy->mount($page);
        self::assertSame([true, false, false, []], [$cy->isSignedIn(), $cy->isReadable(), $cy->mayComment(), $cy->getThreads()]);

        $policy = new Policy();
        $unconfigured = new PageCommentsComponent($this->comments, $policy, new DiscussionJson($policy), $this->form, new ClientConfig(), new KitComponents(), $this->requests);
        $unconfigured->mount($page);
        self::assertSame([false, [], 'Card'], [$unconfigured->isSignedIn(), $unconfigured->getThreads(), $unconfigured->kit('Card')]);

        $nobody = $this->component(null);
        $this->requests->pop();
        $nobody->mount($page);
        self::assertSame([false, false, false, [], '/'], [$nobody->isSignedIn(), $nobody->isReadable(), $nobody->mayComment(), $nobody->getThreads(), $nobody->back]);
    }

    public function testTheWayBackStaysOnTheSite(): void
    {
        $page = ResourceReference::of('page', '1');

        foreach (['//other.example/x' => '/', '/\\other.example/x' => '/', '/article' => '/article'] as $uri => $back) {
            $request = Request::create('/');
            $request->server->set('REQUEST_URI', $uri);
            $this->requests->push($request);
            $component = $this->component('ada');
            $component->mount($page);
            $this->requests->pop();
            self::assertSame($back, $component->back, $uri);
        }

        $request = Request::create('/');
        $request->server->set('REQUEST_URI', 'article');

        $this->requests->push($request);
        $component = $this->component('ada');
        $component->mount($page);
        self::assertSame('/', $component->back);
    }

    public function testTheControllerNeedsItsAuthorsAndItsPolicy(): void
    {
        foreach ([
            new PageController($this->comments, $this->form, $this->twig(), new IdentityTranslator(), new FixedAuthor(ResourceReference::of('user', 'ada'))),
            new PageController($this->comments, $this->form, $this->twig(), new IdentityTranslator(), new FixedAuthor(ResourceReference::of('user', 'ada')), false),
            new PageController($this->comments, $this->form, $this->twig(), new IdentityTranslator(), null, true),
        ] as $disabled) {
            $response = $disabled(Request::create('/', 'POST', ['body' => 'x']));
            self::assertSame([404, 'page.stopped.disabled|/|'], [$response->getStatusCode(), $response->getContent()]);
        }

        $controller = new PageController($this->comments, $this->form, $this->twig(), new IdentityTranslator(), new FixedAuthor(ResourceReference::of('user', 'ada')), true);
        $unsigned = $controller(Request::create('/_c/phil_comments_page?subject=page%3A1&back=%2Fa', 'POST', ['body' => 'x']));
        self::assertSame([403, 'page.stopped.forbidden|/|'], [$unsigned->getStatusCode(), $unsigned->getContent()]);

        $request = Request::create($this->form->action('page:1', '/a'), 'POST', ['_token' => 'csrf-token', 'do' => 'open', 'body' => 'Hi'], server: ['HTTP_SEC_FETCH_SITE' => 'same-origin']);
        $this->requests->push($request);
        $done = $controller($request);
        self::assertSame([303, '/a#phil-comments-page'], [$done->getStatusCode(), $done->headers->get('Location')]);
        self::assertCount(1, $this->comments->discussions(ResourceReference::of('page', '1'), ResourceReference::of('user', 'ada')));
    }

    public function testEachActionOfThePlainForms(): void
    {
        $page = ResourceReference::of('page', '1');
        $ada = ResourceReference::of('user', 'ada');
        $author = new FixedAuthor($ada);
        $controller = new PageController($this->comments, $this->form, $this->twig(), new IdentityTranslator(), $author, true);
        $thread = $this->comments->openOnPage($page, $ada, 'First');
        $id = $thread->thread->id;

        self::assertSame([303, ''], $this->send($controller, ['do' => 'reply', 'id' => $id, 'body' => 'Second']));
        $second = $this->comments->discussions($page, $ada)[0]->comments[1]->id;
        self::assertSame([303, ''], $this->send($controller, ['do' => 'edit', 'id' => $second, 'body' => 'Second, edited']));
        self::assertSame([303, ''], $this->send($controller, ['do' => 'resolve', 'id' => $id]));
        self::assertSame('resolved', $this->comments->thread($id)->status()->value);
        self::assertSame([303, ''], $this->send($controller, ['do' => 'reopen', 'id' => $id]));
        self::assertSame('open', $this->comments->thread($id)->status()->value);
        self::assertSame(['First', 'Second, edited'], array_map(static fn (Comment $comment): string => $comment->body(), $this->comments->discussions($page, $ada)[0]->comments));
        self::assertSame([303, ''], $this->send($controller, ['do' => 'delete', 'id' => $second]));
        self::assertCount(1, $this->comments->discussions($page, $ada)[0]->comments);

        self::assertSame([400, 'page.stopped.invalid|/a|x'], $this->send($controller, ['do' => 'shout', 'body' => 'x']));
        self::assertSame([400, 'page.stopped.invalid|/a|'], $this->send($controller, ['do' => 'open', 'body' => '']));
        self::assertSame([400, 'page.stopped.invalid|/a|x'], $this->send($controller, ['do' => 'open', 'body' => 'x'], 'not a reference'));
        self::assertSame([404, 'page.stopped.missing|/a|x'], $this->send($controller, ['do' => 'reply', 'id' => 'nope', 'body' => 'x']));
        self::assertSame([403, 'page.stopped.forbidden|/a|kept'], $this->send($controller, ['do' => 'open', 'body' => 'kept'], token: 'wrong'));
        self::assertSame([403, 'page.stopped.forbidden|/a|kept'], $this->send($controller, ['do' => 'open', 'body' => 'kept'], site: 'cross-site'));

        $author->author = ResourceReference::of('user', 'bob');
        self::assertSame([403, 'page.stopped.refused|/a|x'], $this->send($controller, ['do' => 'open', 'body' => 'x']));
        $author->author = null;
        self::assertSame([401, 'page.stopped.anonymous|/a|x'], $this->send($controller, ['do' => 'open', 'body' => 'x']));
    }

    /**
     * A plain form sent to the signed address of a page, from this site unless `$site` says otherwise.
     *
     * @param array<string, string> $fields
     *
     * @return array{int, string}
     */
    private function send(PageController $controller, array $fields, string $subject = 'page:1', string $token = 'csrf-token', string $site = 'same-origin'): array
    {
        $request = Request::create($this->form->action($subject, '/a'), 'POST', ['_token' => $token, ...$fields], server: ['HTTP_SEC_FETCH_SITE' => $site]);
        $this->requests->push($request);

        try {
            $response = $controller($request);
        } finally {
            $this->requests->pop();
        }

        $location = $response->headers->get('Location');
        self::assertSame(Response::HTTP_SEE_OTHER === $response->getStatusCode() ? '/a#phil-comments-page' : null, $location);

        return [$response->getStatusCode(), Response::HTTP_SEE_OTHER === $response->getStatusCode() ? '' : (string) $response->getContent()];
    }

    private function component(?string $viewer, ?ClientConfig $config = null): PageCommentsComponent
    {
        $policy = new Policy(readers: ['user:ada', 'user:bob'], writers: ['user:ada']);

        return new PageCommentsComponent($this->comments, $policy, new DiscussionJson($policy), $this->form, $config ?? new ClientConfig(), new KitComponents('ui'), $this->requests, new FixedAuthor(null === $viewer ? null : ResourceReference::of('user', $viewer)));
    }

    private function twig(): Environment
    {
        return new Environment(new ArrayLoader(['@PhilComments/stopped.html.twig' => '{{ message }}|{{ back }}|{{ body }}']));
    }
}
