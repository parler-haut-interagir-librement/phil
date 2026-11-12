<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Phil\ApiContract\Contract\OpenApiRule;
use Phil\ApiContract\Deprecation\ObservedDeprecations;
use Phil\ApiContract\OpenApi\ContractVerifier;
use Phil\ApiContractBundle\Infrastructure\Console\DeprecationsCommand;
use Phil\ApiContractBundle\Infrastructure\Console\VerifyCommand;
use Phil\ApiContractBundle\Infrastructure\DependencyInjection\CheckDeprecatedEndpoints;
use Phil\ApiContractBundle\Infrastructure\Deprecation\CacheObservedDeprecations;
use Phil\ApiContractBundle\Infrastructure\Doctrine\IdempotencySchemaListener;
use Phil\ApiContractBundle\Infrastructure\Http\CallerScopeResolver;
use Phil\ApiContractBundle\Infrastructure\Http\DeprecationListener;
use Phil\ApiContractBundle\Infrastructure\Http\IdempotencyListener;
use Phil\ApiContractBundle\Infrastructure\Http\IdempotencyScopeResolver;
use Phil\ApiContractBundle\Infrastructure\Http\ProblemDetailsListener;
use Phil\ApiContractBundle\Infrastructure\HttpClient\DeprecationObservingHttpClient;
use Phil\ApiContractBundle\Infrastructure\OpenApi\ContractVerifierFactory;
use Phil\ApiContractBundle\PhilApiContractBundle;
use Phil\Idempotency\Contract\IdempotencyStore;
use Phil\Idempotency\Store\CacheIdempotencyStore;
use Phil\Idempotency\Store\DbalIdempotencyStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilApiContractBundle::class)]
final class PhilApiContractBundleTest extends TestCase
{
    public function testTheServicesFollowTheConfiguration(): void
    {
        $container = $this->load([['problem_details' => ['paths' => ['^/api/']], 'idempotency' => ['ttl' => 60, 'reservation_ttl' => 20, 'replayed_headers' => ['Location'], 'connection' => 'api']]]);
        $clock = new Reference('clock');

        self::assertSame('phil_api_contract', $this->extension()->getAlias());
        self::assertSame(['service_container', ProblemDetailsListener::class, CallerScopeResolver::class, IdempotencyListener::class, DeprecationListener::class, CacheObservedDeprecations::class, DeprecationObservingHttpClient::class, ContractVerifier::class, DeprecationsCommand::class, VerifyCommand::class, DbalIdempotencyStore::class, IdempotencySchemaListener::class], array_keys($container->getDefinitions()));
        self::assertSame([['^/api/'], '%kernel.debug%'], $container->getDefinition(ProblemDetailsListener::class)->getArguments());
        self::assertSame([['event' => 'kernel.exception']], $container->getDefinition(ProblemDetailsListener::class)->getTag('kernel.event_listener'));
        self::assertEquals([new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(CallerScopeResolver::class)->getArguments());
        self::assertSame(CallerScopeResolver::class, (string) $container->getAlias(IdempotencyScopeResolver::class));
        self::assertEquals([new Reference(IdempotencyStore::class, ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference(IdempotencyScopeResolver::class), 60, $clock, ['Location'], 20], $container->getDefinition(IdempotencyListener::class)->getArguments());
        self::assertSame([['event' => 'kernel.controller', 'method' => 'onController', 'after' => [DeprecationListener::class, 'data_collector.request']], ['event' => 'kernel.response', 'method' => 'onResponse']], $container->getDefinition(IdempotencyListener::class)->getTag('kernel.event_listener'));
        self::assertSame([['event' => 'kernel.controller', 'method' => 'onController'], ['event' => 'kernel.response', 'method' => 'onResponse']], $container->getDefinition(DeprecationListener::class)->getTag('kernel.event_listener'));
        self::assertEquals([new Reference('cache.app', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(CacheObservedDeprecations::class)->getArguments());
        self::assertSame(CacheObservedDeprecations::class, (string) $container->getAlias(ObservedDeprecations::class));
        self::assertTrue($container->getAlias(ObservedDeprecations::class)->isPublic());

        $client = $container->getDefinition(DeprecationObservingHttpClient::class);
        self::assertSame(['http_client', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE], $client->getDecoratedService());
        self::assertEquals([new Reference('.inner'), new Reference(ObservedDeprecations::class), $clock, new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $client->getArguments());
        self::assertEquals([new Reference(ObservedDeprecations::class), $clock], $container->getDefinition(DeprecationsCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(DeprecationsCommand::class)->hasTag('console.command'));

        $connection = new Reference('doctrine.dbal.api_connection');
        self::assertEquals([$connection, $clock], $container->getDefinition(DbalIdempotencyStore::class)->getArguments());
        self::assertSame(DbalIdempotencyStore::class, (string) $container->getAlias(IdempotencyStore::class));
        self::assertTrue($container->getAlias(IdempotencyStore::class)->isPublic());
        self::assertEquals([$connection], $container->getDefinition(IdempotencySchemaListener::class)->getArguments());
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'api']], $container->getDefinition(IdempotencySchemaListener::class)->getTag('doctrine.event_listener'));
    }

    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame([[], '%kernel.debug%'], $container->getDefinition(ProblemDetailsListener::class)->getArguments());
        $listener = $container->getDefinition(IdempotencyListener::class);
        self::assertSame([86_400, ['Content-Type', 'Location'], 300], [$listener->getArgument(2), $listener->getArgument(4), $listener->getArgument(5)]);
        self::assertEquals([new Reference('doctrine.dbal.default_connection'), new Reference('clock')], $container->getDefinition(DbalIdempotencyStore::class)->getArguments());

        $without = $this->load([['idempotency' => ['connection' => null]]]);
        self::assertFalse($without->hasDefinition(DbalIdempotencyStore::class));
        self::assertFalse($without->hasAlias(IdempotencyStore::class));
        self::assertFalse($without->hasDefinition(IdempotencySchemaListener::class));
    }

    public function testTheVerifierAndItsCommandFollowTheConfiguration(): void
    {
        $container = $this->load([['verification' => ['document' => '%kernel.project_dir%/openapi.yaml', 'level' => 'strict', 'fail_on' => 'warning', 'skip' => ['operation-description']]]]);

        $verifier = $container->getDefinition(ContractVerifier::class);
        self::assertSame([ContractVerifierFactory::class, 'create'], $verifier->getFactory());
        self::assertEquals([new TaggedIteratorArgument(PhilApiContractBundle::RULE_TAG), ['operation-description']], $verifier->getArguments());
        self::assertTrue($verifier->isPublic());
        self::assertSame('phil_api_contract.openapi_rule', PhilApiContractBundle::RULE_TAG);
        self::assertSame([PhilApiContractBundle::RULE_TAG => [[]]], $container->getAutoconfiguredInstanceof()[OpenApiRule::class]->getTags());
        self::assertEquals([new Reference(ContractVerifier::class), '%kernel.project_dir%/openapi.yaml', 'strict', 'warning'], $container->getDefinition(VerifyCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(VerifyCommand::class)->hasTag('console.command'));

        $defaults = $this->load([]);
        self::assertSame([], $defaults->getDefinition(ContractVerifier::class)->getArgument(1));
        self::assertSame([null, 'recommended', 'error'], \array_slice($defaults->getDefinition(VerifyCommand::class)->getArguments(), 1));
    }

    public function testEveryLevelAndThresholdIsAccepted(): void
    {
        foreach ([['level', 'minimal', 1], ['level', 'recommended', 1], ['level', 'strict', 1], ['fail_on', 'error', 2], ['fail_on', 'warning', 2]] as [$option, $value, $position]) {
            $container = $this->load([['verification' => [$option => $value]]]);

            self::assertSame($value, $container->getDefinition(VerifyCommand::class)->getArgument($position + 1));
        }
    }

    public function testAnUnknownLevelOrThresholdIsRefused(): void
    {
        foreach ([['level' => 'lenient'], ['fail_on' => 'notice']] as $verification) {
            try {
                $this->load([['verification' => $verification]]);
                self::fail((string) json_encode($verification));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('is not allowed', $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testACachePoolKeepsTheKeysInsteadOfTheConnection(): void
    {
        $container = $this->load([['idempotency' => ['cache_pool' => 'cache.idempotency']]]);

        self::assertEquals([new Reference('cache.idempotency'), new Reference('lock.factory'), new Reference('clock')], $container->getDefinition(CacheIdempotencyStore::class)->getArguments());
        self::assertSame(CacheIdempotencyStore::class, (string) $container->getAlias(IdempotencyStore::class));
        self::assertTrue($container->getAlias(IdempotencyStore::class)->isPublic());
        self::assertFalse($container->hasDefinition(DbalIdempotencyStore::class));
        self::assertFalse($container->hasDefinition(IdempotencySchemaListener::class));

        $locked = $this->load([['idempotency' => ['cache_pool' => 'cache.idempotency', 'lock_factory' => 'lock.idempotency.factory']]]);
        self::assertEquals(new Reference('lock.idempotency.factory'), $locked->getDefinition(CacheIdempotencyStore::class)->getArgument(1));
    }

    public function testTheDeprecatedEndpointsAreCheckedWhenTheContainerIsCompiled(): void
    {
        $container = new ContainerBuilder();
        new PhilApiContractBundle()->build($container);

        $passes = array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof CheckDeprecatedEndpoints);
        self::assertCount(1, $passes);
    }

    public function testWithoutDoctrineTheKeysAreKeptElsewhere(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);

        $without = new ContainerBuilder();
        $without->registerExtension($extension);

        $extension->prepend($without);
        self::assertSame([['idempotency' => ['connection' => null]]], $without->getExtensionConfig('phil_api_contract'));

        $with = new ContainerBuilder();
        $with->registerExtension(new DoctrineExtension());
        $with->registerExtension($extension);

        $extension->prepend($with);
        self::assertSame([], $with->getExtensionConfig('phil_api_contract'));
    }

    public function testAKeyWithoutLifetimeIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('Should be greater than or equal to 1');

        $this->load([['idempotency' => ['ttl' => 0]]]);
    }

    public function testAReservationWithoutLifetimeIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('Should be greater than or equal to 1');

        $this->load([['idempotency' => ['reservation_ttl' => 0]]]);
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
        $extension = new PhilApiContractBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
