<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use LogicException;
use Phil\SocialCardsBundle\Application\FetchedPage;
use Phil\SocialCardsBundle\Application\LinkPreviews;
use Phil\SocialCardsBundle\Infrastructure\Http\LinkPreviewController;
use Phil\SocialCardsBundle\Tests\Support\FakeFetcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
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

    public function testEveryAnswerIsPrivateKeptFiveMinutesAndNotSniffed(): void
    {
        $controller = new LinkPreviewController(new LinkPreviews($this->fetcher), accessAttribute: null);

        $answers = [
            $controller($this->request()),
            $controller(Request::create('https://example.com/social-cards/link-preview', 'GET', ['url' => 'https://example.org/none'])),
            $controller(Request::create('https://example.com/social-cards/link-preview', 'GET', ['url' => 'ftp://example.org/post'])),
        ];

        self::assertSame([Response::HTTP_OK, Response::HTTP_NO_CONTENT, Response::HTTP_BAD_REQUEST], array_map(static fn (Response $r): int => $r->getStatusCode(), $answers));

        foreach ($answers as $response) {
            self::assertTrue($response->headers->hasCacheControlDirective('private'));
            self::assertFalse($response->headers->hasCacheControlDirective('public'));
            self::assertSame('300', $response->headers->getCacheControlDirective('max-age'));
            self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        }
    }

    public function testAMissingAddressIsRefused(): void
    {
        $controller = new LinkPreviewController(new LinkPreviews($this->fetcher), accessAttribute: null);

        $response = $controller(Request::create('https://example.com/social-cards/link-preview'));

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('{"error":"The address is not an http or https address of a host name."}', $response->getContent());

        self::assertSame([], $this->fetcher->fetched);
    }

    public function testASpentRateLimiterAnswersTooManyPreviewsWithRetryAfter(): void
    {
        $limiter = new RateLimiterFactory(['id' => 'preview', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 hour'], new InMemoryStorage());
        $controller = new LinkPreviewController(new LinkPreviews($this->fetcher), $limiter, accessAttribute: null);
        $request = $this->request();
        $request->server->set('REMOTE_ADDR', '203.0.113.7');

        self::assertSame(Response::HTTP_OK, $controller($request)->getStatusCode());

        $response = $controller($request);

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        self::assertSame('{"error":"Too many previews; try again later."}', $response->getContent());
        self::assertGreaterThan(1, (int) $response->headers->get('Retry-After'));
        self::assertLessThanOrEqual(3601, (int) $response->headers->get('Retry-After'));
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertSame(['https://example.org/post'], $this->fetcher->fetched, 'The refused call fetches nothing.');

        $other = $this->request();
        $other->server->set('REMOTE_ADDR', '203.0.113.8');
        self::assertSame(Response::HTTP_OK, $controller($other)->getStatusCode(), 'The limiter is keyed by the client address.');
    }

    public function testAnUnknownClientAddressSharesTheUnknownKey(): void
    {
        $limiter = new RateLimiterFactory(['id' => 'preview', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 hour'], new InMemoryStorage());
        $controller = new LinkPreviewController(new LinkPreviews($this->fetcher), $limiter, accessAttribute: null);
        $request = $this->request();
        $request->server->remove('REMOTE_ADDR');

        self::assertNull($request->getClientIp());
        self::assertSame(Response::HTTP_OK, $controller($request)->getStatusCode());
        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $controller($request)->getStatusCode());
    }

    public function testRetryAfterIsAtLeastOneSecond(): void
    {
        foreach (['-10 seconds' => '1', '+0 seconds' => '1', '+1 second' => '1', '+100 seconds' => null] as $retryAt => $expected) {
            $spent = new RateLimit(0, new DateTimeImmutable($retryAt), false, 1);
            $limiter = new class($spent) implements RateLimiterFactoryInterface {
                public function __construct(private readonly RateLimit $spent)
                {
                }

                public function create(?string $key = null): LimiterInterface
                {
                    $spent = $this->spent;

                    return new class($spent) implements LimiterInterface {
                        public function __construct(private readonly RateLimit $spent)
                        {
                        }

                        public function reserve(int $tokens = 1, ?float $maxTime = null): \Symfony\Component\RateLimiter\Reservation
                        {
                            throw new LogicException('Not used.');
                        }

                        public function consume(int $tokens = 1): RateLimit
                        {
                            return $this->spent;
                        }

                        public function reset(): void
                        {
                        }
                    };
                }
            };

            $response = (new LinkPreviewController(new LinkPreviews($this->fetcher), $limiter, accessAttribute: null))($this->request());
            $seconds = (int) $response->headers->get('Retry-After');

            if (null === $expected) {
                self::assertGreaterThanOrEqual(99, $seconds);
                self::assertLessThanOrEqual(100, $seconds);
            } else {
                self::assertSame(1, $seconds, $retryAt);
            }
        }
    }

    private function request(): Request
    {
        return Request::create('https://example.com/social-cards/link-preview', 'GET', ['url' => 'https://example.org/post']);
    }
}
