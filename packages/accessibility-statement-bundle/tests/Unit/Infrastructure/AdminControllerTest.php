<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Tests\Unit\Infrastructure;

use Phil\AccessibilityStatement\StatementReader;
use Phil\AccessibilityStatementBundle\Application\StatementPublisher;
use Phil\AccessibilityStatementBundle\Infrastructure\Http\StatementAdminController;
use Phil\AccessibilityStatementBundle\Tests\Unit\Fixture\MemoryStatements;
use Phil\AccessibilityStatementBundle\Tests\Unit\Fixture\Statements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;

/**
 * The administration page refuses rather than opens: without the role, without Symfony Security, without its CSRF
 * protection.
 */
#[CoversClass(StatementAdminController::class)]
final class AdminControllerTest extends TestCase
{
    public function testWithoutTheRoleThePageIsRefusedBySecurity(): void
    {
        try {
            $this->controller($this->checker(false), $this->csrf())->index(Request::create('/'));
            self::fail('opened');
        } catch (AccessDeniedException $accessDeniedException) {
            self::assertSame(['ROLE_EDITOR'], $accessDeniedException->getAttributes());
            self::assertSame('The administration of the accessibility statement is not open to you.', $accessDeniedException->getMessage());
        }

        $this->expectException(AccessDeniedException::class);
        $this->controller($this->checker(false), $this->csrf())->publish(Request::create('/', 'POST', ['_token' => 'valid']));
    }

    public function testWithoutSymfonySecurityNobodyIsLetIn(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessageIsOrContains('The administration of the accessibility statement needs Symfony Security.');

        $this->controller(null, $this->csrf())->index(Request::create('/'));
    }

    public function testWithoutCsrfProtectionNothingIsPublished(): void
    {
        $controller = $this->controller($this->checker(true), null);
        self::assertSame('|0|2|writable', $controller->index(Request::create('/'))->getContent(), 'no token to give');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessageIsOrContains('The form of the accessibility statement expired: reload the page.');
        $controller->publish(Request::create('/', 'POST', ['_token' => 'valid', 'statement' => (string) json_encode(Statements::minimal(2))]));
    }

    public function testThePageCarriesTheTokenAndWhatWasPublished(): void
    {
        $statements = new MemoryStatements([StatementReader::fromArray(Statements::minimal())], false);
        $controller = $this->controller($this->checker(true), $this->csrf(), $statements);

        self::assertSame('token-value|3|2|read only', $controller->index(Request::create('/?published=3'))->getContent());
        $refused = $controller->publish(Request::create('/', 'POST', ['_token' => 'valid', 'statement' => (string) json_encode(Statements::minimal(2))]));
        self::assertSame(422, $refused->getStatusCode());
        self::assertSame('token-value|0|2|read only|The statement comes from the configuration: a new version is written there.', $refused->getContent());
    }

    public function testThePageIsGivenItsLayoutTheLatestStatementAndTheVersionsAndIsNeverStored(): void
    {
        $controller = $this->controller($this->checker(true), $this->csrf(), template: '{{ layout }}|{{ latest.version }}|{{ versions|length }}');

        $page = $controller->index(Request::create('/'));
        self::assertSame('layout|1|1', $page->getContent());
        self::assertTrue($page->headers->hasCacheControlDirective('no-store'));

        $refused = $controller->publish(Request::create('/', 'POST', ['_token' => 'valid', 'statement' => '{}']));
        self::assertSame(422, $refused->getStatusCode());
        self::assertTrue($refused->headers->hasCacheControlDirective('no-store'));
    }

    private function controller(?AuthorizationCheckerInterface $checker, ?CsrfTokenManagerInterface $csrf, ?MemoryStatements $statements = null, ?string $template = null): StatementAdminController
    {
        $statements ??= new MemoryStatements([StatementReader::fromArray(Statements::minimal())]);
        $routes = new RouteCollection();
        $routes->add(StatementAdminController::ROUTE, new Route('/admin'));

        $twig = new Environment(new ArrayLoader([StatementAdminController::TEMPLATE => $template ?? '{{ csrf_token }}|{{ published }}|{{ draft is null ? "" : (draft|json_decode).version }}|{{ writable ? "writable" : "read only" }}{% if error %}|{{ error }}{% endif %}']));
        $twig->addFilter(new TwigFilter('json_decode', static fn (string $json): mixed => json_decode($json)));

        return new StatementAdminController($statements, new StatementPublisher($statements, new MockClock('2026-11-08T12:00:00+00:00')), $twig, new UrlGenerator($routes, new RequestContext()), 'layout', 'ROLE_EDITOR', $checker, $csrf);
    }

    private function checker(bool $granted): AuthorizationCheckerInterface
    {
        return new readonly class($granted) implements AuthorizationCheckerInterface {
            public function __construct(
                private bool $granted,
            ) {
            }

            public function isGranted(mixed $attribute, mixed $subject = null, mixed $accessDecision = null): bool
            {
                return $this->granted && 'ROLE_EDITOR' === $attribute;
            }
        };
    }

    private function csrf(): CsrfTokenManagerInterface
    {
        return new class implements CsrfTokenManagerInterface {
            public function getToken(string $tokenId): CsrfToken
            {
                return new CsrfToken($tokenId, StatementAdminController::CSRF_TOKEN === $tokenId ? 'token-value' : 'other');
            }

            public function refreshToken(string $tokenId): CsrfToken
            {
                return $this->getToken($tokenId);
            }

            public function removeToken(string $tokenId): ?string
            {
                return null;
            }

            public function isTokenValid(CsrfToken $token): bool
            {
                return StatementAdminController::CSRF_TOKEN === $token->getId() && 'valid' === $token->getValue();
            }
        };
    }
}
