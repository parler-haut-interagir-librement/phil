<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Tests\Unit\Infrastructure;

use Phil\ReviewsBundle\Application\IdentifierDirectory;
use Phil\ReviewsBundle\Domain\Exception\InvalidReview;
use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewContent;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;
use Phil\ReviewsBundle\Infrastructure\Console\RecomputeCommand;
use Phil\ReviewsBundle\Infrastructure\Http\RateLimitRoutes;
use Phil\ReviewsBundle\Infrastructure\Http\ReviewsController;
use Phil\ReviewsBundle\Infrastructure\Http\ReviewsRequest;
use Phil\ReviewsBundle\Infrastructure\Http\SpamGuard;
use Phil\ReviewsBundle\Infrastructure\Security\RoleResponders;
use Phil\ReviewsBundle\Infrastructure\Security\SecurityCurrentReviewer;
use Phil\ReviewsBundle\Infrastructure\StructuredData\AggregateRatings;
use Phil\ReviewsBundle\Infrastructure\Twig\KitComponents;
use Phil\ReviewsBundle\Infrastructure\Twig\ReviewsExtension;
use Phil\ReviewsBundle\Tests\Unit\Fixture\Store;
use Phil\SpamProtectionBundle\Application\RefusalPolicy;
use Phil\SpamProtectionBundle\Application\SubmissionInspector;
use Phil\SpamProtectionBundle\Infrastructure\Cache\CacheUsedTokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface as CsrfTokenStorage;

