<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle;

use LogicException;
use Override;
use Phil\CommerceSyndicationBundle\Application\Courier;
use Phil\CommerceSyndicationBundle\Application\Forwarder;
use Phil\CommerceSyndicationBundle\Application\Inbox;
use Phil\CommerceSyndicationBundle\Application\Ledger;
use Phil\CommerceSyndicationBundle\Application\MessageRetention;
use Phil\CommerceSyndicationBundle\Application\PartnerReturns;
use Phil\CommerceSyndicationBundle\Application\Partners;
use Phil\CommerceSyndicationBundle\Application\Port\ForwardedOrderHandler;
use Phil\CommerceSyndicationBundle\Application\Port\MessageTransport;
use Phil\CommerceSyndicationBundle\Application\Port\PartnerReturnHandler;
use Phil\CommerceSyndicationBundle\Application\Port\PartnerShipmentHandler;
use Phil\CommerceSyndicationBundle\Application\Publisher;
use Phil\CommerceSyndicationBundle\Application\ShipmentTracking;
use Phil\CommerceSyndicationBundle\Application\SyndicatedSellables;
use Phil\CommerceSyndicationBundle\Infrastructure\Console\DeliverMessagesCommand;
use Phil\CommerceSyndicationBundle\Infrastructure\Console\PurgeMessagesCommand;
use Phil\CommerceSyndicationBundle\Infrastructure\Doctrine\DoctrineSyndicationRepository;
use Phil\CommerceSyndicationBundle\Infrastructure\Doctrine\FedSkuIndex;
use Phil\CommerceSyndicationBundle\Infrastructure\Http\HttpClientTransport;
use Phil\CommerceSyndicationBundle\Infrastructure\Http\SyndicationWebhookController;
use ReflectionClass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * Syndication, a module of the core of commerce: catalogues shared between distinct applications. A supplier feeds
 * its resellers signed messages — prices, stock —; a reseller sells the products as its own and forwards each order
 * to the supplier, who ships it; a ledger keeps what each owes the other.
 *
 * The messages are Standard Webhooks signed with Ed25519, through `phil/standard-webhooks`; each partner is
 * known by its public key. The module plugs into `phil/commerce-bundle`, and refuses to load without it. With
 * DoctrineBundle, partners, messages, syndicated products and the ledger are mapped and stored.
 */
final class PhilCommerceSyndicationBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_commerce_syndication';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('identity')
                    ->info("This application's name at its partners: the audience of the messages they send it.")
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('signing_key')
                    ->info('This application\'s "whsk_" Ed25519 secret key, from the environment; its public key goes to the partners.')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->integerNode('tolerance')
                    ->info("How many seconds a message's timestamp may stray from now.")
                    ->defaultValue(300)
                    ->min(1)
                ->end()
                ->integerNode('max_attempts')
                    ->info('How many delivery attempts a message gets before it is given up.')
                    ->defaultValue(8)
                    ->min(1)
                    ->max(20)
                ->end()
                ->integerNode('retention_days')
                    ->info('How many days a message is kept once delivered, given up or received; its personal data is cleared as soon as it is delivered or given up.')
                    ->defaultValue(90)
                    ->min(1)
                ->end()
                ->integerNode('cost_grace_days')
                    ->info('On a supplier: how many days a cost it published to a reseller still pays a forwarded line after the next price replaced it.')
                    ->defaultValue(7)
                    ->min(0)
                ->end()
            ->end();
    }

    /**
     * The check runs while extensions prepend, the one moment the container knows every registered extension;
     * each extension then loads in a container of its own.
     */
    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('phil_commerce')) {
            throw new LogicException('PhilCommerceSyndicationBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.');
        }

        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        $container->extension('doctrine', ['orm' => ['mappings' => ['PhilCommerceSyndicationBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => __DIR__.'/Domain/Model',
            'prefix' => 'Phil\CommerceSyndicationBundle\Domain\Model',
        ]]]]);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();
        $builder->registerForAutoconfiguration(ForwardedOrderHandler::class)->addTag('phil_commerce_syndication.forwarded_order_handler');
        $builder->registerForAutoconfiguration(PartnerShipmentHandler::class)->addTag('phil_commerce_syndication.shipment_handler');
        $builder->registerForAutoconfiguration(PartnerReturnHandler::class)->addTag('phil_commerce_syndication.return_handler');

        // Every service reads a repository: Doctrine's, below. An application storing the module's data otherwise
        // declares the services itself, with its own adapters.
        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        $services->set(SyndicatedSellables::class)->autowire()
            ->tag('phil_commerce.sellable_resolver')
            ->tag('phil_commerce.sellable_availability');

        foreach ([Partners::class, Publisher::class, Forwarder::class, Ledger::class] as $service) {
            $services->set($service)->autowire()->public();
        }

        $services->set(ShipmentTracking::class)->autowire()->public()
            ->arg('$handlers', tagged_iterator('phil_commerce_syndication.shipment_handler'));
        $services->set(PartnerReturns::class)->autowire()->public()
            ->arg('$handlers', tagged_iterator('phil_commerce_syndication.return_handler'));

        $services->set(Inbox::class)->autowire()->public()
            ->arg('$identity', $config['identity'])
            ->arg('$tolerance', $config['tolerance'])
            ->arg('$costGraceDays', $config['cost_grace_days'])
            ->arg('$handlers', tagged_iterator('phil_commerce_syndication.forwarded_order_handler'));

        $services->set(Courier::class)->autowire()->public()
            ->arg('$signingKey', $config['signing_key'])
            ->arg('$maxAttempts', $config['max_attempts']);

        $services->set(MessageRetention::class)->autowire()->public()
            ->arg('$retentionDays', $config['retention_days']);

        $services->set(SyndicationWebhookController::class)->autowire()->public()->tag('controller.service_arguments');

        // The transport over Symfony's HTTP client when it is installed; an application without it declares its own.
        if (interface_exists(HttpClientInterface::class)) {
            $services->set(HttpClientTransport::class)->autowire();
            $services->alias(MessageTransport::class, HttpClientTransport::class);
        }

        if (class_exists(Command::class)) {
            $services->set(DeliverMessagesCommand::class)->autowire()->tag('console.command');
            $services->set(PurgeMessagesCommand::class)->autowire()->tag('console.command');
        }

        // The index of the SKUs each reseller is fed, kept in step with the partners at each flush.
        $services->set(FedSkuIndex::class)->tag('doctrine.event_listener', ['event' => 'onFlush']);

        // The ports by reflection: the bundle class wires adapters, and names no class of the domain.
        foreach ([DoctrineSyndicationRepository::class] as $adapter) {
            $services->set($adapter)->args([service('doctrine.orm.entity_manager')]);

            foreach (new ReflectionClass($adapter)->getInterfaceNames() as $port) {
                $services->alias($port, $adapter)->public();
            }
        }
    }

    private function hasBundle(ContainerBuilder $builder, string $bundle): bool
    {
        // Through the bag rather than `getParameter()`, which static analysis types from the container of
        // whatever application analyses the bundle, not from the kernel that loads it.
        $bundles = $builder->getParameterBag()->all()['kernel.bundles'] ?? null;

        return \is_array($bundles) && isset($bundles[$bundle]);
    }
}
