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
use Phil\ConversationBundle\Application\IdentifierDirectory;
use Phil\ConversationBundle\Application\NoContacts;
use Phil\ConversationBundle\Application\Reception;
use Phil\ConversationBundle\Domain\Repository\ConversationRepository;
use Phil\ConversationBundle\Domain\Repository\ReceptionRepository;
use Phil\ConversationBundle\Infrastructure\Console\RotateKeysCommand;
use Phil\ConversationBundle\Infrastructure\DependencyInjection\ConversationServices;
use Phil\ConversationBundle\Infrastructure\Doctrine\DbalConversationRepository;
use Phil\ConversationBundle\Infrastructure\Doctrine\DbalReceptionRepository;
use Phil\ConversationBundle\Infrastructure\Doctrine\KeyRotation;
use Phil\ConversationBundle\Infrastructure\Doctrine\SchemaListener;
use Phil\ConversationBundle\Infrastructure\Http\ConversationController;
use Phil\ConversationBundle\Infrastructure\Http\ConversationRequest;
use Phil\ConversationBundle\Infrastructure\KeyManagement\KeyManagementMessageSealer;
use Phil\ConversationBundle\Infrastructure\Mercure\MercureMessagePublisher;
use Phil\ConversationBundle\Infrastructure\Notifications\ConversationSubjects;
use Phil\ConversationBundle\Infrastructure\Notifications\NotifyParticipants;
use Phil\ConversationBundle\Infrastructure\Privacy\ConversationPrivacy;
use Phil\ConversationBundle\Infrastructure\Security\SecurityCurrentParticipant;
use Phil\ConversationBundle\Infrastructure\Twig\ConversationExtension;
use Phil\ConversationBundle\Infrastructure\Twig\KitComponents;
use Phil\ConversationBundle\PhilConversationBundle;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\PhilNotificationsBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\Mercure\HubInterface;

/**
 * What each service of the bundle receives, whether the application can fetch it, and what it is tagged with.
 */
#[CoversClass(ConversationServices::class)]
#[CoversClass(PhilConversationBundle::class)]
final class ConversationServicesTest extends TestCase
{
    private const array CONFIG = ['encryption' => ['key' => 'k2026']];

