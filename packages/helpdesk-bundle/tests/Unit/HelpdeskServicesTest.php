<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Tests\Unit;

use Phil\ConversationBundle\Application\Contract\ConversationReader;
use Phil\ConversationBundle\Application\Contract\CurrentParticipant;
use Phil\ConversationBundle\Application\Contract\ParticipantDirectory;
use Phil\ConversationBundle\Application\Conversations;
use Phil\HelpdeskBundle\Application\Contract\CannedReplies;
use Phil\HelpdeskBundle\Application\Contract\QueueAccess;
use Phil\HelpdeskBundle\Application\Helpdesk;
use Phil\HelpdeskBundle\Application\NoCannedReplies;
use Phil\HelpdeskBundle\Application\Queues;
use Phil\HelpdeskBundle\Domain\Repository\TicketRepository;
use Phil\HelpdeskBundle\Infrastructure\DependencyInjection\HelpdeskServices;
use Phil\HelpdeskBundle\Infrastructure\Doctrine\DbalTicketRepository;
use Phil\HelpdeskBundle\Infrastructure\Doctrine\SchemaListener;
use Phil\HelpdeskBundle\Infrastructure\Http\AgentController;
use Phil\HelpdeskBundle\Infrastructure\Http\HelpdeskRequest;
use Phil\HelpdeskBundle\Infrastructure\Http\RequesterController;
use Phil\HelpdeskBundle\Infrastructure\Privacy\HelpdeskPrivacy;
use Phil\HelpdeskBundle\Infrastructure\Security\RoleQueueAccess;
use Phil\HelpdeskBundle\Infrastructure\Twig\HelpdeskExtension;
use Phil\HelpdeskBundle\Infrastructure\Twig\KitComponents;
use Phil\HelpdeskBundle\Infrastructure\Twig\TwigCannedReplies;
use Phil\HelpdeskBundle\PhilHelpdeskBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * What each service of the bundle receives, whether the application can fetch it, and what it is tagged with.
 */
#[CoversClass(HelpdeskServices::class)]
#[CoversClass(PhilHelpdeskBundle::class)]
final class HelpdeskServicesTest extends TestCase
{
    private const array CONFIG = ['queues' => ['billing' => ['label' => 'queue.billing', 'roles' => ['ROLE_BILLING']]]];

    public function testTheStoreTheQueuesAndTheHelpdeskAreWiredAndPublic(): void
    {
        $container = $this->load([self::CONFIG]);
        $connection = new Reference('doctrine.dbal.default_connection');

        self::assertEquals([$connection], $container->getDefinition(DbalTicketRepository::class)->getArguments());
        self::assertTrue($container->getAlias(TicketRepository::class)->isPublic());
        self::assertTrue($container->getDefinition(Queues::class)->isPublic());

        self::assertEquals([new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(RoleQueueAccess::class)->getArguments());
        self::assertTrue($container->hasDefinition(NoCannedReplies::class));
        self::assertSame(NoCannedReplies::class, (string) $container->getAlias(CannedReplies::class));
        self::assertSame(RoleQueueAccess::class, (string) $container->getAlias(QueueAccess::class));

        $helpdesk = $container->getDefinition(Helpdesk::class);
        self::assertEquals([
            new Reference(TicketRepository::class),
            new Reference(Conversations::class),
            new Reference(Queues::class),
            new Reference(QueueAccess::class),
            new Reference('clock'),
            new Reference(CannedReplies::class),
            new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ], $helpdesk->getArguments());
        self::assertTrue($helpdesk->isPublic());

        self::assertEquals([$connection], $container->getDefinition(SchemaListener::class)->getArguments());

        $privacy = $container->getDefinition(HelpdeskPrivacy::class);
        self::assertEquals([new Reference(TicketRepository::class)], $privacy->getArguments());
        self::assertSame([[]], $privacy->getTag(HelpdeskServices::EXPORTER_TAG));
        self::assertSame([[]], $privacy->getTag(HelpdeskServices::ERASER_TAG));
    }

    public function testThePagesAreWired(): void
    {
        $container = $this->load([self::CONFIG]);

        $extension = $container->getDefinition(HelpdeskExtension::class);
        self::assertEquals([new Reference(KitComponents::class), new Reference(ParticipantDirectory::class)], $extension->getArguments());
        self::assertSame([[]], $extension->getTag('twig.attribute_extension'));
        self::assertSame([[]], $extension->getTag('twig.runtime'));

        self::assertEquals([new Reference('security.csrf.token_manager')], $container->getDefinition(HelpdeskRequest::class)->getArguments());

        $requesters = $container->getDefinition(RequesterController::class);
        self::assertEquals([
            new Reference(Helpdesk::class),
            new Reference(Conversations::class),
            new Reference(Queues::class),
            new Reference(CurrentParticipant::class),
            new Reference(HelpdeskRequest::class),
            new Reference('twig'),
            new Reference('router'),
            '@PhilHelpdesk/layout.html.twig',
        ], $requesters->getArguments());
        self::assertTrue($requesters->isPublic());
        self::assertSame([[]], $requesters->getTag('controller.service_arguments'));

        $agents = $container->getDefinition(AgentController::class);
        self::assertEquals([
            new Reference(Helpdesk::class),
            new Reference(ConversationReader::class),
            new Reference(Queues::class),
            new Reference(CurrentParticipant::class),
            new Reference(HelpdeskRequest::class),
            new Reference('twig'),
            new Reference('router'),
            '@PhilHelpdesk/layout.html.twig',
        ], $agents->getArguments());
        self::assertTrue($agents->isPublic());
        self::assertSame([[]], $agents->getTag('controller.service_arguments'));
    }

    public function testTheNamesOfTheQueuesAndOfTheCannedRepliesAreKeptAsWritten(): void
    {
        $container = $this->load([[
            'queues' => ['billing-2' => ['label' => 'B', 'roles' => ['ROLE_B']]],
            'canned_replies' => ['order-1' => ['label' => 'Order', 'template' => 'order.txt.twig']],
        ]]);

        $queues = $container->getDefinition(Queues::class)->getArgument(0);
        $replies = $container->getDefinition(TwigCannedReplies::class)->getArgument(1);
        self::assertIsArray($queues);
        self::assertIsArray($replies);
        self::assertSame(['billing-2'], array_keys($queues));
        self::assertSame(['order-1'], array_keys($replies));
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);

        $extension = new PhilHelpdeskBundle()->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load($configs, $container);

        return $container;
    }
}
