<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Infrastructure;

use LogicException;
use Phil\RateLimitBundle\Application\Exception\TooManyRequests;
use Phil\RateLimitBundle\Application\KeyResolvers;
use Phil\RateLimitBundle\Application\Limiters;
use Phil\RateLimitBundle\Application\RateLimitEnforcer;
use Phil\RateLimitBundle\Domain\Attribute\RateLimit;
use Phil\RateLimitBundle\Infrastructure\DependencyInjection\LimitersPass;
use Phil\RateLimitBundle\Infrastructure\DependencyInjection\RateLimitServices;
use Phil\RateLimitBundle\Infrastructure\Http\RateLimitCacheWarmer;
use Phil\RateLimitBundle\PhilRateLimitBundle;
use Phil\RateLimitBundle\Tests\Unit\Fixture\HeaderKey;
use Phil\RateLimitBundle\Tests\Unit\Fixture\LimitedController;
use Phil\RateLimitBundle\Tests\Unit\Fixture\ScriptedLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(LimitersPass::class)]
final class LimitersPassTest extends TestCase
{
    public function testTheLimitersAndStrategiesAreCollected(): void
    {
        $container = $this->container([
            'limiters' => [
                'api' => ['policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 minute', 'key' => 'account'],
                'own' => ['policy' => 'no_limit', 'key' => 'app.key'],
            ],
            'forms' => ['contact' => [['limiter' => 'external', 'key' => 'form.key']]],
        ], static function (ContainerBuilder $container): void {
            $container->register('app.scripted', ScriptedLimiter::class)->setArguments([1])->addTag(RateLimitServices::LIMITER_TAG, ['name' => 'external', 'key' => 'user', 'amount' => 1]);
            $container->register('app.tagged', HeaderKey::class)->addTag(RateLimitServices::KEY_TAG, ['strategy' => 'account']);
            $container->register('app.key', HeaderKey::class);
            $container->register('form.key', HeaderKey::class);
            $container->register('app.autoconfigured', HeaderKey::class)->addTag(RateLimitServices::KEY_TAG);
        });
        $container->compile(true);

        self::assertFalse($container->hasParameter(RateLimitServices::REQUIRED_PARAMETER));
        self::assertFalse($container->hasParameter(RateLimitServices::FORMS_PARAMETER));

        $enforcer = $container->get(RateLimitEnforcer::class);
        self::assertInstanceOf(RateLimitEnforcer::class, $enforcer);

        $request = Request::create('/');
        $request->headers->set('X-Account', 'alice');
        self::assertSame(1, $enforcer->enforce($request, [new RateLimit('api')])?->remaining);
        self::assertSame(0, $enforcer->enforce($request, [new RateLimit('api', key: 'app.autoconfigured')])?->remaining);
        self::assertNull($enforcer->enforce($request, [new RateLimit('own')])?->limit);
        self::assertSame(0, $enforcer->enforce($request, [new RateLimit('external', key: 'form.key')])?->remaining);

        $this->expectException(TooManyRequests::class);
        $enforcer->enforce($request, [new RateLimit('api')]);
    }

    public function testTheLocatorsListTheirNames(): void
    {
        $container = $this->container(['limiters' => ['api' => ['policy' => 'no_limit']]]);
        new LimitersPass()->process($container);

        $limiters = $container->getDefinition(Limiters::class)->getArguments();
        self::assertSame(['api' => ['key' => 'ip', 'amount' => 1, 'observe' => false]], $limiters[1]);
        self::assertSame(['ip', 'user', 'user_or_ip', 'ip_route'], $container->getDefinition(KeyResolvers::class)->getArgument(1));
    }

    public function testTheWarmerLearnsTheControllersWhoseIdIsNotAClass(): void
    {
        $container = $this->container([], static function (ContainerBuilder $container): void {
            $container->register('app.limited', LimitedController::class)->addTag('controller.service_arguments');
            $container->register(LimitedController::class, LimitedController::class)->addTag('controller.service_arguments');
            $container->register('app.classless')->addTag('controller.service_arguments');
            $container->register('app.again', LimitedController::class)->addTag('controller.service_arguments');
        });
        new LimitersPass()->process($container);

        self::assertSame(['app.limited' => LimitedController::class, 'app.again' => LimitedController::class], $container->getDefinition(RateLimitCacheWarmer::class)->getArgument(3));
    }

