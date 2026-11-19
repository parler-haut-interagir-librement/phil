<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\ReactionsBundle\Application\Exception\ReactionRefused;
use Phil\ReactionsBundle\Domain\Model\Reaction;
use Phil\ReactionsBundle\Infrastructure\Http\ReactionController;
use Phil\ReactionsBundle\Infrastructure\Http\ReactionGuard;
use Phil\ReactionsBundle\Tests\Fixtures\Stage;
use Phil\ReactionsBundle\Tests\Fixtures\Tokens;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Translation\IdentityTranslator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(ReactionController::class)]
#[CoversClass(ReactionGuard::class)]
final class HttpTest extends TestCase
{
    private Stage $stage;

    private Tokens $tokens;

    private ReactionGuard $guard;

    private ReactionController $controller;

    private ResourceReference $article;

    #[Override]
    protected function setUp(): void
    {
        $this->build(new Stage());
    }

    private function build(Stage $stage): void
    {
        $this->stage = $stage;
        $this->tokens = new Tokens();
        $this->article = ResourceReference::of('article', '1');
        $routes = new RouteCollection();
        $routes->add(ReactionGuard::ROUTE, new Route('/reactions/submit'));

        $this->guard = new ReactionGuard($this->tokens, new UriSigner(str_repeat('s', 32)), new UrlGenerator($routes, new RequestContext(host: 'site.test')));
        $twig = new Environment(new ArrayLoader(['@PhilReactions/stopped.html.twig' => '{{ back }}|{{ message }}']));
        $this->controller = new ReactionController($this->stage->reactions, $this->stage, $this->guard, $twig, new IdentityTranslator());
    }

    public function testTheToken(): void
    {
        self::assertSame('good', $this->guard->token());
        $this->guard->assertTrusted('good');
        self::assertSame(['phil_reactions'], $this->tokens->checked);
        self::assertSame('phil_reactions_submit', ReactionGuard::ROUTE);

        $this->expectExceptionObject(ReactionRefused::untrustedRequest());
        $this->guard->assertTrusted('bad');
    }

    public function testASignedAddressNamesTheContentAndThePage(): void
    {
        $action = $this->guard->action('article:1', '/page?a=1');

        self::assertStringStartsWith('http://site.test/reactions/submit?', $action);
        parse_str((string) parse_url($action, \PHP_URL_QUERY), $query);
        self::assertSame(['article:1', '/page?a=1'], [$query['content'], $query['back']]);
        self::assertTrue($this->guard->isSigned($this->post($action, [])));
        self::assertFalse($this->guard->isSigned($this->post(str_replace('article%3A1', 'article%3A2', $action), [])));
    }

    public function testAReactionComesBackToThePage(): void
    {
        $response = $this->submit(['_token' => 'good', 'react' => 'like']);

        self::assertSame([303, '/page?a=1'], [$response->getStatusCode(), $response->headers->get('Location')]);
        self::assertSame('like', ($this->stage->repository->of($this->article, 'ada')[0] ?? null)?->code());
    }

    public function testWithdrawing(): void
    {
        $this->stage->reactions->react($this->article, 'ada', 'like');

        $response = $this->submit(['_token' => 'good', 'withdraw' => 'like']);

        self::assertSame([303, '/page?a=1'], [$response->getStatusCode(), $response->headers->get('Location')]);
        self::assertSame([], $this->stage->repository->of($this->article, 'ada'));
    }

    public function testWithdrawingOneOfSeveralOrAll(): void
    {
        $this->build(new Stage(3));
        $this->stage->reactions->react($this->article, 'ada', 'like');
        $this->stage->reactions->react($this->article, 'ada', 'thanks');
        $this->stage->reactions->react($this->article, 'ada', 'spark');

        self::assertSame(303, $this->submit(['_token' => 'good', 'withdraw' => 'thanks'])->getStatusCode());
        self::assertSame(['like', 'spark'], array_map(static fn (Reaction $reaction): string => $reaction->code(), $this->stage->repository->of($this->article, 'ada')));

        self::assertSame(303, $this->submit(['_token' => 'good', 'withdraw' => ''])->getStatusCode());
        self::assertSame([], $this->stage->repository->of($this->article, 'ada'));
    }

    public function testTheCapOfTheSite(): void
    {
        $this->build(new Stage(2));
        $this->submit(['_token' => 'good', 'react' => 'like']);
        $this->submit(['_token' => 'good', 'react' => 'thanks']);

        self::assertSame([Response::HTTP_CONFLICT, '/page?a=1|reactions.limit'], $this->outcome(['_token' => 'good', 'react' => 'spark']));
        self::assertCount(2, $this->stage->repository->of($this->article, 'ada'));
    }

    public function testAnAddressNotSigned(): void
    {
        $response = $this->controller->__invoke($this->post('http://site.test/reactions/submit?content=article:1&back=https://evil.test', ['_token' => 'good', 'react' => 'like']));

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('/|reactions.refused', $response->getContent());
        self::assertSame([], $this->stage->repository->of($this->article, 'ada'));
    }

    public function testABadTokenAndAVisitorAreRefused(): void
    {
        self::assertSame([403, '/page?a=1|reactions.refused'], $this->outcome(['_token' => 'bad', 'react' => 'like']));

        $this->stage->member = null;
        self::assertSame([403, '/page?a=1|reactions.refused'], $this->outcome(['_token' => 'good', 'react' => 'like']));
        self::assertSame([], $this->stage->repository->of($this->article, 'ada'));
    }

    public function testTheVoterRefuses(): void
    {
        $this->stage->refused = ['like'];

        self::assertSame([403, '/page?a=1|reactions.refused'], $this->outcome(['_token' => 'good', 'react' => 'like']));
    }

    public function testAnUnknownReaction(): void
    {
        self::assertSame([422, '/page?a=1|reactions.unknown'], $this->outcome(['_token' => 'good', 'react' => 'nothing']));
        self::assertSame([422, '/page?a=1|reactions.unknown'], $this->outcome(['_token' => 'good']));
    }

    public function testTooManyReactions(): void
    {
        $this->stage->tooMany = true;

        $response = $this->submit(['_token' => 'good', 'react' => 'like']);

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        self::assertSame('/page?a=1|reactions.too_many', $response->getContent());
        self::assertSame('12', $response->headers->get('Retry-After'));
    }

    /**
     * @param array<string, string> $body
     *
     * @return array{int, string|false}
     */
    private function outcome(array $body): array
    {
        $response = $this->submit($body);

        return [$response->getStatusCode(), $response->getContent()];
    }

    /**
     * @param array<string, string> $body
     */
    private function submit(array $body): Response
    {
        return $this->controller->__invoke($this->post($this->guard->action('article:1', '/page?a=1'), $body));
    }

    /**
     * @param array<string, string> $body
     */
    private function post(string $url, array $body): Request
    {
        return Request::create($url, 'POST', $body);
    }
}
