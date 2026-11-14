<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Twig;

use Phil\PrivacyBundle\Domain\Consent\ConsentCategory;
use Phil\PrivacyBundle\Domain\Consent\ConsentPolicy;
use Phil\PrivacyBundle\Domain\Consent\ConsentToken;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentContext;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentCookie;
use Phil\PrivacyBundle\Infrastructure\Http\CookieInventoryFactory;
use Phil\PrivacyBundle\Infrastructure\Http\PrivacyPages;
use Phil\PrivacyBundle\Infrastructure\Twig\ConsentExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(ConsentExtension::class)]
final class ConsentExtensionTest extends TestCase
{
    public function testTheBannerSpeaksTheLocaleOfTheRequest(): void
    {
        $request = Request::create('/page');
        $request->setLocale('fr');

        $stack = new RequestStack([$request]);
        $tokens = self::createStub(CsrfTokenManagerInterface::class);
        $tokens->method('getToken')->willReturn(new CsrfToken('phil_privacy', 'tok'));
        $twig = new Environment(new ArrayLoader(['@PhilPrivacy/consent/banner.html.twig' => '[{{ locale }}]']));
        $policy = new ConsentPolicy('1', [new ConsentCategory('analytics')]);
        $extension = new ConsentExtension(
            new ConsentContext($stack, new ConsentCookie(new ConsentToken('s'), new MockClock(), 'c', 'P1D'), $policy),
            new PrivacyPages($twig, $tokens, self::createStub(UrlGeneratorInterface::class), 'l'),
            $stack,
            CookieInventoryFactory::create([], 'c', 'P1D'),
            $policy,
        );

        self::assertSame('[fr]', $extension->banner($twig));
    }
}
