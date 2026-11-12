<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Application;

use Phil\BrowserSecurityBundle\Application\ContentSecurityPolicyContext;
use Phil\BrowserSecurityBundle\Application\PolicyOverride;
use Phil\BrowserSecurityBundle\Application\RequestState;
use Phil\BrowserSecurityBundle\Domain\Enum\InlineKind;
use Phil\BrowserSecurityBundle\Domain\Syntax\SourceSyntax;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(RequestState::class)]
#[CoversClass(ContentSecurityPolicyContext::class)]
final class RequestStateTest extends TestCase
{
    public function testTheStateLivesInTheRequest(): void
    {
        $request = new Request();
        $state = RequestState::of($request);

        self::assertSame($state, RequestState::of($request));
        self::assertSame($state, $request->attributes->get(RequestState::ATTRIBUTE));
        self::assertNotSame($state, RequestState::of(new Request()));

        $request->attributes->set(RequestState::ATTRIBUTE, 'not a state');
        self::assertInstanceOf(RequestState::class, RequestState::of($request));
        self::assertNotSame($state, RequestState::of($request));
    }

    public function testANonceIsCreatedOnceByKind(): void
    {
        $state = new RequestState();
        $script = $state->nonce(InlineKind::Script);

        self::assertMatchesRegularExpression('#^[A-Za-z0-9+/]{22}==$#', $script);
        self::assertSame($script, $state->nonce(InlineKind::Script));
        self::assertNotSame($script, $state->nonce(InlineKind::Style));
        self::assertSame(['script' => $script, 'style' => $state->nonce(InlineKind::Style)], $state->nonces());
        self::assertNotSame(RequestState::newNonce(), RequestState::newNonce());
    }

    public function testTheSourcesOfEachKind(): void
    {
        $state = new RequestState();
        self::assertSame([], $state->sources(InlineKind::Script));

        $state->addHash(InlineKind::Script, 'a()');
        $state->addHash(InlineKind::Script, 'a()');
        $state->addHash(InlineKind::Style, 'b{}');
        self::assertSame([SourceSyntax::hashOf('a()')], $state->sources(InlineKind::Script));

        $nonce = $state->nonce(InlineKind::Script);
        self::assertSame(["'nonce-".$nonce."'", SourceSyntax::hashOf('a()')], $state->sources(InlineKind::Script));
        self::assertSame([SourceSyntax::hashOf('b{}')], $state->sources(InlineKind::Style));
        self::assertFalse($state->sharedNonce);
    }

    public function testASharedNonceAllowsStylesToo(): void
    {
        $state = new RequestState();
        $shared = $state->shareScriptNonce();

        self::assertTrue($state->sharedNonce);
        self::assertSame($shared, $state->nonce(InlineKind::Script));
        self::assertSame(["'nonce-".$shared."'"], $state->sources(InlineKind::Style));

        $style = $state->nonce(InlineKind::Style);
        self::assertSame(["'nonce-".$style."'", "'nonce-".$shared."'"], $state->sources(InlineKind::Style));
    }

    public function testTheOverridesInOrder(): void
    {
        $state = new RequestState();
        $first = new PolicyOverride('a');
        $second = new PolicyOverride('b');
        $state->addOverride($first);
        $state->addOverride($second);

        self::assertSame([$first, $second], $state->overrides);
    }

    public function testTheContextWritesToTheMainRequest(): void
    {
        $main = new Request();
        $requests = new RequestStack([$main]);
        $requests->push(new Request());

        $context = new ContentSecurityPolicyContext($requests);
        $state = RequestState::of($main);

        self::assertSame($state->nonce(InlineKind::Script), $context->nonce());
        self::assertSame($state->nonce(InlineKind::Style), $context->nonce('style'));
        self::assertSame($state->nonce(InlineKind::Style), $context->nonce(InlineKind::Style));
        self::assertSame($state->nonce(InlineKind::Script), $context->sharedNonce());
        self::assertTrue($state->sharedNonce);

        $context->allowInline('script', 'a()');
        $context->allowInline(InlineKind::Style, 'b{}');
        self::assertContains(SourceSyntax::hashOf('a()'), $state->sources(InlineKind::Script));
        self::assertContains(SourceSyntax::hashOf('b{}'), $state->sources(InlineKind::Style));

        $override = new PolicyOverride('preview');
        $context->override($override);
        self::assertSame([$override], $state->overrides);
    }

    public function testWithoutARequestTheContextStillGivesANonce(): void
    {
        $context = new ContentSecurityPolicyContext(new RequestStack());

        self::assertMatchesRegularExpression('#^[A-Za-z0-9+/]{22}==$#', $context->nonce());
        self::assertNotSame($context->nonce(), $context->nonce());
        self::assertMatchesRegularExpression('#^[A-Za-z0-9+/]{22}==$#', $context->sharedNonce());
        $context->allowInline('script', 'a()');
        $context->override(new PolicyOverride());
    }
}
