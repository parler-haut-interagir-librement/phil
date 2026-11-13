<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Unit;

use Phil\Poll\Contract\CountingMethod;
use Phil\Poll\Counting\Borda;
use Phil\Poll\Counting\ChoiceCount;
use Phil\Poll\Counting\InstantRunoff;
use Phil\Poll\Counting\Schulze;
use Phil\Poll\CountingMethods;
use Phil\PollBundle\Application\Contract\PollPolicy;
use Phil\PollBundle\Application\DefaultPollPolicy;
use Phil\PollBundle\Application\Polls;
use Phil\PollBundle\Application\PollViews;
use Phil\PollBundle\Domain\Counting\MeekStv;
use Phil\PollBundle\Domain\Repository\PollRepository;
use Phil\PollBundle\Domain\Repository\VoteRepository;
use Phil\PollBundle\Infrastructure\DependencyInjection\PollServices;
use Phil\PollBundle\Infrastructure\Doctrine\DoctrinePollRepository;
use Phil\PollBundle\Infrastructure\Doctrine\DoctrineVoteRepository;
use Phil\PollBundle\Infrastructure\Memory\InMemoryPollRepository;
use Phil\PollBundle\Infrastructure\Memory\InMemoryVoteRepository;
use Phil\PollBundle\Infrastructure\Twig\PollTwig;
use Phil\PollBundle\PhilPollBundle;
use Phil\PollBundle\Tests\Unit\Fixture\DoctrineStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilPollBundle::class)]
#[CoversClass(PollServices::class)]
final class PhilPollBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_poll', $this->extension()->getAlias());
        self::assertSame(['service_container', DoctrinePollRepository::class, DoctrineVoteRepository::class, DefaultPollPolicy::class, ChoiceCount::class, InstantRunoff::class, Borda::class, Schulze::class, MeekStv::class, CountingMethods::class, Polls::class, PollViews::class, PollTwig::class], array_keys($container->getDefinitions()));
        self::assertEquals([new Reference('doctrine.orm.default_entity_manager')], $container->getDefinition(DoctrinePollRepository::class)->getArguments());
        self::assertEquals([new Reference('doctrine.orm.default_entity_manager')], $container->getDefinition(DoctrineVoteRepository::class)->getArguments());
        self::assertSame([DoctrinePollRepository::class, true], [(string) $container->getAlias(PollRepository::class), $container->getAlias(PollRepository::class)->isPublic()]);
        self::assertSame([DoctrineVoteRepository::class, true], [(string) $container->getAlias(VoteRepository::class), $container->getAlias(VoteRepository::class)->isPublic()]);
        self::assertSame(DefaultPollPolicy::class, (string) $container->getAlias(PollPolicy::class));
        self::assertFalse($container->getAlias(PollPolicy::class)->isPublic());

        foreach ([ChoiceCount::class, InstantRunoff::class, Borda::class, Schulze::class, MeekStv::class] as $method) {
            self::assertSame([[]], $container->getDefinition($method)->getTag('phil_poll.counting_method'), $method);
            self::assertSame($method, $container->getDefinition($method)->getClass());
        }

        self::assertSame('phil_poll.counting_method', PollServices::COUNTING_METHOD_TAG);
        self::assertEquals([new TaggedIteratorArgument('phil_poll.counting_method')], $container->getDefinition(CountingMethods::class)->getArguments());
        self::assertTrue($container->getAutoconfiguredInstanceof()[CountingMethod::class]->hasTag('phil_poll.counting_method'));
        self::assertEquals([
            new Reference(PollRepository::class),
            new Reference(VoteRepository::class),
            new Reference('clock'),
            new Reference(PollPolicy::class),
            new Reference(CountingMethods::class),
            new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            null,
            3600,
        ], $container->getDefinition(Polls::class)->getArguments());
        self::assertTrue($container->getDefinition(Polls::class)->isPublic());
        self::assertTrue($container->getDefinition(CountingMethods::class)->isPublic());
        self::assertFalse($container->getDefinition(Borda::class)->isPublic());
        self::assertEquals([new Reference(Polls::class), new Reference(PollPolicy::class), new Reference(CountingMethods::class), new Reference('clock')], $container->getDefinition(PollViews::class)->getArguments());
        self::assertTrue($container->getDefinition(PollViews::class)->isPublic());
        self::assertEquals([new Reference(PollViews::class)], $container->getDefinition(PollTwig::class)->getArguments());
        self::assertTrue($container->getDefinition(PollTwig::class)->isAutoconfigured());
        self::assertFalse($container->getDefinition(PollTwig::class)->isPublic());
        self::assertSame([], $container->getDefinition(DoctrinePollRepository::class)->getTags());
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load(['store' => 'memory', 'policy' => 'app.policy']);

        self::assertSame(['service_container', InMemoryPollRepository::class, InMemoryVoteRepository::class, ChoiceCount::class, InstantRunoff::class, Borda::class, Schulze::class, MeekStv::class, CountingMethods::class, Polls::class, PollViews::class, PollTwig::class], array_keys($container->getDefinitions()));
        self::assertSame([InMemoryPollRepository::class, true], [(string) $container->getAlias(PollRepository::class), $container->getAlias(PollRepository::class)->isPublic()]);
        self::assertSame([InMemoryVoteRepository::class, true], [(string) $container->getAlias(VoteRepository::class), $container->getAlias(VoteRepository::class)->isPublic()]);
        self::assertSame('app.policy', (string) $container->getAlias(PollPolicy::class));
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryPollRepository::class)->getTags());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryVoteRepository::class)->getTags());
        self::assertEquals([new Reference('doctrine.orm.votes_entity_manager')], $this->load(['store' => 'doctrine', 'entity_manager' => 'votes'])->getDefinition(DoctrineVoteRepository::class)->getArguments());
        self::assertEquals([new Reference('doctrine.orm.votes_entity_manager')], $this->load(['entity_manager' => 'votes'])->getDefinition(DoctrinePollRepository::class)->getArguments());

        $cached = $this->load(['results_cache' => ['pool' => 'cache.app', 'lifetime' => 60]])->getDefinition(Polls::class)->getArguments();
        self::assertEquals([new Reference('cache.app'), 60], \array_slice($cached, 6));
        self::assertSame(1, $this->load(['results_cache' => ['lifetime' => 1]])->getDefinition(Polls::class)->getArgument(7));
    }

    public function testABadConfigurationIsRefused(): void
    {
        foreach ([['store' => 'redis'], ['entity_manager' => ''], ['results_cache' => ['lifetime' => 0]]] as $config) {
            try {
                $this->load($config);
                self::fail((string) json_encode($config));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('phil_poll', $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testTheMappingIsPrepended(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineStub());
        $container->registerExtension($extension);

        $extension->prepend($container);

        self::assertSame([['orm' => ['mappings' => ['PhilPollBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname(__DIR__, 2).'/src/Domain/Model',
            'prefix' => 'Phil\PollBundle\Domain\Model',
        ]]]]], $container->getExtensionConfig('doctrine'));

        $bare = new ContainerBuilder();
        $bare->registerExtension($extension);

        $extension->prepend($bare);
        self::assertSame([[], []], [$bare->getExtensionConfig('doctrine'), $bare->getExtensionConfig('phil_poll')]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilPollBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
