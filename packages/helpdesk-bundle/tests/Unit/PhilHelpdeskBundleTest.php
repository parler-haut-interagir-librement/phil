<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Tests\Unit;

use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Phil\HelpdeskBundle\Application\Contract\CannedReplies;
use Phil\HelpdeskBundle\Application\Contract\QueueAccess;
use Phil\HelpdeskBundle\Application\Event\TicketAssigned;
use Phil\HelpdeskBundle\Application\Helpdesk;
use Phil\HelpdeskBundle\Application\NoCannedReplies;
use Phil\HelpdeskBundle\Application\Queues;
use Phil\HelpdeskBundle\Domain\Repository\TicketRepository;
use Phil\HelpdeskBundle\Infrastructure\DependencyInjection\HelpdeskServices;
use Phil\HelpdeskBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\HelpdeskBundle\Infrastructure\Doctrine\DbalTicketRepository;
use Phil\HelpdeskBundle\Infrastructure\Doctrine\SchemaListener;
use Phil\HelpdeskBundle\Infrastructure\Http\AgentController;
use Phil\HelpdeskBundle\Infrastructure\Http\RequesterController;
use Phil\HelpdeskBundle\Infrastructure\Notifications\NotifyAssignee;
use Phil\HelpdeskBundle\Infrastructure\Privacy\HelpdeskPrivacy;
use Phil\HelpdeskBundle\Infrastructure\Security\RoleQueueAccess;
use Phil\HelpdeskBundle\Infrastructure\Twig\KitComponents;
use Phil\HelpdeskBundle\Infrastructure\Twig\TwigCannedReplies;
use Phil\HelpdeskBundle\PhilHelpdeskBundle;
use Phil\HelpdeskBundle\Tests\Unit\Fixture\NamedExtension;
use Phil\NotificationsBundle\PhilNotificationsBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilHelpdeskBundle::class)]
#[CoversClass(HelpdeskServices::class)]
#[CoversClass(KitSubdirectoryPass::class)]
final class PhilHelpdeskBundleTest extends TestCase
{
    private const array QUEUES = ['queues' => ['billing' => ['label' => 'queue.billing', 'roles' => ['ROLE_BILLING']]]];

