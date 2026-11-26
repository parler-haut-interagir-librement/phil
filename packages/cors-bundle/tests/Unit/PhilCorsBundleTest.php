<?php

declare(strict_types=1);

namespace Phil\CorsBundle\Tests\Unit;

use Phil\CorsBundle\Application\CorsHeaders;
use Phil\CorsBundle\Application\PolicyResolver;
use Phil\CorsBundle\Domain\CorsPolicy;
use Phil\CorsBundle\Domain\Exception\InvalidCorsPolicy;
use Phil\CorsBundle\Infrastructure\EventListener\CorsListener;
use Phil\CorsBundle\Infrastructure\PathPolicyResolver;
use Phil\CorsBundle\PhilCorsBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelEvents;

#[CoversClass(PhilCorsBundle::class)]
final class PhilCorsBundleTest extends TestCase
{
    public function testTheAlias(): void
    {
        self::assertSame('phil_cors', $this->extension()->getAlias());
    }

    public function testTheServicesAndTheListenerTags(): void
    {
        $container = $this->load([]);

        self::assertSame(PathPolicyResolver::class, $container->getDefinition('phil_cors.path_policy_resolver')->getClass());
        self::assertSame('phil_cors.path_policy_resolver', (string) $container->getAlias(PolicyResolver::class));
        self::assertSame(CorsHeaders::class, $container->getDefinition('phil_cors.headers')->getClass());
        self::assertSame([
            ['event' => KernelEvents::REQUEST, 'method' => 'onKernelRequest', 'priority' => 250],
            ['event' => KernelEvents::RESPONSE, 'method' => 'onKernelResponse'],
        ], $container->getDefinition('phil_cors.listener')->getTag('kernel.event_listener'));
        self::assertSame(CorsListener::class, $container->getDefinition('phil_cors.listener')->getClass());
        $arguments = $container->getDefinition('phil_cors.listener')->getArguments();
        self::assertEquals([new Reference(PolicyResolver::class), new Reference('phil_cors.headers')], $arguments);
    }

    public function testDisabledRegistersNothing(): void
    {
        $container = $this->load([['enabled' => false, 'paths' => ['^/api/' => ['allow_origins' => '*']]]]);

        self::assertFalse($container->has('phil_cors.listener'));
        self::assertFalse($container->has('phil_cors.headers'));
        self::assertFalse($container->has(PolicyResolver::class));
    }

    public function testThePathsKeepTheirOrderTheirKeysAndTheDefaults(): void
    {
        $resolver = $this->resolver([['paths' => [
            '^/api-v2/' => ['allow_origins' => 'https://a.test'],
            '^/api/' => ['allow_origins' => ['*'], 'allow_methods' => 'PUT', 'max_age' => 60],
        ]]]);

        $first = $resolver->resolve(Request::create('/api-v2/x'));
        self::assertInstanceOf(CorsPolicy::class, $first);
        self::assertTrue($first->allowsOrigin('https://a.test'));
        self::assertFalse($first->allowsOrigin('https://b.test'));
        self::assertSame(['GET', 'HEAD', 'POST'], $first->methods);
        self::assertSame([], $first->exposeHeaders);
        self::assertFalse($first->allowCredentials);
        self::assertNull($first->maxAge);
        self::assertFalse($first->allowPrivateNetwork);

        $second = $resolver->resolve(Request::create('/api/x'));
        self::assertInstanceOf(CorsPolicy::class, $second);
        self::assertSame(['PUT'], $second->methods);
        self::assertSame(60, $second->maxAge);
        self::assertNull($resolver->resolve(Request::create('/x')));
    }

    public function testEveryOptionReachesThePolicy(): void
    {
        $policy = $this->resolver([['paths' => ['^/' => [
            'allow_origin_patterns' => ['^https://[a-z]+\.test$'],
            'allow_headers' => ['Authorization'],
            'expose_headers' => ['ETag'],
            'allow_credentials' => true,
            'allow_private_network' => true,
        ]]]])->resolve(Request::create('/'));

        self::assertInstanceOf(CorsPolicy::class, $policy);
        self::assertTrue($policy->allowsOrigin('https://b.test'));
        self::assertSame(['Authorization'], $policy->allowHeaders);
        self::assertSame(['ETag'], $policy->exposeHeaders);
        self::assertTrue($policy->allowCredentials);
        self::assertTrue($policy->allowPrivateNetwork);
    }

    public function testAPathThatIsANumberIsAPattern(): void
    {
        $resolver = $this->resolver([['paths' => ['2' => ['allow_origins' => 'https://a.test']]]]);

        self::assertInstanceOf(CorsPolicy::class, $resolver->resolve(Request::create('/v2/x')));
        self::assertNull($resolver->resolve(Request::create('/v1/x')));
    }

