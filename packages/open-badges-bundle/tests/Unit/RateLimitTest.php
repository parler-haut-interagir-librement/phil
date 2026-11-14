<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Tests\Unit;

use LogicException;
use Phil\OpenBadgesBundle\Infrastructure\DependencyInjection\VerificationRateLimitPass;
use Phil\OpenBadgesBundle\Infrastructure\Http\VerificationController;
use Phil\OpenBadgesBundle\Infrastructure\Http\VerificationRateLimit;
use Phil\OpenBadgesBundle\PhilOpenBadgesBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * The limit on the checks of the verification page: the rule given to the request, and the build that refuses a
 * limiter `phil/rate-limit-bundle` does not declare.
 */
#[CoversClass(VerificationRateLimit::class)]
#[CoversClass(VerificationRateLimitPass::class)]
final class RateLimitTest extends TestCase
{
    public function testAPostToThePageCountsAgainstTheLimiter(): void
    {
        $request = $this->request('POST');
        $this->listen($request);

        self::assertSame('checks', $request->attributes->get('_rate_limit'));
        self::assertSame('_rate_limit', VerificationRateLimit::ATTRIBUTE);
    }

    public function testShowingThePageAnotherRouteASubRequestOrARuleOfTheRouteAreLeftAlone(): void
    {
        $get = $this->request('GET');
        $this->listen($get);
        self::assertFalse($get->attributes->has('_rate_limit'));

        $other = $this->request('POST', 'phil_open_badges_issuer');
        $this->listen($other);
        self::assertFalse($other->attributes->has('_rate_limit'));

        $sub = $this->request('POST');
        $this->listen($sub, HttpKernelInterface::SUB_REQUEST);
        self::assertFalse($sub->attributes->has('_rate_limit'));

        $ruled = $this->request('POST');
        $ruled->attributes->set('_rate_limit', ['api']);
        $this->listen($ruled);
        self::assertSame(['api'], $ruled->attributes->get('_rate_limit'));
    }

    public function testTheBuildAcceptsALimiterTheRateLimitBundleDeclares(): void
    {
        $container = $this->container('checks');
        $container->register('phil_rate_limit.limiter.api', stdClass::class)->addTag('phil_rate_limit.limiter', ['name' => 'api']);
        $container->register('app.quota', stdClass::class)->addTag('phil_rate_limit.limiter', ['name' => 'other'])->addTag('phil_rate_limit.limiter', ['name' => 'checks']);

        new VerificationRateLimitPass()->process($container);
        self::assertSame('phil_rate_limit.limiter', VerificationRateLimitPass::LIMITER_TAG);
    }

    public function testTheBuildRefusesALimiterNothingDeclares(): void
    {
        $container = $this->container('checks');
        $container->register('phil_rate_limit.limiter.login', stdClass::class)->addTag('phil_rate_limit.limiter', ['name' => 'login']);
        $container->register('phil_rate_limit.limiter.api', stdClass::class)->addTag('phil_rate_limit.limiter', ['name' => 'api']);
        $container->register('app.unnamed', stdClass::class)->addTag('phil_rate_limit.limiter');

        $this->expectExceptionObject(new LogicException('phil_open_badges.verification.rate_limit names the limiter "checks", which phil/rate-limit-bundle does not declare; it declares "api", "login".'));

        new VerificationRateLimitPass()->process($container);
    }

    public function testTheBuildSaysWhenNoLimiterIsDeclared(): void
    {
        $this->expectExceptionObject(new LogicException('phil_open_badges.verification.rate_limit names the limiter "checks", which phil/rate-limit-bundle does not declare; it declares none.'));

        new VerificationRateLimitPass()->process($this->container('checks'));
    }

    public function testWithoutALimitThereIsNothingToCheck(): void
    {
        $container = new ContainerBuilder();
        new VerificationRateLimitPass()->process($container);

        self::assertFalse($container->hasDefinition(VerificationRateLimit::class));
    }

    public function testTheBundleAddsThePass(): void
    {
        $container = new ContainerBuilder();
        new PhilOpenBadgesBundle()->build($container);

        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof VerificationRateLimitPass));
    }

    private function container(string $limiter): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(VerificationRateLimit::class, VerificationRateLimit::class)->setArguments([$limiter]);

        return $container;
    }

    private function request(string $method, string $route = VerificationController::ROUTE): Request
    {
        $request = Request::create('https://example.com/open-badges/verify', $method);
        $request->attributes->set('_route', $route);

        return $request;
    }

    private function listen(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): void
    {
        new VerificationRateLimit('checks')->onKernelRequest(new RequestEvent(self::createStub(HttpKernelInterface::class), $request, $type));
    }
}
