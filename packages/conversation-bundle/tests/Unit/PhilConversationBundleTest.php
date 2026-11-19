<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Unit;

use Phil\ConversationBundle\Application\Contract\ContactRelation;
use Phil\ConversationBundle\Application\Contract\ConversationReader;
use Phil\ConversationBundle\Application\Contract\CurrentParticipant;
use Phil\ConversationBundle\Application\Contract\MessageSealer;
use Phil\ConversationBundle\Application\Contract\ParticipantDirectory;
use Phil\ConversationBundle\Application\ConversationLimits;
use Phil\ConversationBundle\Application\Conversations;
use Phil\ConversationBundle\Application\Event\MessageSent;
use Phil\ConversationBundle\Application\IdentifierDirectory;
use Phil\ConversationBundle\Application\NoContacts;
use Phil\ConversationBundle\Infrastructure\Console\RotateKeysCommand;
use Phil\ConversationBundle\Infrastructure\DependencyInjection\ConversationServices;
use Phil\ConversationBundle\Infrastructure\DependencyInjection\EncryptionPass;
use Phil\ConversationBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\ConversationBundle\Infrastructure\Doctrine\SchemaListener;
use Phil\ConversationBundle\Infrastructure\Http\ConversationController;
use Phil\ConversationBundle\Infrastructure\KeyManagement\KeyManagementMessageSealer;
use Phil\ConversationBundle\Infrastructure\Mercure\MercureMessagePublisher;
use Phil\ConversationBundle\Infrastructure\Notifications\ConversationSubjects;
use Phil\ConversationBundle\Infrastructure\Notifications\NotifyParticipants;
use Phil\ConversationBundle\Infrastructure\Privacy\ConversationPrivacy;
use Phil\ConversationBundle\Infrastructure\Security\SecurityCurrentParticipant;
use Phil\ConversationBundle\Infrastructure\Twig\KitComponents;
use Phil\ConversationBundle\PhilConversationBundle;
use Phil\ConversationBundle\Tests\Unit\Fixture\NamedExtension;
use Phil\NotificationsBundle\PhilNotificationsBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\Mercure\HubInterface;

#[CoversClass(PhilConversationBundle::class)]
#[CoversClass(ConversationServices::class)]
#[CoversClass(EncryptionPass::class)]
#[CoversClass(KitSubdirectoryPass::class)]
final class PhilConversationBundleTest extends TestCase
{
    private const array ENCRYPTION = ['encryption' => ['key' => 'k2026']];

