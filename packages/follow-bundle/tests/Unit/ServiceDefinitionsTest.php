<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Unit;

use Phil\FollowBundle\Application\Activities;
use Phil\FollowBundle\Application\AutoFollow;
use Phil\FollowBundle\Application\Contract\FollowAccess;
use Phil\FollowBundle\Application\Contract\FollowApproval;
use Phil\FollowBundle\Application\Contract\SubjectParents;
use Phil\FollowBundle\Application\Followers;
use Phil\FollowBundle\Application\FollowRequests;
use Phil\FollowBundle\Application\Follows;
use Phil\FollowBundle\Application\FollowTypes;
use Phil\FollowBundle\Application\Handler\FanOutActivityHandler;
use Phil\FollowBundle\Application\Handler\PurgeFollowRecordsHandler;
use Phil\FollowBundle\Application\Message\PurgeFollowRecords;
use Phil\FollowBundle\Application\Mutuals;
use Phil\FollowBundle\Application\SubjectChain;
use Phil\FollowBundle\Domain\Contract\ActivityRepository;
use Phil\FollowBundle\Domain\Contract\DeliveryRepository;
use Phil\FollowBundle\Domain\Contract\FollowerVisibilityRepository;
use Phil\FollowBundle\Domain\Contract\FollowRepository;
use Phil\FollowBundle\Domain\Contract\FollowRequestRepository;
use Phil\FollowBundle\Domain\Contract\MutualRepository;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\FollowBundle\Infrastructure\DependencyInjection\FollowServices;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalActivityRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalDeliveryRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalFollowerVisibilityRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalFollowRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalFollowRequestRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalMutualRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\FollowSchemaListener;
use Phil\FollowBundle\Infrastructure\Http\FollowController;
use Phil\FollowBundle\Infrastructure\Http\FollowGuard;
use Phil\FollowBundle\Infrastructure\Privacy\FollowPrivacy;
use Phil\FollowBundle\PhilFollowBundle;
use Phil\NotificationsBundle\Application\Notifications;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The container the bundle builds: each service with the arguments it is given in the order its constructor takes them,
 * the services an application reads public, and the limits of each option.
 */