    public function testAWildcardWithCredentialsStopsTheBuild(): void
    {
        try {
            $this->load([['paths' => ['^/api/' => ['allow_origins' => '*', 'allow_credentials' => true]]]]);
            self::fail('The configuration was accepted.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertSame('phil_cors.paths."^/api/": allow_origins is "*" with allow_credentials: browsers refuse a wildcard with credentials, list the values.', $invalidConfigurationException->getMessage());
            self::assertInstanceOf(InvalidCorsPolicy::class, $invalidConfigurationException->getPrevious());
        }
    }

    public function testAPatternThatDoesNotCompileStopsTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_cors.paths."^/api/(": The path pattern "^/api/(" is not a regular expression that compiles.');

        $this->load([['paths' => ['^/api/(' => ['allow_origins' => 'https://a.test']]]]);
    }

    public function testAMaxAgeAboveADayStopsTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([['paths' => ['^/' => ['allow_origins' => 'https://a.test', 'max_age' => 86401]]]]);
    }

    public function testAMaxAgeOfADayIsAccepted(): void
    {
        $policy = $this->resolver([['paths' => ['^/' => ['allow_origins' => 'https://a.test', 'max_age' => 86400]]]])->resolve(Request::create('/'));

        self::assertSame(86400, $policy?->maxAge);
    }

    public function testANegativeMaxAgeStopsTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([['paths' => ['^/' => ['allow_origins' => 'https://a.test', 'max_age' => -1]]]]);
    }

    public function testAMaxAgeOfZeroIsAccepted(): void
    {
        $policy = $this->resolver([['paths' => ['^/' => ['allow_origins' => 'https://a.test', 'max_age' => 0]]]])->resolve(Request::create('/'));

        self::assertSame(0, $policy?->maxAge);
    }

    public function testANegativeMaxAgeIsRefusedByTheConfigurationTree(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('max_age');

        $this->processed(['max_age' => -1]);
    }

    public function testAMaxAgeOfZeroPassesTheConfigurationTree(): void
    {
        self::assertSame(0, $this->processed(['max_age' => 0])['paths']['^/']['max_age']);
    }

    /**
     * @param array<string, mixed> $policy
     *
     * @return array{paths: array<string, array<string, mixed>>}
     */
    private function processed(array $policy): array
    {
        $extension = $this->extension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        /** @var array{paths: array<string, array<string, mixed>>} */
        return new Processor()->process($configuration->getConfigTreeBuilder()->buildTree(), [['paths' => ['^/' => ['allow_origins' => 'https://a.test'] + $policy]]]);
    }

    public function testAnEmptyValueStopsTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('"" is not an origin');

        $this->load([['paths' => ['^/' => ['allow_origins' => ['']]]]]);
    }

    public function testANumberIsNoOrigin(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([['paths' => ['^/' => ['allow_origins' => [42]]]]]);
    }

    public function testAValueFromTheEnvironmentIsCheckedWhenTheResolverIsBuilt(): void
    {
        $resolver = $this->fromEnvironment('^https://[a-z]+\.test$')->get('phil_cors.path_policy_resolver');
        self::assertInstanceOf(PolicyResolver::class, $resolver);

        self::assertTrue($resolver->resolve(Request::create('/api/x'))?->allowsOrigin('https://ab.test'));

        $container = $this->fromEnvironment('https://ab.test');

        $this->expectException(InvalidCorsPolicy::class);
        $this->expectExceptionMessageIsOrContains('The origin pattern "https://ab.test" is not anchored');

        $container->get('phil_cors.path_policy_resolver');
    }

    private function fromEnvironment(string $pattern): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('env(PHIL_CORS_TEST_PATTERN)', $pattern);
        $container->registerExtension($this->extension());
        $container->loadFromExtension('phil_cors', ['paths' => ['^/api/' => ['allow_origin_patterns' => ['%env(PHIL_CORS_TEST_PATTERN)%']]]]);
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->getDefinition('phil_cors.path_policy_resolver')->setPublic(true);
            }
        });
        $container->compile(true);

        return $container;
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function resolver(array $configs): PolicyResolver
    {
        $container = $this->load($configs);
        $container->getDefinition('phil_cors.path_policy_resolver')->setPublic(true);
        $container->compile(true);
        $resolver = $container->get('phil_cors.path_policy_resolver');
        self::assertInstanceOf(PolicyResolver::class, $resolver);

        return $resolver;
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilCorsBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