    public function testWhatALimiterNeedsMustExist(): void
    {
        $cases = [
            'phil_rate_limit.limiters.api (its cache pool) needs the service "cache.missing", which is not defined: declare it, or name another in the configuration.' => ['cache_pool' => 'cache.missing', 'limiters' => ['api' => ['policy' => 'no_limit']]],
            'phil_rate_limit.limiters.api (its lock factory) needs the service "lock.factory"' => ['lock_factory' => 'lock.factory', 'limiters' => ['api' => ['policy' => 'no_limit']]],
            'phil_rate_limit.limiters.api.storage needs the service "app.storage"' => ['limiters' => ['api' => ['policy' => 'no_limit', 'storage' => 'app.storage']]],
            'phil_rate_limit.limiters.api.service needs the service "limiter.api"' => ['limiters' => ['api' => ['service' => 'limiter.api']]],
            'The key strategy "nobody" is neither ip, user, user_or_ip, ip_route nor a service.' => ['limiters' => ['api' => ['policy' => 'no_limit', 'key' => 'nobody']]],
            'The form "contact" is limited by "nope", which no limiter is named.' => ['forms' => ['contact' => 'nope']],
            'The key strategy "ghost" is neither' => ['limiters' => ['api' => ['policy' => 'no_limit']], 'forms' => ['contact' => [['limiter' => 'api'], ['limiter' => 'api', 'key' => 'ghost']]]],
            'The service "app.not_a_key" is a stdClass, which does not implement Phil\RateLimitBundle\Application\Contract\KeyResolver.' => ['limiters' => ['api' => ['policy' => 'no_limit', 'key' => 'app.not_a_key']]],
        ];

        foreach ($cases as $message => $config) {
            $container = $this->container($config, static function (ContainerBuilder $container): void {
                $container->register('app.not_a_key', stdClass::class);
            });

            try {
                $container->compile(true);
                self::fail($message);
            } catch (LogicException $logicException) {
                self::assertStringContainsString($message, $logicException->getMessage());
            }
        }
    }

    public function testATaggedLimiterIsChecked(): void
    {
        $cases = [
            'The service "a" is tagged "phil_rate_limit.limiter" without a name of 1 to 64 letters, digits, "_", "." and "-".' => [['a', []]],
            'The service "a" is tagged "phil_rate_limit.limiter" without a name' => [['a', ['name' => 'a b']]],
            'The service "a" is tagged "phil_rate_limit.limiter" without a name ' => [['a', ['name' => 3]]],
            'Two limiters are named "x": the services "a" and "b".' => [['a', ['name' => 'x']], ['b', ['name' => 'x']]],
            'The limiter "x" takes a key strategy by name, an amount from one and observe as a boolean.' => [['a', ['name' => 'x', 'amount' => 0]]],
            'The limiter "x" takes a key strategy by name, an amount from one and observe as a boolean. ' => [['a', ['name' => 'x', 'amount' => '2']]],
            'The limiter "x" takes a key strategy by name, an amount from one and observe as a boolean.  ' => [['a', ['name' => 'x', 'key' => 3]]],
            'The limiter "x" takes a key strategy by name, an amount from one and observe as a boolean.   ' => [['a', ['name' => 'x', 'observe' => 'yes']]],
            'The service "a" is a stdClass, which does not implement Phil\RateLimitBundle\Application\Contract\Limiter.' => [['a', ['name' => 'x'], stdClass::class]],
        ];

        foreach ($cases as $message => $services) {
            $container = $this->container([], static function (ContainerBuilder $container) use ($services): void {
                foreach ($services as $service) {
                    $container->register($service[0], $service[2] ?? ScriptedLimiter::class)->addTag(RateLimitServices::LIMITER_TAG, $service[1]);
                }
            });

            try {
                $container->compile(true);
                self::fail($message);
            } catch (LogicException $logicException) {
                self::assertStringContainsString(trim($message), $logicException->getMessage());
            }
        }
    }

    public function testATaggedLimiterOfAnUnknownClassIsTrusted(): void
    {
        $container = $this->container([], static function (ContainerBuilder $container): void {
            $container->register('a', '%app.class%')->addTag(RateLimitServices::LIMITER_TAG, ['name' => 'x']);
            $container->setParameter('app.class', ScriptedLimiter::class);
            $container->register('b', 'App\\NotLoaded')->addTag(RateLimitServices::LIMITER_TAG, ['name' => 'y']);
            $container->register('c')->setFactory([self::class, 'limiter'])->addTag(RateLimitServices::LIMITER_TAG, ['name' => 'z']);
        });
        new LimitersPass()->process($container);

        self::assertSame(['x' => ['key' => 'ip', 'amount' => 1, 'observe' => false], 'y' => ['key' => 'ip', 'amount' => 1, 'observe' => false], 'z' => ['key' => 'ip', 'amount' => 1, 'observe' => false]], $container->getDefinition(Limiters::class)->getArgument(1));
    }

    public function testALimiterInObservationIsKnown(): void
    {
        $container = $this->container(['limiters' => ['watched' => ['policy' => 'no_limit', 'observe' => true]]], static function (ContainerBuilder $container): void {
            $container->register('app.scripted', ScriptedLimiter::class)->addTag(RateLimitServices::LIMITER_TAG, ['name' => 'tagged', 'observe' => true]);
        });
        new LimitersPass()->process($container);

        self::assertSame([
            'watched' => ['key' => 'ip', 'amount' => 1, 'observe' => true],
            'tagged' => ['key' => 'ip', 'amount' => 1, 'observe' => true],
        ], $container->getDefinition(Limiters::class)->getArgument(1));
    }

    public function testNothingIsDoneWithoutTheBundle(): void
    {
        $container = new ContainerBuilder();
        new LimitersPass()->process($container);

        self::assertFalse($container->has(Limiters::class));
    }

    public static function limiter(): ScriptedLimiter
    {
        return new ScriptedLimiter();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function container(array $config, ?callable $services = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.secret', 'test');
        $container->setParameter('kernel.environment', 'test');
        $container->register('cache.app', ArrayAdapter::class);
        $container->register('request_stack', RequestStack::class);

        $bundle = new PhilRateLimitBundle();
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);
        $extension->load([$config], $container);
        $bundle->build($container);

        if (null !== $services) {
            $services($container);
        }

        return $container;
    }
}
