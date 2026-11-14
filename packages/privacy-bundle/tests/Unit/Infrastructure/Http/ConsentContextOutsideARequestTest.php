<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Http;

use Phil\PrivacyBundle\Domain\Consent\ConsentCategory;
use Phil\PrivacyBundle\Domain\Consent\ConsentPolicy;
use Phil\PrivacyBundle\Domain\Consent\ConsentState;
use Phil\PrivacyBundle\Domain\Consent\ConsentToken;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentContext;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentCookie;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentVaryListener;
use Phil\PrivacyBundle\Infrastructure\Http\CookieInventoryFactory;
use Phil\PrivacyBundle\Infrastructure\Http\PrivacyPages;
use Phil\PrivacyBundle\Infrastructure\Twig\ConsentExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Loader\ArrayLoader;
use Twig\Node\Nodes;
use Twig\TwigFunction;

#[CoversClass(ConsentContext::class)]
#[CoversClass(ConsentVaryListener::class)]
#[CoversClass(ConsentExtension::class)]
#[CoversClass(CookieInventoryFactory::class)]
final class ConsentContextOutsideARequestTest extends TestCase
{
    public function testOutsideARequestNothingIsGranted(): void
    {
        $state = $this->context(new RequestStack())->state();

        self::assertFalse($state->isGranted('analytics'));
        self::assertTrue($state->isPending());
        self::assertFalse($state->globalPrivacyControl);
    }

    public function testTheStateIsKeptOnTheMainRequestOnly(): void
    {
        $main = new Request();
        $stack = new RequestStack([$main]);
        $stack->push($sub = new Request());

        $context = $this->context($stack);

        self::assertSame($context->state(), $context->state());
        self::assertInstanceOf(ConsentState::class, $main->attributes->get(ConsentContext::ATTRIBUTE));
        self::assertFalse($sub->attributes->has(ConsentContext::ATTRIBUTE));
    }

    public function testAResponseThatDidNotReadTheConsentDoesNotVary(): void
    {
        $response = new Response();
        new ConsentVaryListener()->onKernelResponse(new ResponseEvent(self::createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $response));

        self::assertSame([], $response->getVary());
    }

    public function testASubRequestDoesNotDecideTheVary(): void
    {
        $response = new Response();
        $request = new Request();
        $request->attributes->set(ConsentContext::ATTRIBUTE, true);
        new ConsentVaryListener()->onKernelResponse(new ResponseEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::SUB_REQUEST, $response));

        self::assertSame([], $response->getVary());
    }

    public function testAVaryAlreadySetIsKept(): void
    {
        $response = new Response();
        $response->setVary('Accept');

        $request = new Request();
        $request->attributes->set(ConsentContext::ATTRIBUTE, true);
        new ConsentVaryListener()->onKernelResponse(new ResponseEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        self::assertSame(['Accept', 'Cookie', 'Sec-GPC'], $response->getVary());
    }

    public function testTheBannerOutsideARequestPostsWithoutATarget(): void
    {
        $stack = new RequestStack();
        $tokens = self::createStub(CsrfTokenManagerInterface::class);
        $tokens->method('getToken')->willReturn(new CsrfToken('phil_privacy', 'tok'));
        $twig = new Environment(new ArrayLoader(['@PhilPrivacy/consent/banner.html.twig' => '[{{ target }}|{{ csrf_token }}|{{ state.isPending ? "p" }}]']));
        $extension = $this->extension($stack, $twig, $tokens);

        self::assertSame('[|tok|p]', $extension->banner($twig));
        $functions = new AttributeExtension(ConsentExtension::class)->getFunctions();
        self::assertSame(['privacy_consent', 'privacy_consent_pending', 'privacy_consent_banner', 'privacy_cookie_policy'], array_map(static fn (TwigFunction $function): string => $function->getName(), $functions));
        self::assertSame(['html'], $functions[2]->getSafe(new Nodes()));
        self::assertTrue($functions[2]->needsEnvironment());
    }

    public function testOnlyASignalOfOneIsTheGlobalPrivacyControl(): void
    {
        $signal = static function (?string $value): bool {
            $request = new Request();

            if (null !== $value) {
                $request->headers->set('Sec-GPC', $value);
            }

            return ConsentContext::sendsGlobalPrivacyControl($request);
        };

        self::assertTrue($signal('1'));
        self::assertFalse($signal('0'));
        self::assertFalse($signal(null));
    }

    public function testTheBannerInARequestComesBackToThePageItWasShownOn(): void
    {
        $stack = new RequestStack([Request::create('/page?tab=2')]);
        $tokens = self::createStub(CsrfTokenManagerInterface::class);
        $tokens->method('getToken')->willReturn(new CsrfToken('phil_privacy', 'tok'));
        $twig = new Environment(new ArrayLoader(['@PhilPrivacy/consent/banner.html.twig' => '[{{ target }}|{{ locale }}|{{ groups|length }}]']));

        self::assertSame('[/page?tab=2|en|2]', $this->extension($stack, $twig, $tokens)->banner($twig));
    }

    public function testTheCookiePolicyIsTheInventoryByGroupInTheLocaleOfTheRequest(): void
    {
        $request = Request::create('/policy');
        $request->setLocale('fr');

        $stack = new RequestStack([$request]);
        $twig = new Environment(new ArrayLoader(['@PhilPrivacy/cookies/_inventory.html.twig' => '{{ locale }}:{% for group in groups %}[{{ group.category ? group.category.name : "own" }}={{ group.cookies|length }}]{% endfor %}']));

        self::assertSame('fr:[own=1][analytics=1]', $this->extension($stack, $twig, self::createStub(CsrfTokenManagerInterface::class))->cookiePolicy($twig));
        self::assertSame('en:[own=1][analytics=1]', $this->extension(new RequestStack(), $twig, self::createStub(CsrfTokenManagerInterface::class))->cookiePolicy($twig));
    }

    private function extension(RequestStack $stack, Environment $twig, CsrfTokenManagerInterface $tokens): ConsentExtension
    {
        $inventory = CookieInventoryFactory::create(['analytics' => ['_a' => ['purpose' => 'Counts', 'duration' => 'P1D']]], 'c', 'P1D');

        return new ConsentExtension($this->context($stack), new PrivacyPages($twig, $tokens, self::createStub(UrlGeneratorInterface::class), 'l'), $stack, $inventory, $this->policy());
    }

    private function policy(): ConsentPolicy
    {
        return new ConsentPolicy('1', [new ConsentCategory('analytics')]);
    }

    private function context(RequestStack $stack): ConsentContext
    {
        return new ConsentContext($stack, new ConsentCookie(new ConsentToken('s'), new MockClock(), 'c', 'P1D'), $this->policy());
    }
}
