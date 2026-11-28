<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Tests\Unit\Infrastructure;

use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderGoogleBundle\Infrastructure\Federation\GoogleProvider;
use Phil\IdentityProviderGoogleBundle\Tests\Fixture\FakeGoogle;
use Phil\IdentityProviderGoogleBundle\Tests\Fixture\SignInHarness;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(GoogleProvider::class)]
final class GoogleProviderTest extends TestCase
{
    public function testItIsGoogle(): void
    {
        $provider = new GoogleProvider(new SignInHarness()->signIn);

        self::assertSame('google', $provider->name());
        self::assertSame('Google', $provider->label());
    }

    public function testASignInGoesToGoogleAndComesBack(): void
    {
        $harness = new SignInHarness();
        $provider = new GoogleProvider($harness->signIn);
        $request = new Request();
        $request->setSession($harness->session);

        $response = $provider->start($request, 'https://example.com/login/with/google/callback');
        self::assertInstanceOf(RedirectResponse::class, $response);
        parse_str(substr($response->getTargetUrl(), (int) strpos($response->getTargetUrl(), '?') + 1), $parameters);
        self::assertSame('https://example.com/login/with/google/callback', $parameters['redirect_uri']);
        self::assertIsString($parameters['nonce']);
        $harness->google->idToken = FakeGoogle::sign(FakeGoogle::claims($parameters['nonce']));

        $callback = new Request(['state' => $parameters['state'], 'code' => 'c'], ['state' => 'posted', 'code' => 'posted']);
        $callback->setSession($harness->session);

        self::assertSame(FakeGoogle::SUBJECT, $provider->finish($callback, 'https://example.com/login/with/google/callback')->subject);
    }

    public function testGoogleUnreachableRefusesTheStart(): void
    {
        $harness = new SignInHarness();
        $harness->google->answers[FakeGoogle::DISCOVERY] = new MockResponse('down', ['http_code' => 503]);
        $request = new Request();
        $request->setSession($harness->session);

        $this->expectExceptionObject(Refused::because('external.failed'));
        new GoogleProvider($harness->signIn)->start($request, 'https://example.com/cb');
    }
}