    public function testTheDefaultsWireTheStoreTheQueuesAndThePages(): void
    {
        $container = $this->load([self::QUEUES]);

        self::assertSame('phil_helpdesk', $this->extension()->getAlias());
        self::assertSame(DbalTicketRepository::class, (string) $container->getAlias(TicketRepository::class));
        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $container->getDefinition(DbalTicketRepository::class)->getArguments());
        self::assertSame([['billing' => ['label' => 'queue.billing', 'roles' => ['ROLE_BILLING']]]], $container->getDefinition(Queues::class)->getArguments());
        self::assertSame([Queues::class, 'fromDeclarations'], $container->getDefinition(Queues::class)->getFactory());
        self::assertSame(RoleQueueAccess::class, (string) $container->getAlias(QueueAccess::class));
        self::assertSame(NoCannedReplies::class, (string) $container->getAlias(CannedReplies::class));
        self::assertTrue($container->getDefinition(Helpdesk::class)->isPublic());
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'default']], $container->getDefinition(SchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertTrue($container->getDefinition(HelpdeskPrivacy::class)->hasTag('phil_privacy.exporter'));
        self::assertTrue($container->getDefinition(HelpdeskPrivacy::class)->hasTag('phil_privacy.eraser'));
        self::assertSame('@PhilHelpdesk/layout.html.twig', $container->getDefinition(RequesterController::class)->getArgument(7));
        self::assertSame('@PhilHelpdesk/layout.html.twig', $container->getDefinition(AgentController::class)->getArgument(7));
        self::assertFalse($container->getDefinition(AgentController::class)->hasTag(EasyAdminBundle::TAG_ADMIN_ROUTE_CONTROLLER));
        self::assertNull($container->getDefinition(KitComponents::class)->getArgument(0));
        self::assertFalse($container->hasDefinition(NotifyAssignee::class));
    }

    public function testTheApplicationNamesItsAccessCannedRepliesAndLayouts(): void
    {
        $container = $this->load([self::QUEUES + [
            'connection' => 'support',
            'queue_access' => 'app.access',
            'canned_replies' => ['thanks' => ['label' => 'Thanks', 'template' => 'canned/thanks.txt.twig']],
            'layout' => 'base.html.twig',
            'agent_layout' => 'admin.html.twig',
            'kit_subdirectory' => 'ui',
        ]], ['EasyAdminBundle' => EasyAdminBundle::class]);

        self::assertEquals([new Reference('doctrine.dbal.support_connection')], $container->getDefinition(DbalTicketRepository::class)->getArguments());
        self::assertSame('app.access', (string) $container->getAlias(QueueAccess::class));
        self::assertFalse($container->hasDefinition(RoleQueueAccess::class));
        self::assertSame(TwigCannedReplies::class, (string) $container->getAlias(CannedReplies::class));
        self::assertSame(['thanks' => ['label' => 'Thanks', 'template' => 'canned/thanks.txt.twig', 'queues' => []]], $container->getDefinition(TwigCannedReplies::class)->getArgument(1));
        self::assertSame('base.html.twig', $container->getDefinition(RequesterController::class)->getArgument(7));
        self::assertSame('admin.html.twig', $container->getDefinition(AgentController::class)->getArgument(7));
        self::assertTrue($container->getDefinition(AgentController::class)->hasTag(EasyAdminBundle::TAG_ADMIN_ROUTE_CONTROLLER));
        self::assertSame('ui', $container->getDefinition(KitComponents::class)->getArgument(0));

        $source = $this->load([self::QUEUES + ['canned_reply_source' => 'app.templates', 'canned_replies' => ['thanks' => ['label' => 'Thanks', 'template' => 't']]]]);
        self::assertSame('app.templates', (string) $source->getAlias(CannedReplies::class));
        self::assertFalse($source->hasDefinition(TwigCannedReplies::class));
    }

    public function testTheQueuesAreRequiredAndNamedBySlug(): void
    {
        foreach ([[], ['queues' => []], ['queues' => ['Billing' => ['label' => 'B', 'roles' => ['ROLE_B']]]], ['queues' => ['billing' => ['label' => 'B', 'roles' => []]]]] as $config) {
            try {
                $this->load([$config]);
                self::fail('A configuration without valid queues was loaded.');
            } catch (InvalidConfigurationException) {
            }
        }

        self::assertTrue($this->load([['queues' => ['b-1_x' => ['label' => 'B', 'roles' => ['ROLE_B']]]]])->hasDefinition(Queues::class));
    }

    public function testTheNotificationIsWiredWithItsBundle(): void
    {
        $container = $this->load([self::QUEUES + ['notifications' => ['type' => 'site.assigned']]], ['PhilNotificationsBundle' => PhilNotificationsBundle::class]);

        $notify = $container->getDefinition(NotifyAssignee::class);
        self::assertSame('site.assigned', $notify->getArgument(1));
        self::assertSame([['event' => TicketAssigned::class]], $notify->getTag('kernel.event_listener'));
        self::assertFalse($this->load([self::QUEUES + ['notifications' => false]], ['PhilNotificationsBundle' => 'x'])->hasDefinition(NotifyAssignee::class));
    }

    public function testTheOtherBundlesAreConfiguredWhenTheyAreThere(): void
    {
        $container = new ContainerBuilder();

        foreach (['framework', 'phil_notifications'] as $name) {
            $container->registerExtension(new NamedExtension($name));
        }

        $container->prependExtensionConfig('phil_helpdesk', ['notifications' => ['type' => 'first.type']]);
        $container->prependExtensionConfig('phil_helpdesk', ['notifications' => ['type' => 'site.assigned']]);
        $container->prependExtensionConfig('phil_helpdesk', ['notifications' => 'not an array']);

        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['csrf_protection' => ['stateless_token_ids' => ['phil_helpdesk']]]], $container->getExtensionConfig('framework'));
        self::assertSame([['types' => ['first.type' => ['channels' => ['site', 'email']]]]], $container->getExtensionConfig('phil_notifications'));

        $default = new ContainerBuilder();
        $default->registerExtension(new NamedExtension('phil_notifications'));

        $extension->prepend($default);
        self::assertSame([['types' => ['phil_helpdesk.assigned' => ['channels' => ['site', 'email']]]]], $default->getExtensionConfig('phil_notifications'));

        $bare = new ContainerBuilder();
        $extension->prepend($bare);
        self::assertSame([], $bare->getExtensionConfig('framework'));
    }

    public function testTheKitIsNamedAfterTheToolkit(): void
    {
        $container = $this->load([self::QUEUES]);
        $container->setParameter('.ux_toolkit.components.subdirectory', 'ui');
        new KitSubdirectoryPass()->process($container);
        self::assertSame('ui', $container->getDefinition(KitComponents::class)->getArgument(0));

        $without = $this->load([self::QUEUES]);
        new KitSubdirectoryPass()->process($without);
        self::assertSame('', $without->getDefinition(KitComponents::class)->getArgument(0));

        $named = $this->load([self::QUEUES + ['kit_subdirectory' => 'kit']]);
        $named->setParameter('.ux_toolkit.components.subdirectory', 'ui');
        new KitSubdirectoryPass()->process($named);
        self::assertSame('kit', $named->getDefinition(KitComponents::class)->getArgument(0));

        $empty = new ContainerBuilder();
        new KitSubdirectoryPass()->process($empty);
        self::assertFalse($empty->hasDefinition(KitComponents::class));

        $built = new ContainerBuilder();
        new PhilHelpdeskBundle()->build($built);
        self::assertContains(KitSubdirectoryPass::class, array_map(static fn (object $pass): string => $pass::class, $built->getCompilerPassConfig()->getBeforeOptimizationPasses()));
    }

    /**
     * @param list<array<string, mixed>> $configs
     * @param array<string, string>      $bundles
     */
    private function load(array $configs, array $bundles = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $bundles);
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilHelpdeskBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
