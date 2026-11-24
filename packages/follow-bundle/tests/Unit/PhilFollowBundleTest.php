<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Unit;

use Phil\FollowBundle\Application\Contract\FollowAccess;
use Phil\FollowBundle\Application\Contract\FollowApproval;
use Phil\FollowBundle\Application\Contract\SubjectParents;
use Phil\FollowBundle\Application\Handler\FanOutActivityHandler;
use Phil\FollowBundle\Application\Message\FanOutActivity;
use Phil\FollowBundle\Application\NoParents;
use Phil\FollowBundle\Application\OpenAccess;
use Phil\FollowBundle\Application\OpenFollowing;
use Phil\FollowBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\FollowBundle\Infrastructure\DependencyInjection\FollowServices;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalFollowRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\FollowSchemaListener;
use Phil\FollowBundle\Infrastructure\Http\FollowController;
use Phil\FollowBundle\Infrastructure\Http\FollowGuard;
use Phil\FollowBundle\Infrastructure\Privacy\FollowPrivacy;
use Phil\FollowBundle\Infrastructure\Scheduler\FollowSchedule;
use Phil\FollowBundle\Infrastructure\Twig\FollowExtension;
use Phil\FollowBundle\PhilFollowBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilFollowBundle::class)]
#[CoversClass(FollowServices::class)]
#[CoversClass(CsrfProtectionPass::class)]
final class PhilFollowBundleTest extends TestCase
{
    public function testTheAlias(): void
    {
        self::assertSame('phil_follow', $this->extension()->getAlias());
    }

    public function testTheDefaultsOpenEverySubjectWithoutParents(): void
    {
        $container = $this->load([]);

        self::assertSame(NoParents::class, (string) $container->getAlias(SubjectParents::class));
        self::assertSame(OpenFollowing::class, (string) $container->getAlias(FollowApproval::class));
        self::assertSame(OpenAccess::class, (string) $container->getAlias(FollowAccess::class));
        $handler = $container->getDefinition(FanOutActivityHandler::class);
        self::assertSame(500, $handler->getArgument(8));
        self::assertTrue($handler->getArgument(9));
        self::assertSame(604800, $handler->getArgument(10));
        self::assertSame('messenger.default_bus', $this->id($handler->getArgument(11)));
        self::assertSame([['handles' => FanOutActivity::class]], $handler->getTag('messenger.message_handler'));
        self::assertSame('doctrine.dbal.default_connection', $this->id($container->getDefinition(DbalFollowRepository::class)->getArgument(0)));
        self::assertTrue($container->hasDefinition(FollowSchemaListener::class));
        self::assertTrue($container->hasDefinition(FollowPrivacy::class));
        self::assertSame([[], []], [$container->getDefinition(FollowPrivacy::class)->getTag('phil_privacy.exporter')[0], $container->getDefinition(FollowPrivacy::class)->getTag('phil_privacy.eraser')[0]]);
        self::assertFalse($container->hasDefinition(FollowGuard::class), 'The forms need Twig.');
    }

