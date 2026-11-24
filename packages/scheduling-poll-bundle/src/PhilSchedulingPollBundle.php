<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle;

use Override;
use Phil\SchedulingPollBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\SchedulingPollBundle\Infrastructure\DependencyInjection\OptionalServicesPass;
use Phil\SchedulingPollBundle\Infrastructure\DependencyInjection\SchedulingPollServices;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Polls of availabilities to choose a date: the organizer proposes dates, each respondent answers yes, if need be or
 * no by a personal link of `phil/access-links`, without an account, and the date is fixed by the organizer or, at the
 * deadline, by the answers counted on the voting engine of `phil/poll`; an iCalendar file is then sent to every
 * respondent through Messenger. The tables are mapped for Doctrine; the templates draw their parts with recipes of the
 * PHIL UX kit installed by the Symfony UX Toolkit.
 *
 * ```yaml
 * phil_scheduling_poll:
 *     links:
 *         keys: ['%env(SCHEDULING_POLL_LINK_KEY)%']
 *         page: 'https://example.org/scheduling-poll/answer'
 * ```
 */
final class PhilSchedulingPollBundle extends AbstractBundle
{
    /** An ISO 8601 duration in its designator form, with at least one part. */
    private const string DURATION = '/^P(?=\d|T\d)(?:\d+Y)?(?:\d+M)?(?:\d+W)?(?:\d+D)?(?:T(?=\d)(?:\d+H)?(?:\d+M)?(?:\d+S)?)?$/D';

    #[Override]
    protected string $extensionAlias = 'phil_scheduling_poll';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('store')
                    ->info('Where polls and respondents are kept: "doctrine", or "memory" for tests.')
                    ->values(['doctrine', 'memory'])
                    ->defaultValue('doctrine')
                ->end()
                ->scalarNode('entity_manager')
                    ->info('The Doctrine entity manager of the tables of the polls.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->scalarNode('connection')
                    ->info('The Doctrine DBAL connection of the tables of the links.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->arrayNode('links')
                    ->info('The personal links of the respondents.')
                    ->isRequired()
                    ->children()
                        ->arrayNode('keys')
                            ->info("The keys of the links' keyed hash, 32 bytes or more each: the first hashes, every key finds; up to eight, for a rotation.")
                            ->isRequired()
                            ->requiresAtLeastOneElement()
                            ->scalarPrototype()->end()
                        ->end()
                        ->scalarNode('page')
                            ->info('The absolute address of the answer page, the route phil_scheduling_poll_answer: the links point to it.')
                            ->isRequired()
                            ->cannotBeEmpty()
                            ->validate()
                                ->ifTrue(static fn (mixed $page): bool => !self::isPage($page))
                                ->thenInvalid('The page of the links is an absolute address without a fragment, such as "https://example.org/scheduling-poll/answer"; %s is not.')
                            ->end()
                        ->end()
                        ->scalarNode('parameter')
                            ->info('The query parameter that carries the token.')
                            ->defaultValue('token')
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('link_lifetime')
                    ->info('How long a link still opens after the deadline, an ISO 8601 duration: the names and addresses of the respondents are erased that long after the closing.')
                    ->defaultValue('P30D')
                    ->validate()
                        ->ifTrue(static fn (mixed $duration): bool => !\is_string($duration) || 1 !== preg_match(self::DURATION, $duration))
                        ->thenInvalid('The lifetime of a link is an ISO 8601 duration, such as "P30D"; %s is not.')
                    ->end()
                ->end()
                ->integerNode('max_respondents')
                    ->info('The most respondents a poll takes.')
                    ->min(1)
                    ->max(10000)
                    ->defaultValue(500)
                ->end()
                ->arrayNode('closing')
                    ->info('The closing of the polls past their deadline, by the schedule "phil_scheduling_poll".')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('frequency')->info('How often the schedule closes the polls past their deadline.')->defaultValue('5 minutes')->end()
                        ->integerNode('batch')->info('The polls closed at most each time.')->min(1)->max(1000)->defaultValue(100)->end()
                    ->end()
                ->end()
                ->arrayNode('erasure')
                    ->info('The erasure of the names and addresses, by the schedule "phil_scheduling_poll".')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('frequency')->info('How often the schedule erases what is past its delay.')->defaultValue('1 hour')->end()
                    ->end()
                ->end()
                ->arrayNode('schedule')
                    ->info('What the schedule keeps its state in and locks with.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('cache')->info('The cache pool of its state.')->defaultValue('cache.app')->end()
                        ->scalarNode('lock_factory')->info('The lock factory of its lock.')->defaultValue('lock.factory')->end()
                    ->end()
                ->end()
                ->arrayNode('mailer')
                    ->info('The mail of the date chosen.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('from')->info('Its sender, "Name <address>"; the headers of the Mailer when null.')->defaultNull()->end()
                    ->end()
                ->end()
                ->scalarNode('respondent_addresses')
                    ->info('The service, a RespondentAddresses, that finds the address of a respondent who joined with an account; none by default.')
                    ->defaultNull()
                ->end()
                ->arrayNode('privacy')
                    ->info('The export and the erasure through phil/privacy-bundle.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('account_kind')->info('The kind of the references of the accounts the data subjects are.')->defaultValue('user')->end()
                    ->end()
                ->end()
                ->scalarNode('kit_subdirectory')
                    ->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null.')
                    ->defaultNull()
                ->end()
            ->end();
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new KitSubdirectoryPass());
        $container->addCompilerPass(new OptionalServicesPass());
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilSchedulingPollBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\SchedulingPollBundle\Domain\Model',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{store: string, entity_manager: string, connection: string, links: array{keys: list<string>, page: string, parameter: string}, link_lifetime: string, max_respondents: int, closing: array{frequency: string, batch: int}, erasure: array{frequency: string}, schedule: array{cache: string, lock_factory: string}, mailer: array{from: ?string}, respondent_addresses: ?string, privacy: array{account_kind: string}, kit_subdirectory: ?string} $config */
        SchedulingPollServices::register($builder, $config);
    }

    private static function isPage(mixed $page): bool
    {
        try {
            return \is_string($page) && null === Url::fromString($page)->fragment;
        } catch (InvalidUrl) {
            return false;
        }
    }
}
