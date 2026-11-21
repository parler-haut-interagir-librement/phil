<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\DependencyInjection;

use Phil\Outbox\Contract\Outbox;
use Phil\PrivacyBundle\Application\Contract\ErasureMessages;
use Phil\PrivacyBundle\Application\Contract\ErasureRequestStore;
use Phil\PrivacyBundle\Application\Contract\ExternalPersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\GuestRequestStore;
use Phil\PrivacyBundle\Application\Contract\GuestSubjects;
use Phil\PrivacyBundle\Application\Erasure\ErasureRequests;
use Phil\PrivacyBundle\Application\Handler\EraseModuleHandler;
use Phil\PrivacyBundle\Application\Message\EraseModule;
use Phil\PrivacyBundle\Infrastructure\Console\ErasureRequestCommand;
use Phil\PrivacyBundle\Infrastructure\Database\DbalErasureRequests;
use Phil\PrivacyBundle\Infrastructure\Messenger\ErasureFailureListener;
use Phil\PrivacyBundle\Infrastructure\Outbox\EraseModuleCodec;
use Phil\PrivacyBundle\Infrastructure\Outbox\EraseModuleRelay;
use Phil\PrivacyBundle\Infrastructure\Outbox\OutboxErasureMessages;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * The services of the erasure workflow, when `erasure_workflow.enabled` is true: the store of the requests, the
 * service, the handler of `EraseModule`, its outbox codec, the relay to the bus, the listener of the last failure, and
 * the command. `PrivacyServicesPass` checks that the outbox, Messenger and the clock are there, and hands the service
 * to the account deletions and to the requests made without an account.
 *
 * @phpstan-type WorkflowConfig array{enabled: bool, lease: int, operator_role: string, store: string|null}
 */
final class ErasureWorkflowServices
{
    /** The tag of every module's external eraser. */
    public const string EXTERNAL_ERASER_TAG = 'phil_privacy.external_eraser';

    /** The tag phil/outbox-bundle gathers its codecs by. */
    public const string OUTBOX_CODEC_TAG = 'phil_outbox.codec';

    /** The parameter of the role that reads the progress of any erasure request. */
    public const string OPERATOR_ROLE = 'phil_privacy.erasure_workflow.operator_role';

    private function __construct()
    {
    }

    /**
     * @param WorkflowConfig $config
     */
    public static function load(array $config, ServicesConfigurator $services, ContainerBuilder $builder, Reference $connection, Reference $clock): void
    {
        $builder->setParameter(self::OPERATOR_ROLE, $config['operator_role']);

        if (!$config['enabled']) {
            return;
        }

        $builder->registerForAutoconfiguration(ExternalPersonalDataEraser::class)->addTag(self::EXTERNAL_ERASER_TAG);

        if (null === $config['store']) {
            $services->set(DbalErasureRequests::class)->args([$connection])->tag(RightsServices::PURGER_TAG);
        }

        $services->alias(ErasureRequestStore::class, $config['store'] ?? DbalErasureRequests::class);
        $services->set(OutboxErasureMessages::class)->args([new Reference(Outbox::class)]);
        $services->alias(ErasureMessages::class, OutboxErasureMessages::class);
        $services->set(ErasureRequests::class)->args([
            new Reference(ErasureRequestStore::class),
            new Reference(ErasureMessages::class),
            new TaggedIteratorArgument(RightsServices::ERASER_TAG),
            new TaggedIteratorArgument(self::EXTERNAL_ERASER_TAG),
            $clock,
            new Reference(GuestRequestStore::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference(GuestSubjects::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            $config['lease'],
            new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ])->public();

        $services->set(EraseModuleHandler::class)
            ->args([new Reference(ErasureRequests::class)])
            ->tag('messenger.message_handler', ['handles' => EraseModule::class]);
        $services->set(EraseModuleCodec::class)
            ->args([new Reference(ErasureRequestStore::class)])
            ->tag(self::OUTBOX_CODEC_TAG);
        $services->set(EraseModuleRelay::class)
            ->args([new Reference('messenger.default_bus')])
            ->tag('kernel.event_listener', ['event' => EraseModule::class]);
        $services->set(ErasureFailureListener::class)
            ->args([new Reference(ErasureRequests::class)])
            ->tag('kernel.event_listener', ['event' => WorkerMessageFailedEvent::class]);

        if (class_exists(Command::class)) {
            $services->set(ErasureRequestCommand::class)->args([new Reference(ErasureRequests::class)])->tag('console.command');
        }
    }
}
