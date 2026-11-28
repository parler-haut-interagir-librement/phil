<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Infrastructure;

use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Application\Passkeys;
use Phil\IdentityProviderBundle\Domain\WebAuthn\RelyingParty;
use Phil\IdentityProviderBundle\Infrastructure\Http\Pages;
use Phil\IdentityProviderBundle\Infrastructure\Http\PasskeyController;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryPasskeyRepository;
use Phil\IdentityProviderBundle\Infrastructure\Security\PasskeyAuthenticator;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\ArrayChallengeStore;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Services;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * The answer of a passkey is read to one byte past `Passkeys::MAX_RESPONSE` and no further, with or without a
 * `Content-Length`, signed out at sign-in as signed in at registration.
 */
#[CoversClass(PasskeyAuthenticator::class)]
#[CoversClass(PasskeyController::class)]
final class PasskeyBodyTest extends TestCase
{
    private Services $services;

    private Passkeys $passkeys;

    protected function setUp(): void
    {
        $this->services = new Services();
        $this->passkeys = new Passkeys(new RelyingParty('example.com', 'Example', ['https://example.com']), new InMemoryPasskeyRepository(), $this->services->accounts, new ArrayChallengeStore(), $this->services->journal, $this->services->clock);
    }

    public function testASignInLongerThanAnAnswerIsRefusedOneBytePastTheLimit(): void
    {
        $this->passkeys->signInOptions();
        [$request, $stream] = $this->request(Passkeys::MAX_RESPONSE + 4096);

        try {
            new PasskeyAuthenticator($this->passkeys, new TokenStorage())->authenticate($request);
            self::fail('A body past the limit signs nobody in.');
        } catch (CustomUserMessageAuthenticationException $customUserMessageAuthenticationException) {
            self::assertSame('passkey.refused', $customUserMessageAuthenticationException->getMessageKey());
            self::assertSame(0, $customUserMessageAuthenticationException->getCode());
        }

        self::assertSame(Passkeys::MAX_RESPONSE + 1, ftell($stream));
    }

    public function testARegistrationLongerThanAnAnswerIsRefusedOneBytePastTheLimit(): void
    {
        $account = $this->services->account();
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken(new AccountUser($account), 'main'));

        $authorization = self::createStub(AuthorizationCheckerInterface::class);
        $authorization->method('isGranted')->willReturn(true);
        $csrf = self::createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(true);
        $pages = new Pages(new Environment(new ArrayLoader()), $tokens, $authorization, $csrf, self::createStub(UrlGeneratorInterface::class), 'layout', 'step_up');
        $this->passkeys->registrationOptions($account);
        [$request, $stream] = $this->request(Passkeys::MAX_RESPONSE + 4096);
        $request->headers->set('X-CSRF-Token', 'token');

        $response = new PasskeyController($pages, $this->passkeys)->register($request);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('{"error":"passkey.refused"}', $response->getContent());
        self::assertSame(Passkeys::MAX_RESPONSE + 1, ftell($stream));
    }

    /**
     * @return array{Request, resource}
     */
    private function request(int $bytes): array
    {
        $stream = fopen('php://temp', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, str_repeat(' ', $bytes));
        rewind($stream);
        $request = Request::create('/passkey/login', 'POST', content: $stream);
        $request->headers->remove('Content-Length');

        return [$request, $stream];
    }
}
