<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\AccessBundle\Domain\Exception\InvalidAccess;
use Phil\AccessBundle\Domain\ValueObject\MemberId;
use Phil\AccessBundle\Infrastructure\Http\ViewAsController;
use Phil\AccessBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;

#[CoversClass(ViewAsController::class)]
final class ViewAsControllerTest extends TestCase
{
    private Stage $stage;

    private CsrfTokenManager $tokens;

    #[Override]
    protected function setUp(): void
    {
        $this->stage = new Stage();
        $this->stage->spaces->addAdmin(MemberId::of('ada'));
        $this->stage->member->signIn('ada');

        $this->tokens = new CsrfTokenManager(new UriSafeTokenGenerator(), new class implements TokenStorageInterface {
            /** @var array<string, string> */
            private array $tokens = [];

            #[Override]
            public function getToken(string $tokenId): string
            {
                return $this->tokens[$tokenId] ?? '';
            }

            #[Override]
            public function setToken(string $tokenId, #[SensitiveParameter] string $token): void
            {
                $this->tokens[$tokenId] = $token;
            }

            #[Override]
            public function removeToken(string $tokenId): ?string
            {
                return null;
            }

            #[Override]
            public function hasToken(string $tokenId): bool
            {
                return isset($this->tokens[$tokenId]);
            }
        });
    }

    public function testStartAndStop(): void
    {
        $controller = new ViewAsController($this->stage->viewAs, $this->tokens, '/admin');

        $response = $controller->start($this->post(), 'ann');
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/admin', $response->getTargetUrl());
        self::assertSame('ann', $this->stage->viewAs->member()?->value);

        $response = $controller->stop($this->post());
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/admin', $response->getTargetUrl());
        self::assertNull($this->stage->viewAs->member());
    }

    public function testTheDefaultRedirect(): void
    {
        self::assertSame('/', new ViewAsController($this->stage->viewAs, $this->tokens)->stop($this->post())->getTargetUrl());
    }

    public function testAFormWithoutItsTokenIsRefused(): void
    {
        $controller = new ViewAsController($this->stage->viewAs, $this->tokens);

        foreach ([Request::create('/', 'POST'), Request::create('/', 'POST', ['_token' => 'forged'])] as $request) {
            try {
                $controller->start($request, 'ann');
                self::fail('The form should be refused.');
            } catch (AccessDeniedHttpException $denied) {
                self::assertSame('This form was not sent from this site.', $denied->getMessage());
            }
        }

        self::assertNull($this->stage->viewAs->member());
    }

    public function testStoppingWithoutItsTokenIsRefused(): void
    {
        $controller = new ViewAsController($this->stage->viewAs, $this->tokens);
        $controller->start($this->post(), 'ann');

        try {
            $controller->stop(Request::create('/', 'POST', ['_token' => 'forged']));
            self::fail('The form should be refused.');
        } catch (AccessDeniedHttpException) {
        }

        self::assertSame('ann', $this->stage->viewAs->member()?->value);
    }

    public function testARefusedViewIsForbidden(): void
    {
        $this->stage->member->signIn('ann');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessageIs('Only an admin of the space views the site as a member.');

        new ViewAsController($this->stage->viewAs, $this->tokens)->start($this->post(), 'olga');
    }

    public function testAnInvalidMemberIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        new ViewAsController($this->stage->viewAs, $this->tokens)->start($this->post(), 'ann@example.org');
    }

    public function testTheRedirectIsAPathOfTheSite(): void
    {
        foreach (['//evil.example', 'https://evil.example', '/\\evil.example', 'admin', ''] as $redirect) {
            try {
                new ViewAsController($this->stage->viewAs, $this->tokens, $redirect);
                self::fail(\sprintf('"%s" should be refused.', $redirect));
            } catch (InvalidAccess $invalid) {
                self::assertSame(\sprintf('"%s" is not a path of the site: it starts with one "/".', $redirect), $invalid->getMessage());
            }
        }
    }

    private function post(): Request
    {
        return Request::create('/', 'POST', ['_token' => $this->tokens->getToken(ViewAsController::TOKEN_ID)->getValue()]);
    }
}