#[CoversClass(PhilFollowBundle::class)]
#[CoversClass(FollowServices::class)]
final class ServiceDefinitionsTest extends TestCase
{
    public function testEachRepositoryReadsTheConnectionAndAnswersToItsPort(): void
    {
        $container = $this->load([]);
        $dbal = '@doctrine.dbal.default_connection';

        foreach ([
            FollowRepository::class => DbalFollowRepository::class,
            FollowRequestRepository::class => DbalFollowRequestRepository::class,
            MutualRepository::class => DbalMutualRepository::class,
            ActivityRepository::class => DbalActivityRepository::class,
            DeliveryRepository::class => DbalDeliveryRepository::class,
            FollowerVisibilityRepository::class => DbalFollowerVisibilityRepository::class,
        ] as $port => $adapter) {
            self::assertSame([$dbal], $this->arguments($container, $adapter), $adapter);
            self::assertSame($adapter, (string) $container->getAlias($port), $port);
        }

        $listener = $container->getDefinition(FollowSchemaListener::class);
        self::assertSame([$dbal], $this->arguments($container, FollowSchemaListener::class));
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'default']], $listener->getTag('doctrine.event_listener'));
    }

    public function testTheApplicationServicesAreWiredInTheOrderOfTheirConstructors(): void
    {
        $container = $this->load([], ['twig']);
        $notifications = '@'.Notifications::class;
        $types = '@'.FollowTypes::class;

        self::assertSame(['follow.activity', 'follow.activity_quiet', 'follow.requested', 'follow.accepted', 'follow.followed'], $this->arguments($container, FollowTypes::class));
        self::assertSame(['@'.SubjectParents::class, 5], $this->arguments($container, SubjectChain::class));
        self::assertSame([
            '@'.FollowRepository::class, '@'.FollowRequestRepository::class, '@'.DeliveryRepository::class, '@'.FollowApproval::class, '@'.FollowAccess::class,
            '@'.SubjectChain::class, $notifications, '@clock', $types, 'member',
        ], $this->arguments($container, Follows::class));
        self::assertSame([
            '@'.FollowRequestRepository::class, '@'.FollowRepository::class, '@'.MutualRepository::class, $notifications, '@clock', $types, 'member', true,
        ], $this->arguments($container, FollowRequests::class));
        self::assertSame([
            '@'.MutualRepository::class, '@'.FollowRequestRepository::class, '@'.FollowRequests::class, $notifications, '@clock', false, $types, 'member',
        ], $this->arguments($container, Mutuals::class));
        self::assertSame(['@'.FollowRepository::class, '@'.FollowerVisibilityRepository::class, false, 'member'], $this->arguments($container, Followers::class));
        self::assertSame([
            '@'.FollowRepository::class, '@'.FollowApproval::class, '@'.FollowAccess::class, '@clock', false, false, FollowLevel::Watching, 'member',
        ], $this->arguments($container, AutoFollow::class));
        self::assertSame([
            '@'.ActivityRepository::class, '@'.FollowRepository::class, '@'.DeliveryRepository::class, '@'.SubjectChain::class, '@'.FollowAccess::class,
            $notifications, '@clock', $types, 500, true, 604800, '@messenger.default_bus',
        ], $this->arguments($container, FanOutActivityHandler::class));
        self::assertSame(['@'.ActivityRepository::class, '@'.DeliveryRepository::class, '@clock', 30, 604800], $this->arguments($container, PurgeFollowRecordsHandler::class));
        self::assertSame([['handles' => PurgeFollowRecords::class]], $container->getDefinition(PurgeFollowRecordsHandler::class)->getTag('messenger.message_handler'));
        self::assertSame(['@'.ActivityRepository::class, '@'.FanOutActivityHandler::class, '@clock', '@messenger.default_bus'], $this->arguments($container, Activities::class));

        foreach ([Follows::class, FollowRequests::class, Mutuals::class, Followers::class, AutoFollow::class, PurgeFollowRecordsHandler::class, Activities::class, FollowController::class] as $service) {
            self::assertTrue($container->getDefinition($service)->isPublic(), $service);
        }
    }

    public function testTheFormsAreWiredToTheirGuardAndTheServices(): void
    {
        $container = $this->load([], ['twig']);

        self::assertSame(['@'.CsrfProtectionPass::TOKEN_MANAGER, '@uri_signer', '@router'], $this->arguments($container, FollowGuard::class));
        self::assertSame([
            '@'.Follows::class, '@'.FollowRequests::class, '@'.Followers::class, '@'.Mutuals::class, '@Phil\NotificationsBundle\Application\Contract\CurrentRecipient',
            '@'.FollowGuard::class, '@twig', '@translator',
        ], $this->arguments($container, FollowController::class));
    }

    public function testThePrivacyServiceReadsEveryRepository(): void
    {
        $container = $this->load([]);

        self::assertSame([
            '@'.FollowRepository::class, '@'.FollowRequestRepository::class, '@'.MutualRepository::class, '@'.ActivityRepository::class,
            '@'.DeliveryRepository::class, '@'.FollowerVisibilityRepository::class, 'member',
        ], $this->arguments($container, FollowPrivacy::class));
    }

    public function testTheBundleAddsItsCsrfCheck(): void
    {
        $container = new ContainerBuilder();
        new PhilFollowBundle()->build($container);

        $found = array_filter($container->getCompilerPassConfig()->getPasses(), static fn (object $pass): bool => $pass instanceof CsrfProtectionPass);
        self::assertCount(1, $found);
    }

    public function testTheDefaultsOfTheOptions(): void
    {
        $container = $this->load([]);

        self::assertSame(5, $container->getDefinition(SubjectChain::class)->getArgument(1));
        self::assertSame(30, $container->getDefinition(PurgeFollowRecordsHandler::class)->getArgument(3));
        self::assertSame(FollowLevel::Watching, $container->getDefinition(AutoFollow::class)->getArgument(6));
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('validOptions')]
    public function testTheLimitsOfTheOptionsAreAccepted(array $config): void
    {
        self::assertTrue($this->load([$config])->hasDefinition(Follows::class));
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidOptions')]
    public function testWhatIsBeyondTheLimitsOfTheOptionsIsRefused(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load([$config]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function validOptions(): iterable
    {
        yield 'the smallest page' => [['batch_size' => 1]];
        yield 'the largest page' => [['batch_size' => 1000]];
        yield 'no parents read' => [['inheritance' => ['max_depth' => 0]]];
        yield 'the deepest chain' => [['inheritance' => ['max_depth' => 16]]];
        yield 'no delay' => [['email' => ['resend_after' => 0]]];
        yield 'one day' => [['retention' => ['activity_days' => 1]]];
        yield 'watching' => [['auto_follow' => ['level' => 'watching']]];
        yield 'mentions' => [['auto_follow' => ['level' => 'mentions']]];
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidOptions(): iterable
    {
        yield 'an empty page' => [['batch_size' => 0]];
        yield 'a page too large' => [['batch_size' => 1001]];
        yield 'a negative depth' => [['inheritance' => ['max_depth' => -1]]];
        yield 'a chain too deep' => [['inheritance' => ['max_depth' => 17]]];
        yield 'a negative delay' => [['email' => ['resend_after' => -1]]];
        yield 'no day' => [['retention' => ['activity_days' => 0]]];
        yield 'ignoring' => [['auto_follow' => ['level' => 'ignoring']]];
    }

    /**
     * @return list<mixed>
     */
    private function arguments(ContainerBuilder $container, string $id): array
    {
        return array_values(array_map(static fn (mixed $argument): mixed => $argument instanceof Reference ? '@'.$argument : $argument, $container->getDefinition($id)->getArguments()));
    }

    /**
     * @param list<array<string, mixed>> $configs
     * @param list<string>               $extensions
     */
    private function load(array $configs, array $extensions = []): ContainerBuilder
    {
        $container = new ContainerBuilder();

        foreach ($extensions as $alias) {
            $container->registerExtension(new class($alias) extends Extension {
                public function __construct(private readonly string $alias)
                {
                }

                public function load(array $configs, ContainerBuilder $container): void
                {
                }

                public function getAlias(): string
                {
                    return $this->alias;
                }
            });
        }

        $extension = new PhilFollowBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);
        $extension->load($configs, $container);

        return $container;
    }
}
