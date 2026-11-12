<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Infrastructure\DependencyInjection;

use Phil\FormsAutosave\Application\Contract\DraftOwner;
use Phil\FormsAutosave\Application\Contract\DraftSealer;
use Phil\FormsAutosave\Application\Contract\DraftStore;
use Phil\FormsAutosave\Application\DraftKeys;
use Phil\FormsAutosave\Application\Drafts;
use Phil\FormsAutosave\Application\PlainSealer;
use Phil\FormsAutosave\Infrastructure\Console\PurgeCommand;
use Phil\FormsAutosave\Infrastructure\Doctrine\DoctrineDraftStore;
use Phil\FormsAutosave\Infrastructure\Form\AutosaveLabels;
use Phil\FormsAutosave\Infrastructure\Form\AutosaveTypeExtension;
use Phil\FormsAutosave\Infrastructure\Http\DoneCookie;
use Phil\FormsAutosave\Infrastructure\Http\DraftController;
use Phil\FormsAutosave\Infrastructure\Http\DraftRequest;
use Phil\FormsAutosave\Infrastructure\Memory\InMemoryDraftStore;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The bundle's services: the keys, the labels, the form type extension and the cookie of submitted drafts; with the
 * server store, the store, the sealer in clear when encryption is off, the drafts, the check of the controller's
 * requests, the endpoints and the purge command. The owner and the encrypting sealer are completed by
 * `ServerStorePass`, the latter from the parameters of the master key and its KMS.
 *
 * @phpstan-type ServerConfig array{enabled: bool, store: string, connection: string, encrypt: bool, key: ?string, kms: ?string, max_bytes: int, owner: ?string}
 * @phpstan-type AutosaveConfig array{secret: string, ttl: int, debounce: int, max_bytes: int, server: ServerConfig}
 */
final class AutosaveServices
{
    /** The master key that seals drafts on the server, or null; read by `ServerStorePass`. */
    public const string KEY = 'phil_forms_autosave.server.key';

    /** The service of the KMS that holds it, or null for the default client; read by `ServerStorePass`. */
    public const string KMS = 'phil_forms_autosave.server.kms';

    private function __construct()
    {
    }

    /**
     * @param AutosaveConfig $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $server = $config['server'];
        $optional = ContainerInterface::NULL_ON_INVALID_REFERENCE;

        if (null !== $server['owner']) {
            $container->setAlias(DraftOwner::class, $server['owner']);
        }

        $container->register(DraftKeys::class, DraftKeys::class)->setArguments([$config['secret']]);
        $container->register(AutosaveLabels::class, AutosaveLabels::class)->setArguments([new Reference('translator', $optional)]);
        $container->register(DoneCookie::class, DoneCookie::class)
            ->setArguments([new Reference('clock'), new Reference(Drafts::class, $optional)])
            ->addTag('kernel.event_listener', ['event' => KernelEvents::RESPONSE]);

        if ($server['enabled']) {
            self::server($container, $config['ttl'], $server);
        }

        $container->register(AutosaveTypeExtension::class, AutosaveTypeExtension::class)
            ->setArguments([
                new Reference(DraftKeys::class),
                new Reference(AutosaveLabels::class),
                new Reference('request_stack'),
                $config['ttl'],
                $config['debounce'],
                $config['max_bytes'],
                $server['enabled'],
                new Reference(DraftOwner::class, $optional),
                new Reference(Drafts::class, $optional),
                new Reference('router', $optional),
                new Reference(DraftRequest::class, $optional),
            ])
            ->addTag('form.type_extension');
    }

    /**
     * @param ServerConfig $server
     */
    private static function server(ContainerBuilder $container, int $seconds, array $server): void
    {
        if ('memory' === $server['store']) {
            $container->register(InMemoryDraftStore::class, InMemoryDraftStore::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(DraftStore::class, InMemoryDraftStore::class)->setPublic(true);
        } else {
            $container->register(DoctrineDraftStore::class, DoctrineDraftStore::class)
                ->setArguments([new Reference('doctrine.dbal.'.$server['connection'].'_connection')]);
            $container->setAlias(DraftStore::class, DoctrineDraftStore::class)->setPublic(true);
        }

        if (!$server['encrypt']) {
            $container->register(PlainSealer::class, PlainSealer::class);
            $container->setAlias(DraftSealer::class, PlainSealer::class);
        }

        $container->setParameter(self::KEY, $server['key']);
        $container->setParameter(self::KMS, $server['kms']);

        $container->register(Drafts::class, Drafts::class)
            ->setArguments([
                new Reference(DraftStore::class),
                new Reference(DraftSealer::class),
                new Reference(DraftKeys::class),
                new Reference('clock'),
                $seconds,
                $server['max_bytes'],
            ])
            ->setPublic(true);
        $container->register(DraftRequest::class, DraftRequest::class)
            ->setArguments([new Reference(ServerStorePass::TOKEN_MANAGER)]);
        $container->register(DraftController::class, DraftController::class)
            ->setArguments([
                new Reference(Drafts::class),
                new Reference(DraftRequest::class),
                new Reference(DraftOwner::class),
                $server['max_bytes'],
            ])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
        $container->register(PurgeCommand::class, PurgeCommand::class)
            ->setArguments([new Reference(Drafts::class)])
            ->addTag('console.command');
    }
}