    public function testTheStoresAndTheEntryPointsAreWiredAndPublic(): void
    {
        $container = $this->load([self::CONFIG]);
        $connection = new Reference('doctrine.dbal.default_connection');
        $sealer = new Reference(MessageSealer::class);

        self::assertEquals([new Reference(DataKeyGeneratorInterface::class), 'k2026'], $container->getDefinition(KeyManagementMessageSealer::class)->getArguments());
        self::assertFalse($container->getAlias(MessageSealer::class)->isPublic());

        self::assertEquals([$connection, $sealer], $container->getDefinition(DbalConversationRepository::class)->getArguments());
        self::assertSame(DbalConversationRepository::class, (string) $container->getAlias(ConversationRepository::class));
        self::assertTrue($container->getAlias(ConversationRepository::class)->isPublic());
        self::assertEquals([$connection], $container->getDefinition(DbalReceptionRepository::class)->getArguments());
        self::assertSame(DbalReceptionRepository::class, (string) $container->getAlias(ReceptionRepository::class));
        self::assertTrue($container->getAlias(ReceptionRepository::class)->isPublic());

        $reception = $container->getDefinition(Reception::class);
        self::assertEquals([new Reference(ReceptionRepository::class), new Reference(ContactRelation::class)], $reception->getArguments());
        self::assertTrue($reception->isPublic());

        $conversations = $container->getDefinition(Conversations::class);
        self::assertEquals([
            new Reference(ConversationRepository::class),
            new Reference(Reception::class),
            new Reference('clock'),
            new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference(ConversationLimits::class),
        ], $conversations->getArguments());
        self::assertTrue($conversations->isPublic());
        self::assertSame(Conversations::class, (string) $container->getAlias(ConversationReader::class));
        self::assertTrue($container->getAlias(ConversationReader::class)->isPublic());

        $rotation = $container->getDefinition(KeyRotation::class);
        self::assertEquals([$connection, $sealer], $rotation->getArguments());
        self::assertTrue($rotation->isPublic());
        $command = $container->getDefinition(RotateKeysCommand::class);
        self::assertEquals([new Reference(KeyRotation::class)], $command->getArguments());
        self::assertSame([[]], $command->getTag('console.command'));

        $listener = $container->getDefinition(SchemaListener::class);
        self::assertEquals([$connection], $listener->getArguments());
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'default']], $listener->getTag('doctrine.event_listener'));

        $privacy = $container->getDefinition(ConversationPrivacy::class);
        self::assertEquals([new Reference(ConversationRepository::class), new Reference(ReceptionRepository::class)], $privacy->getArguments());
        self::assertSame([[]], $privacy->getTag(ConversationServices::EXPORTER_TAG));
        self::assertSame([[]], $privacy->getTag(ConversationServices::ERASER_TAG));
    }

    public function testThePortsHaveTheirDefaultsWhichTheApplicationReplaces(): void
    {
        $container = $this->load([self::CONFIG]);

        self::assertEquals([new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(SecurityCurrentParticipant::class)->getArguments());
        self::assertSame(SecurityCurrentParticipant::class, (string) $container->getAlias(CurrentParticipant::class));
        self::assertTrue($container->getAlias(CurrentParticipant::class)->isPublic());
        self::assertTrue($container->hasDefinition(IdentifierDirectory::class));
        self::assertSame(IdentifierDirectory::class, (string) $container->getAlias(ParticipantDirectory::class));
        self::assertTrue($container->hasDefinition(NoContacts::class));
        self::assertSame(NoContacts::class, (string) $container->getAlias(ContactRelation::class));

        $own = $this->load([self::CONFIG + ['current_participant' => 'app.current']]);
        self::assertSame('app.current', (string) $own->getAlias(CurrentParticipant::class));
        self::assertTrue($own->getAlias(CurrentParticipant::class)->isPublic());
    }

    public function testThePagesAreWired(): void
    {
        $container = $this->load([self::CONFIG]);

        $extension = $container->getDefinition(ConversationExtension::class);
        self::assertEquals([new Reference(KitComponents::class), new Reference(ParticipantDirectory::class)], $extension->getArguments());
        self::assertSame([[]], $extension->getTag('twig.attribute_extension'));
        self::assertSame([[]], $extension->getTag('twig.runtime'));

        self::assertEquals([new Reference('security.csrf.token_manager')], $container->getDefinition(ConversationRequest::class)->getArguments());

        $controller = $container->getDefinition(ConversationController::class);
        self::assertEquals([
            new Reference(Conversations::class),
            new Reference(Reception::class),
            new Reference(CurrentParticipant::class),
            new Reference(ConversationRequest::class),
            new Reference('twig'),
            new Reference('router'),
            '@PhilConversation/layout.html.twig',
            null,
        ], $controller->getArguments());
        self::assertTrue($controller->isPublic());
        self::assertSame([[]], $controller->getTag('controller.service_arguments'));
    }

    public function testTheNotificationsAndTheRealTimeAreWired(): void
    {
        $container = $this->load(
            [self::CONFIG + ['realtime' => ['enabled' => true, 'topic_prefix' => 'urn:site:']]],
            ['PhilNotificationsBundle' => PhilNotificationsBundle::class],
        );

        self::assertEquals([new Reference(Notifications::class), 'phil_conversation.message'], $container->getDefinition(NotifyParticipants::class)->getArguments());
        self::assertEquals([
            new Reference(ConversationReader::class),
            new Reference(ParticipantDirectory::class),
            new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ], $container->getDefinition(ConversationSubjects::class)->getArguments());
        self::assertEquals([
            new Reference(HubInterface::class),
            'urn:site:',
            new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ], $container->getDefinition(MercureMessagePublisher::class)->getArguments());
    }

    /**
     * @return iterable<string, array{string, int, int}>
     */
    public static function limits(): iterable
    {
        yield 'body_length' => ['body_length', 1, 1_000_000];
        yield 'subject_length' => ['subject_length', 1, 1000];
        yield 'group_size' => ['group_size', 2, 1000];
        yield 'page_size' => ['page_size', 1, 200];
    }

    #[DataProvider('limits')]
    public function testEachLimitAcceptsItsBoundsAndNothingBeyond(string $limit, int $min, int $max): void
    {
        foreach ([$min, $max] as $accepted) {
            $container = $this->load([self::CONFIG + ['limits' => [$limit => $accepted]]]);
            $position = array_search($limit, ['body_length', 'subject_length', 'group_size', 'page_size'], true);
            self::assertIsInt($position);
            self::assertSame($accepted, $container->getDefinition(ConversationLimits::class)->getArgument($position));
        }

        foreach ([$min - 1, $max + 1] as $refused) {
            try {
                $this->load([self::CONFIG + ['limits' => [$limit => $refused]]]);
                self::fail(\sprintf('%s of %d was accepted.', $limit, $refused));
            } catch (InvalidConfigurationException) {
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $configs
     * @param array<string, string>      $bundles
     */
    private function load(array $configs, array $bundles = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $bundles);

        $extension = new PhilConversationBundle()->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load($configs, $container);

        return $container;
    }
}
