<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheckBundle;

use Override;
use Phil\RouteAccessCheck\Contract\AccessRuleDetector;
use Phil\RouteAccessCheck\Contract\ReportFormatter;
use Phil\RouteAccessCheck\ControllerLocator;
use Phil\RouteAccessCheck\Detector\PublicRouteDetector;
use Phil\RouteAccessCheck\Detector\SecurityAttributeDetector;
use Phil\RouteAccessCheck\Detector\SecurityConfigDetector;
use Phil\RouteAccessCheck\Format\GitHubFormatter;
use Phil\RouteAccessCheck\Format\JsonFormatter;
use Phil\RouteAccessCheck\Format\JunitFormatter;
use Phil\RouteAccessCheck\Format\TextFormatter;
use Phil\RouteAccessCheck\IgnoreList;
use Phil\RouteAccessCheck\RouteAccessChecker;
use Phil\RouteAccessCheck\RouteReader;
use Phil\RouteAccessCheck\Security\SecurityMap;
use Phil\RouteAccessCheckBundle\Infrastructure\Console\CheckRouteAccessCommand;
use Phil\RouteAccessCheckBundle\Infrastructure\DependencyInjection\SecurityMapPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The route access check as a console command of the application, `phil:route-access:check`, over its router and the
 * firewalls and access_control entries of its security configuration:
 *
 * ```yaml
 * phil_route_access_check:
 *     baseline: '%kernel.project_dir%/route-access-baseline.json'
 *     ignore:
 *         paths: [/_profiler, /_wdt, /_error, /.well-known/, /healthz]
 * ```
 *
 * A dev and CI tool: it registers its services only in the environments of `environments`, `dev` and `test` by
 * default, and nothing in any other — no listener, no runtime cost. Detectors and formats of the application's own are
 * added by implementing AccessRuleDetector or ReportFormatter, autoconfigured.
 */
final class PhilRouteAccessCheckBundle extends AbstractBundle
{
    public const string DETECTOR_TAG = 'phil_route_access_check.detector';

    public const string FORMATTER_TAG = 'phil_route_access_check.formatter';

    public const string SECURITY_MAP = SecurityMapPass::SECURITY_MAP;

    public const string CONTROLLER_CLASSES = SecurityMapPass::CONTROLLER_CLASSES;

    #[Override]
    protected string $extensionAlias = 'phil_route_access_check';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('environments')
                    ->info('The kernel environments in which the check is registered; in the others, the bundle registers nothing.')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue(['dev', 'test'])
                ->end()
                ->scalarNode('baseline')
                    ->info('The baseline file whose failures are tolerated, such as "%kernel.project_dir%/route-access-baseline.json"; null, none.')
                    ->defaultNull()
                ->end()
                ->scalarNode('public_option')
                    ->info('The route option that declares a route public, set to true or to the reason.')
                    ->defaultValue(PublicRouteDetector::DEFAULT_OPTION)
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('attributes')
                    ->info('The attribute classes read as access checks on the controllers, with the arguments of #[IsGranted].')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue([SecurityAttributeDetector::IS_GRANTED])
                ->end()
                ->arrayNode('ignore')
                    ->info('The routes left aside, by name or path prefix; a list given replaces its default.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('route_names')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                            ->defaultValue(IgnoreList::FRAMEWORK_ROUTE_NAMES)
                        ->end()
                        ->arrayNode('paths')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                            ->defaultValue(IgnoreList::FRAMEWORK_PATHS)
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new SecurityMapPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{environments: list<string>, baseline: ?string, public_option: string, attributes: list<string>, ignore: array{route_names: list<string>, paths: list<string>}} $config */
        $environment = $builder->hasParameter('kernel.environment') ? $builder->getParameter('kernel.environment') : null;

        if (!\in_array($environment, $config['environments'], true)) {
            return;
        }

        $builder->registerForAutoconfiguration(AccessRuleDetector::class)->addTag(self::DETECTOR_TAG);
        $builder->registerForAutoconfiguration(ReportFormatter::class)->addTag(self::FORMATTER_TAG);
        $builder->setParameter(self::SECURITY_MAP, []);
        $builder->setParameter(self::CONTROLLER_CLASSES, []);

        $services = $container->services();
        $services->set(SecurityMap::class)->factory(SecurityMap::fromArray(...))->args(['%'.self::SECURITY_MAP.'%']);
        $services->set(ControllerLocator::class)->args(['%'.self::CONTROLLER_CLASSES.'%']);
        $services->set(RouteReader::class)->args([new Reference(ControllerLocator::class)]);
        $services->set(IgnoreList::class)->args([$config['ignore']['route_names'], $config['ignore']['paths']]);

        $services->set(SecurityAttributeDetector::class)->args([$config['attributes']])->tag(self::DETECTOR_TAG);
        $services->set(PublicRouteDetector::class)->args([$config['public_option']])->tag(self::DETECTOR_TAG);
        $services->set(SecurityConfigDetector::class)->args([new Reference(SecurityMap::class)])->tag(self::DETECTOR_TAG);

        $services->set(TextFormatter::class)->tag(self::FORMATTER_TAG);
        $services->set(JsonFormatter::class)->tag(self::FORMATTER_TAG);
        $services->set(GitHubFormatter::class)->args(['%kernel.project_dir%'])->tag(self::FORMATTER_TAG);
        $services->set(JunitFormatter::class)->tag(self::FORMATTER_TAG);

        $services->set(RouteAccessChecker::class)
            ->args([new TaggedIteratorArgument(self::DETECTOR_TAG), new Reference(IgnoreList::class), new Reference(RouteReader::class)]);

        $services->set(CheckRouteAccessCommand::class)
            ->args([new Reference(RouteAccessChecker::class), new Reference('router'), new TaggedIteratorArgument(self::FORMATTER_TAG), $config['baseline']])
            ->tag('console.command');
    }
}
