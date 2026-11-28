<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Tests\Unit\Infrastructure;

use Phil\SocialCardsBundle\Application\FetchedPage;
use Phil\SocialCardsBundle\Application\LinkPreviews;
use Phil\SocialCardsBundle\Infrastructure\Http\LinkPreviewController;
use Phil\SocialCardsBundle\Tests\Support\FakeFetcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The endpoint of pasted links fetches nothing for a visitor who is not granted its access attribute.
 */
#[CoversClass(LinkPreviewController::class)]
final class LinkPreviewControllerTest extends TestCase
{
    private FakeFetcher $fetcher;

    protected function setUp(): void
    {
        $this->fetcher = new FakeFetcher(['https://example.org/post' => new FetchedPage('<head><meta property="og:title" content="Un billet"></head>', 'https://example.org/post')]);
    }

    public function testWithoutAnAuthorizationCheckerTheEndpointIsRefusedToEveryone(): void
    {
        $response = new LinkPreviewController(new LinkPreviews($this->fetcher))($this->request());

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame('{"error":"Previewing links is for the members who write."}', $response->getContent());
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame([], $this->fetcher->fetched, 'Nothing is fetched.');
    }

    public function testTheAttributeIsAskedOfTheAuthorizationChecker(): void
    {
        $checker = new class implements AuthorizationCheckerInterface {
            /** @var list<mixed> */
            public array $asked = [];

            public bool $grants = false;

            public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                $this->asked[] = $attribute;

                return $this->grants;
            }
        };

        $controller = new LinkPreviewController(new LinkPreviews($this->fetcher), authorizationChecker: $checker, accessAttribute: 'ROLE_WRITER');
        self::assertSame(Response::HTTP_FORBIDDEN, $controller($this->request())->getStatusCode());
        self::assertSame([], $this->fetcher->fetched);

        $checker->grants = true;
        self::assertSame(Response::HTTP_OK, $controller($this->request())->getStatusCode());
        self::assertSame(['ROLE_WRITER', 'ROLE_WRITER'], $checker->asked);
        self::assertSame(['https://example.org/post'], $this->fetcher->fetched);
        self::assertSame('IS_AUTHENTICATED', LinkPreviewController::ACCESS_ATTRIBUTE);
    }

    public function testANullAttributeOpensTheEndpointToEveryVisitor(): void
    {
        $response = new LinkPreviewController(new LinkPreviews($this->fetcher), accessAttribute: null)($this->request());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(['https://example.org/post'], $this->fetcher->fetched);
    }

    private function request(): Request
    {
        return Request::create('https://example.com/social-cards/link-preview', 'GET', ['url' => 'https://example.org/post']);
    }
}
