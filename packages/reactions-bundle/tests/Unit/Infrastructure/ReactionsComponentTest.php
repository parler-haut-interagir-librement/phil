<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\ReactionsBundle\Application\View\ReactionItem;
use Phil\ReactionsBundle\Domain\Model\Reaction;
use Phil\ReactionsBundle\Infrastructure\Http\ReactionGuard;
use Phil\ReactionsBundle\Infrastructure\Twig\Components\ReactionsComponent;
use Phil\ReactionsBundle\Infrastructure\Twig\KitComponents;
use Phil\ReactionsBundle\Tests\Fixtures\Stage;
use Phil\ReactionsBundle\Tests\Fixtures\Tokens;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\UX\LiveComponent\LiveResponder;

#[CoversClass(ReactionsComponent::class)]
final class ReactionsComponentTest extends TestCase
{
    private Stage $stage;

    private RequestStack $requests;

    private LiveResponder $responder;

    private ReactionsComponent $component;

    private ResourceReference $article;

    #[Override]
    protected function setUp(): void
    {
        $this->build(new Stage());
    }

    private function build(Stage $stage): void
    {
        $this->stage = $stage;
        $this->requests = new RequestStack();
        $this->requests->push(Request::create('/page?a=1'));

        $routes = new RouteCollection();
        $routes->add(ReactionGuard::ROUTE, new Route('/submit'));

        $guard = new ReactionGuard(new Tokens(), new UriSigner(str_repeat('s', 32)), new UrlGenerator($routes, new RequestContext(host: 'site.test')));
        $translator = new IdentityTranslator();
        $translator->setLocale('de');

        $this->component = new ReactionsComponent($this->stage->reactions, $this->stage->board, $this->stage, $guard, new KitComponents('ui'), $this->requests, $translator);
        $this->responder = new LiveResponder();
        $this->component->setLiveResponder($this->responder);
        $this->article = ResourceReference::of('article', '1');
        $this->component->mount('article:1');
    }

    public function testMounting(): void
    {
        self::assertSame(['article:1', '/page?a=1'], [$this->component->content, $this->component->path]);
        self::assertSame(36, \strlen($this->component->instance));

        $other = clone $this->component;
        $other->mount(ResourceReference::of('article', '2'));
        self::assertSame('article:2', $other->content);
        self::assertNotSame($this->component->instance, $other->instance);

        $this->requests->pop();
        $other->mount('article:3');
        self::assertSame('/', $other->path);
    }

    public function testWhatTheTemplateReads(): void
    {
        self::assertSame('good', $this->component->getToken());
        self::assertStringContainsString('content=article%3A1', $this->component->getFormAction());
        self::assertStringContainsString('back=%2Fpage%3Fa%3D1', $this->component->getFormAction());
        self::assertSame('ui:ReactionButton', $this->component->kit('ReactionButton'));
        self::assertSame('de', $this->component->getLocale());
        self::assertSame(['like', 'thanks', 'not_useful'], array_map(static fn (ReactionItem $item): string => $item->code, $this->component->getPanel()->bar));
    }

    public function testReactingReadsTheContentAgainAndTellsTheOthers(): void
    {
        self::assertSame(0, $this->component->getPanel()->bar[0]->count);

        $this->component->react('like', 'good');

        self::assertSame('reactions.added', $this->component->notice);
        self::assertSame([1, true], [$this->component->getPanel()->bar[0]->count, $this->component->getPanel()->bar[0]->pressed]);
        self::assertSame([['event' => 'phil-reactions-changed', 'payload' => ['content' => 'article:1', 'instance' => $this->component->instance]]], $this->responder->getBrowserEventsToDispatch());
    }

    public function testNothingChangedTellsNobody(): void
    {
        $this->stage->reactions->react($this->article, 'ada', 'like');

        $this->component->react('like', 'good');

        self::assertSame('reactions.added', $this->component->notice);
        self::assertSame([], $this->responder->getBrowserEventsToDispatch());
    }

    public function testWithdrawing(): void
    {
        $this->stage->reactions->react($this->article, 'ada', 'like');
        self::assertTrue($this->component->getPanel()->bar[0]->pressed);

        $this->component->withdraw('good');

        self::assertSame('reactions.removed', $this->component->notice);
        self::assertFalse($this->component->getPanel()->bar[0]->pressed);
        self::assertSame([], $this->stage->repository->of($this->article, 'ada'));
    }

    public function testSeveralReactionsUpToTheCap(): void
    {
        $this->build(new Stage(2));
        $this->component->react('like', 'good');
        $this->component->react('thanks', 'good');

        $panel = $this->component->getPanel();
        self::assertTrue($panel->full);
        self::assertSame([['like', true, true], ['thanks', true, true]], array_map(static fn (ReactionItem $item): array => [$item->code, $item->pressed, $item->interactive], $panel->bar));
        self::assertSame([], $panel->picker);

        $this->component->react('spark', 'good');
        self::assertSame('reactions.limit', $this->component->notice);

        $this->component->withdraw('good', 'like');
        self::assertSame('reactions.removed', $this->component->notice);
        self::assertSame(['thanks'], array_map(static fn (Reaction $reaction): string => $reaction->code(), $this->stage->repository->of($this->article, 'ada')));
        self::assertFalse($this->component->getPanel()->full);
    }

    public function testTheMemberIsTheOneOfTheRequest(): void
    {
        $this->stage->member = 'linus';

        $this->component->react('like', 'good');

        self::assertSame('like', ($this->stage->repository->of($this->article, 'linus')[0] ?? null)?->code());
        self::assertSame([], $this->stage->repository->of($this->article, 'ada'));
    }

    public function testWhatIsRefusedIsSaidInTheLiveRegion(): void
    {
        $this->component->react('like', 'bad');
        self::assertSame('reactions.refused', $this->component->notice);
        self::assertSame([], $this->stage->repository->of($this->article, 'ada'));

        $this->component->notice = '';
        $this->component->react('nothing', 'good');
        self::assertSame('reactions.refused', $this->component->notice);

        $this->stage->member = null;
        $this->component->notice = '';
        $this->component->withdraw('good');
        self::assertSame('reactions.refused', $this->component->notice);

        $this->stage->member = 'ada';
        $this->stage->tooMany = true;

        $this->component->react('like', 'good');
        self::assertSame('reactions.too_many', $this->component->notice);
        self::assertSame([], $this->responder->getBrowserEventsToDispatch());
    }
}