    public function testTheDefaultsWireTheStoresTheSealerAndThePages(): void
    {
        $container = $this->load([self::ENCRYPTION]);

        self::assertSame('phil_conversation', $this->extension()->getAlias());
        $sealer = $container->getDefinition(KeyManagementMessageSealer::class);
        self::assertEquals([new Reference(DataKeyGeneratorInterface::class), 'k2026'], $sealer->getArguments());
        self::assertSame(KeyManagementMessageSealer::class, (string) $container->getAlias(MessageSealer::class));
        self::assertEquals(new Reference('doctrine.dbal.default_connection'), $container->getDefinition(SchemaListener::class)->getArgument(0));
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'default']], $container->getDefinition(SchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertSame(SecurityCurrentParticipant::class, (string) $container->getAlias(CurrentParticipant::class));
        self::assertSame(IdentifierDirectory::class, (string) $container->getAlias(ParticipantDirectory::class));
        self::assertSame(NoContacts::class, (string) $container->getAlias(ContactRelation::class));
        self::assertSame(Conversations::class, (string) $container->getAlias(ConversationReader::class));
        self::assertTrue($container->getDefinition(Conversations::class)->isPublic());
        self::assertSame([10_000, 200, 50, 50], $container->getDefinition(ConversationLimits::class)->getArguments());
        self::assertTrue($container->getDefinition(RotateKeysCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(ConversationPrivacy::class)->hasTag('phil_privacy.exporter'));
        self::assertTrue($container->getDefinition(ConversationPrivacy::class)->hasTag('phil_privacy.eraser'));

        $controller = $container->getDefinition(ConversationController::class);
        self::assertSame(['@PhilConversation/layout.html.twig', null], \array_slice($controller->getArguments(), 6));
        self::assertTrue($controller->hasTag('controller.service_arguments'));
        self::assertNull($container->getDefinition(KitComponents::class)->getArgument(0));
        self::assertFalse($container->hasDefinition(NotifyParticipants::class), 'without the notifications bundle registered');
        self::assertFalse($container->hasDefinition(MercureMessagePublisher::class));
    }

    public function testTheApplicationNamesItsPortsConnectionAndLimits(): void
    {
        $container = $this->load([[
            'connection' => 'messages',
            'encryption' => ['key' => 'k2027', 'kms' => 'key_management.vault'],
            'current_participant' => 'app.current',
            'directory' => 'app.directory',
            'contacts' => 'app.contacts',
            'layout' => 'base.html.twig',
            'kit_subdirectory' => 'ui',
            'limits' => ['body_length' => 500, 'subject_length' => 80, 'group_size' => 10, 'page_size' => 20],
        ]]);

        self::assertEquals([new Reference('key_management.vault'), 'k2027'], $container->getDefinition(KeyManagementMessageSealer::class)->getArguments());
        self::assertEquals(new Reference('doctrine.dbal.messages_connection'), $container->getDefinition(SchemaListener::class)->getArgument(0));
        self::assertSame('app.current', (string) $container->getAlias(CurrentParticipant::class));
        self::assertSame('app.directory', (string) $container->getAlias(ParticipantDirectory::class));
        self::assertSame('app.contacts', (string) $container->getAlias(ContactRelation::class));
        self::assertFalse($container->hasDefinition(SecurityCurrentParticipant::class));
        self::assertFalse($container->hasDefinition(IdentifierDirectory::class));
        self::assertFalse($container->hasDefinition(NoContacts::class));
        self::assertSame([500, 80, 10, 20], $container->getDefinition(ConversationLimits::class)->getArguments());
        self::assertSame('base.html.twig', $container->getDefinition(ConversationController::class)->getArgument(6));
        self::assertSame('ui', $container->getDefinition(KitComponents::class)->getArgument(0));
    }

    public function testTheEncryptionIsRequired(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([[]]);
    }

    public function testTheNotificationsAreWiredWithTheirBundle(): void
    {
        $container = $this->load([self::ENCRYPTION + ['notifications' => ['type' => 'site.message']]], ['PhilNotificationsBundle' => PhilNotificationsBundle::class]);

        $notify = $container->getDefinition(NotifyParticipants::class);
        self::assertSame('site.message', $notify->getArgument(1));
        self::assertSame([['event' => MessageSent::class]], $notify->getTag('kernel.event_listener'));
        self::assertTrue($container->getDefinition(ConversationSubjects::class)->isAutoconfigured());

        $disabled = $this->load([self::ENCRYPTION + ['notifications' => false]], ['PhilNotificationsBundle' => PhilNotificationsBundle::class]);
        self::assertFalse($disabled->hasDefinition(NotifyParticipants::class));
    }

    public function testTheRealTimeIsWiredWhenEnabled(): void
    {
        $container = $this->load([self::ENCRYPTION + ['realtime' => ['enabled' => true, 'topic_prefix' => 'urn:site:']]]);

        $publisher = $container->getDefinition(MercureMessagePublisher::class);
        self::assertEquals(new Reference(HubInterface::class), $publisher->getArgument(0));
        self::assertSame('urn:site:', $publisher->getArgument(1));
        self::assertSame([['event' => MessageSent::class]], $publisher->getTag('kernel.event_listener'));
        self::assertSame('urn:site:', $container->getDefinition(ConversationController::class)->getArgument(7));
        self::assertSame(HubInterface::class, PhilConversationBundle::HUB);
    }

    public function testTheOtherBundlesAreConfiguredWhenTheyAreThere(): void
    {
        $container = new ContainerBuilder();
        foreach (['framework', 'phil_notifications', 'asset_mapper'] as $name) {
            $container->registerExtension(new NamedExtension($name));
        }

        $container->prependExtensionConfig('phil_conversation', ['notifications' => ['type' => 'first.type']]);
        $container->prependExtensionConfig('phil_conversation', ['notifications' => ['type' => 'site.message']]);
        $container->prependExtensionConfig('phil_conversation', ['notifications' => 'not an array']);

        $extension = $this->extension();
        $this->assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['csrf_protection' => ['stateless_token_ids' => ['phil_conversation']]]], $container->getExtensionConfig('framework'));
        self::assertSame([['types' => ['first.type' => ['channels' => ['site', 'email'], 'collapse' => true]]]], $container->getExtensionConfig('phil_notifications'));
        self::assertSame([['paths' => [PhilConversationBundle::CONTROLLERS_DIR => '@phil/conversation-bundle']]], $container->getExtensionConfig('asset_mapper'));

        $bare = new ContainerBuilder();
        $extension->prepend($bare);
        self::assertSame([], $bare->getExtensionConfig('framework'));
    }

    public function testTheDefaultNotificationTypeIsDeclared(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new NamedExtension('phil_notifications'));

        $extension = $this->extension();
        $this->assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['types' => ['phil_conversation.message' => ['channels' => ['site', 'email'], 'collapse' => true]]]], $container->getExtensionConfig('phil_notifications'));
    }

    public function testAMissingKeyManagementClientStopsTheContainer(): void
    {
        $container = $this->load([self::ENCRYPTION]);

        try {
            new EncryptionPass()->process($container);
            self::fail('The conversations were wired without a key management client.');
        } catch (LogicException $logicException) {
            self::assertStringContainsString('"phil_conversation.encryption" seals the conversations through the key management client "Symfony\Component\KeyManagement\DataKeyGeneratorInterface", which is not defined', $logicException->getMessage());
        }

        $container->register(DataKeyGeneratorInterface::class);
        new EncryptionPass()->process($container);
        self::assertTrue($container->hasDefinition(KeyManagementMessageSealer::class), 'a defined client passes');

        $own = $this->load([self::ENCRYPTION]);
        $own->setAlias(MessageSealer::class, 'app.sealer');
        new EncryptionPass()->process($own);
        self::assertSame('app.sealer', (string) $own->getAlias(MessageSealer::class), "the application's own sealer is kept");

        $empty = new ContainerBuilder();
        new EncryptionPass()->process($empty);
        self::assertFalse($empty->hasDefinition(KeyManagementMessageSealer::class));
    }

    public function testTheKitIsNamedAfterTheToolkit(): void
    {
        $container = $this->load([self::ENCRYPTION]);
        $container->setParameter('.ux_toolkit.components.subdirectory', 'ui');
        new KitSubdirectoryPass()->process($container);
        self::assertSame('ui', $container->getDefinition(KitComponents::class)->getArgument(0));

        $without = $this->load([self::ENCRYPTION]);
        new KitSubdirectoryPass()->process($without);
        self::assertSame('', $without->getDefinition(KitComponents::class)->getArgument(0));

        $named = $this->load([self::ENCRYPTION + ['kit_subdirectory' => 'kit']]);
        $named->setParameter('.ux_toolkit.components.subdirectory', 'ui');
        new KitSubdirectoryPass()->process($named);
        self::assertSame('kit', $named->getDefinition(KitComponents::class)->getArgument(0));

        new KitSubdirectoryPass()->process(new ContainerBuilder());
    }

    public function testTheBundleAddsItsPasses(): void
    {
        $container = new ContainerBuilder();
        new PhilConversationBundle()->build($container);
        $passes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());

        self::assertContains(EncryptionPass::class, $passes);
        self::assertContains(KitSubdirectoryPass::class, $passes);
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
        $extension = new PhilConversationBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