#[CoversClass(SecurityCurrentReviewer::class)]
#[CoversClass(RoleResponders::class)]
#[CoversClass(SpamGuard::class)]
#[CoversClass(ReviewsRequest::class)]
#[CoversClass(RateLimitRoutes::class)]
#[CoversClass(RecomputeCommand::class)]
#[CoversClass(ReviewsExtension::class)]
#[CoversClass(KitComponents::class)]
final class PortsTest extends TestCase
{
    public function testTheCurrentReviewerIsTheSignedInUser(): void
    {
        $tokens = new TokenStorage();
        $reviewer = new SecurityCurrentReviewer($tokens);

        self::assertNull($reviewer->member());
        self::assertNull(new SecurityCurrentReviewer(null)->member());

        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('ada', null), 'main'));
        self::assertSame('ada', new SecurityCurrentReviewer($tokens)->member()?->value);

        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('ada@example.org', null), 'main'));
        $this->expectException(InvalidReview::class);
        $reviewer->member();
    }

    public function testTheRespondersAreThoseSecurityGrants(): void
    {
        $subject = SubjectRef::of('game', 'catan');
        $checker = new class implements AuthorizationCheckerInterface {
            /** @var list<array{mixed, mixed}> */
            public array $asked = [];

            public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                $this->asked[] = [$attribute, $subject];

                return 'ROLE_SHOP' === $attribute;
            }
        };

        self::assertFalse(new RoleResponders($checker)->mayRespond($subject, MemberId::fromString('ada')));
        self::assertSame([[RoleResponders::DEFAULT_ATTRIBUTE, $subject]], $checker->asked);
        self::assertTrue(new RoleResponders($checker, 'ROLE_SHOP')->mayRespond($subject, MemberId::fromString('ada')));
        self::assertFalse(new RoleResponders(null)->mayRespond($subject, MemberId::fromString('ada')));
    }

    public function testTheSpamGuardHoldsWhatTheInspectorRefuses(): void
    {
        $clock = new MockClock('2026-11-21 10:00:00 UTC');
        $inspector = SubmissionInspector::configured(str_repeat('s', 32), $clock, new CacheUsedTokens(new ArrayAdapter()), 2, 3600, 0, 2, ['casino'], false);
        $guard = new SpamGuard($inspector, new RefusalPolicy());

        $protection = $guard->protect();
        self::assertSame(0, $protection->difficulty);
        $clock->modify('+10 seconds');
        self::assertFalse($guard->refuses($this->submission($protection->token), ['Great game', 'Fun']));

        $second = $guard->protect();
        $clock->modify('+10 seconds');
        self::assertTrue($guard->refuses($this->submission($second->token, honeypot: 'http://spam.example'), ['Great game']));

        $third = $guard->protect();
        $clock->modify('+10 seconds');
        self::assertTrue($guard->refuses($this->submission($third->token), ['Best casino']));

        self::assertTrue($guard->refuses($this->submission($protection->token), ['Replayed']), 'A token is used once.');
        self::assertTrue($guard->refuses($this->submission(''), ['No token']));
    }

    public function testTheFormsComeFromThisSite(): void
    {
        $tokens = new CsrfTokenManager(new UriSafeTokenGenerator(), new class implements CsrfTokenStorage {
            /** @var array<string, string> */
            private array $values = [];

            public function getToken(string $tokenId): string
            {
                return $this->values[$tokenId] ?? throw new TokenNotFoundException();
            }

            public function setToken(string $tokenId, #[SensitiveParameter] string $token): void
            {
                $this->values[$tokenId] = $token;
            }

            public function hasToken(string $tokenId): bool
            {
                return isset($this->values[$tokenId]);
            }

            public function removeToken(string $tokenId): ?string
            {
                $token = $this->values[$tokenId] ?? null;
                unset($this->values[$tokenId]);

                return $token;
            }
        });
        $request = new ReviewsRequest($tokens);
        $token = $request->token();

        self::assertTrue($request->isTrusted(Request::create('/', 'POST', ['_token' => $token])));
        self::assertFalse($request->isTrusted(Request::create('/', 'POST', ['_token' => 'forged'])));
        self::assertFalse($request->isTrusted(Request::create('/', 'POST', ['_token' => $token], server: ['HTTP_SEC_FETCH_SITE' => 'cross-site'])));
        self::assertTrue($request->isTrusted(Request::create('/', 'POST', ['_token' => $token], server: ['HTTP_SEC_FETCH_SITE' => 'same-origin'])));
    }

    public function testTheFormsAreGivenTheirRateLimits(): void
    {
        $routes = new RateLimitRoutes(['reviews'], [['limiter' => 'votes', 'key' => 'user']]);
        $kernel = self::createStub(HttpKernelInterface::class);

        foreach ([
            ReviewsController::WRITE_ROUTE => ['reviews'],
            ReviewsController::VOTE_ROUTE => [['limiter' => 'votes', 'key' => 'user']],
            ReviewsController::RESPOND_ROUTE => [['limiter' => 'votes', 'key' => 'user']],
            ReviewsController::CONTEST_ROUTE => [['limiter' => 'votes', 'key' => 'user']],
        ] as $route => $rules) {
            $request = Request::create('/');
            $request->attributes->set('_route', $route);
            $routes(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
            self::assertSame($rules, $request->attributes->get(RateLimitRoutes::ATTRIBUTE), $route);
        }

        $page = Request::create('/');
        $page->attributes->set('_route', ReviewsController::PAGE_ROUTE);
        $routes(new RequestEvent($kernel, $page, HttpKernelInterface::MAIN_REQUEST));
        self::assertFalse($page->attributes->has(RateLimitRoutes::ATTRIBUTE));

        $own = Request::create('/');
        $own->attributes->add(['_route' => ReviewsController::WRITE_ROUTE, RateLimitRoutes::ATTRIBUTE => 'mine']);
        $routes(new RequestEvent($kernel, $own, HttpKernelInterface::MAIN_REQUEST));
        self::assertSame('mine', $own->attributes->get(RateLimitRoutes::ATTRIBUTE));

        $sub = Request::create('/');
        $sub->attributes->set('_route', ReviewsController::WRITE_ROUTE);
        $routes(new RequestEvent($kernel, $sub, HttpKernelInterface::SUB_REQUEST));
        self::assertFalse($sub->attributes->has(RateLimitRoutes::ATTRIBUTE));

        $none = Request::create('/');
        $none->attributes->set('_route', ReviewsController::WRITE_ROUTE);
        new RateLimitRoutes([], ['votes'])(new RequestEvent($kernel, $none, HttpKernelInterface::MAIN_REQUEST));
        self::assertFalse($none->attributes->has(RateLimitRoutes::ATTRIBUTE));
    }

    public function testTheCommandComputesTheRatingsAgain(): void
    {
        $store = new Store();
        $store->reviews->write(Store::game(), Store::member('ada'), new ReviewContent(4));
        $store->reviews->write(Store::game('azul'), Store::member('ada'), new ReviewContent(4));

        $command = new RecomputeCommand($store->ratings);

        [$code, $output] = $this->execute($command, 'game');
        self::assertSame(Command::SUCCESS, $code);
        self::assertStringContainsString('The ratings of 2 subjects were computed again.', $output);

        $store->reviews->write(SubjectRef::of('product', 'mug'), Store::member('ada'), new ReviewContent(4));
        [, $output] = $this->execute($command, 'product');
        self::assertStringContainsString('The ratings of one subject were computed again.', $output);

        [$code, $output] = $this->execute($command, null);
        self::assertSame(Command::SUCCESS, $code);
        self::assertStringContainsString('The ratings of 3 subjects were computed again.', $output);

        [$code, $output] = $this->execute($command, 'book');
        self::assertSame(Command::INVALID, $code);
        self::assertStringContainsString('"book" is not a type of subject the reviews declare.', $output);
    }

    public function testTheTwigFunctions(): void
    {
        $store = new Store();
        $store->reviews->write(Store::game(), Store::member('ada'), new ReviewContent(4));
        $store->reviews->write(Store::game(), Store::member('bob'), new ReviewContent(5));

        $extension = new ReviewsExtension(new KitComponents('kit/ui/'), new IdentifierDirectory(), $store->ratings, $store->types, new AggregateRatings($store->ratings, $store->types));

        self::assertSame('kit:ui:Rating', $extension->kit('Rating'));
        self::assertSame('Rating', new KitComponents()->name('Rating'));
        self::assertSame('ada', $extension->name(MemberId::fromString('ada')));
        self::assertNull($extension->name(null));
        self::assertSame(9, $extension->summary('game', 'catan')?->sum);
        self::assertNull($extension->summary('book', 'dune'));
        self::assertNull($extension->summary('game', ''));
        self::assertSame('game', $extension->type('game')?->name);
        self::assertNull($extension->type('book'));
        self::assertSame(4.5, $extension->aggregateRating('game', 'catan')?->get('ratingValue'));
        self::assertNull($extension->aggregateRating('game', ''));
        self::assertNull(new ReviewsExtension(new KitComponents(), new IdentifierDirectory(), $store->ratings, $store->types)->aggregateRating('game', 'catan'));
    }

    /**
     * @return array{int, string}
     */
    private function execute(RecomputeCommand $command, ?string $type): array
    {
        $output = new BufferedOutput();
        $code = $command(new SymfonyStyle(new ArrayInput([]), $output), $type);

        return [$code, preg_replace('/\s+/', ' ', $output->fetch()) ?? ''];
    }

    private function submission(string $token, string $honeypot = ''): Request
    {
        return Request::create('/', 'POST', [SpamGuard::HONEYPOT => $honeypot, SpamGuard::TOKEN => $token, SpamGuard::PROOF => '']);
    }
}