    public function testTheApplicationNamesItsPorts(): void
    {
        $container = $this->load([['access' => 'app.access', 'approval' => 'app.approval', 'parents' => OpenAccess::class, 'connection' => 'follows', 'batch_size' => 200, 'message_bus' => 'app.bus', 'email' => ['once_until_seen' => false, 'resend_after' => 60]]]);

        self::assertSame('app.access', (string) $container->getAlias(FollowAccess::class));
        self::assertSame('app.approval', (string) $container->getAlias(FollowApproval::class));
        self::assertFalse($container->hasDefinition(OpenFollowing::class));
        self::assertFalse($container->hasDefinition(NoParents::class));
        self::assertSame('doctrine.dbal.follows_connection', $this->id($container->getDefinition(DbalFollowRepository::class)->getArgument(0)));
        $handler = $container->getDefinition(FanOutActivityHandler::class);
        self::assertSame([200, false, 60, 'app.bus'], [$handler->getArgument(8), $handler->getArgument(9), $handler->getArgument(10), $this->id($handler->getArgument(11))]);
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'follows']], $container->getDefinition(FollowSchemaListener::class)->getTag('doctrine.event_listener'));
    }

    public function testNamingTheOpenAccessRegistersIt(): void
    {
        $container = $this->load([['access' => OpenAccess::class]], ['security']);

        self::assertTrue($container->hasDefinition(OpenAccess::class));
        self::assertSame(OpenAccess::class, (string) $container->getAlias(FollowAccess::class));
    }

    public function testWithSecurityTheAccessIsTheApplicationsChoice(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The option "phil_follow.access" is required with Symfony Security');
        $this->load([], ['security']);
    }

    public function testWithTwigTheFormsAndTemplatesAreRegistered(): void
    {
        $container = $this->load([['events' => ['reply.created'], 'current_member' => 'app.member']], ['twig']);

        self::assertTrue($container->hasDefinition(FollowGuard::class));
        $extension = $container->getDefinition(FollowExtension::class);
        self::assertSame(['reply.created'], $extension->getArgument(8));
        self::assertSame('app.member', $this->id($extension->getArgument(4)));
        self::assertTrue($extension->hasTag('twig.attribute_extension'));
        self::assertTrue($extension->hasTag('twig.runtime'));
        self::assertSame('app.member', $this->id($container->getDefinition(FollowController::class)->getArgument(4)));
    }

    public function testWhileLoadingTheBundlesAreReadFromTheKernel(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['TwigBundle' => TwigBundle::class]);
        $this->extension()->load([], $container);
        self::assertTrue($container->hasDefinition(FollowGuard::class));

        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', 'not a list');
        $this->extension()->load([], $container);
        self::assertFalse($container->hasDefinition(FollowGuard::class));

        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => 'App\\SecurityBundle']);
        $this->expectException(InvalidConfigurationException::class);
        $this->extension()->load([], $container);
    }

    public function testTheScheduleLocksAndKeepsItsStateByDefault(): void
    {
        $schedule = $this->load([])->getDefinition(FollowSchedule::class);

        self::assertSame(['04:15', 'UTC', 'lock.factory', 'cache.app'], [$schedule->getArgument(0), $schedule->getArgument(1), $this->id($schedule->getArgument(2)), $this->id($schedule->getArgument(3))]);
        self::assertSame([['name' => 'phil_follow']], $schedule->getTag('scheduler.schedule_provider'));
        $reference = $schedule->getArgument(2);
        self::assertInstanceOf(Reference::class, $reference);
        self::assertSame(ContainerBuilder::NULL_ON_INVALID_REFERENCE, $reference->getInvalidBehavior());
    }

    public function testTheScheduleCanRunWithoutLockNorStateOrNotAtAll(): void
    {
        $schedule = $this->load([['schedule' => ['lock_factory' => null, 'state_pool' => null, 'at' => '02:00', 'timezone' => 'America/Toronto']]])->getDefinition(FollowSchedule::class);
        self::assertSame(['02:00', 'America/Toronto', null, null], $schedule->getArguments());

        self::assertFalse($this->load([['schedule' => ['enabled' => false]]])->hasDefinition(FollowSchedule::class));
    }

    public function testTheBundlePrependsItsTokenAndItsNotificationTypes(): void
    {
        $container = $this->prepend([], ['framework', 'phil_notifications']);

        self::assertSame([['csrf_protection' => ['stateless_token_ids' => ['phil_follow']]]], $container->getExtensionConfig('framework'));
        self::assertSame([['types' => PhilFollowBundle::NOTIFICATION_TYPES]], $container->getExtensionConfig('phil_notifications'));
    }

    public function testTheApplicationDeclaresTheTypesItself(): void
    {
        self::assertSame([], $this->prepend([['notifications' => ['declare_types' => false]]], ['phil_notifications'])->getExtensionConfig('phil_notifications'));
        self::assertSame([['types' => PhilFollowBundle::NOTIFICATION_TYPES]], $this->prepend([['notifications' => ['declare_types' => false]], ['notifications' => ['declare_types' => true]]], ['phil_notifications'])->getExtensionConfig('phil_notifications'));
        self::assertSame([['types' => PhilFollowBundle::NOTIFICATION_TYPES]], $this->prepend([['batch_size' => 10], ['notifications' => ['activity' => 'x']]], ['phil_notifications'])->getExtensionConfig('phil_notifications'));
        self::assertSame([], $this->prepend([], [])->getExtensionConfig('phil_notifications'));
    }

    public function testTheCsrfProtectionIsRequiredForTheForms(): void
    {
        $container = new ContainerBuilder();
        new CsrfProtectionPass()->process($container);
        $container->register(FollowGuard::class);
        $container->register(CsrfProtectionPass::TOKEN_MANAGER);
        new CsrfProtectionPass()->process($container);
        $container->removeDefinition(CsrfProtectionPass::TOKEN_MANAGER);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('"framework.csrf_protection" is false');
        new PhilFollowBundle()->build($container);
        new CsrfProtectionPass()->process($container);
    }

    public function testTheBatchSizeIsBounded(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load([['batch_size' => 1001]]);
    }

    private function id(mixed $reference): string
    {
        self::assertInstanceOf(Reference::class, $reference);

        return (string) $reference;
    }

    /**
     * @param list<array<string, mixed>> $configs
     * @param list<string>               $extensions
     */
    private function load(array $configs, array $extensions = []): ContainerBuilder
    {
        $container = $this->container($extensions);
        $this->extension()->load($configs, $container);

        return $container;
    }

    /**
     * @param list<array<string, mixed>> $configs
     * @param list<string>               $extensions
     */
    private function prepend(array $configs, array $extensions): ContainerBuilder
    {
        $container = $this->container($extensions);
        $extension = $this->extension();
        $container->registerExtension($extension);

        foreach ($configs as $config) {
            $container->loadFromExtension('phil_follow', $config);
        }

        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        return $container;
    }

    /**
     * @param list<string> $extensions
     */
    private function container(array $extensions): ContainerBuilder
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

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilFollowBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
