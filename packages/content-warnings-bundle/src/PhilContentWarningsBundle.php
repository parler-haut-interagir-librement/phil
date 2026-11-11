<?php

declare(strict_types=1);

namespace Phil\ContentWarningsBundle;

use Doctrine\DBAL\Types\Type;
use Override;
use Phil\ContentWarnings\Catalogue;
use Phil\ContentWarnings\Display;
use Phil\ContentWarningsBundle\Application\CatalogueFactory;
use Phil\ContentWarningsBundle\Application\CatalogueLabels;
use Phil\ContentWarningsBundle\Application\Contract\WarningPreferencesStore;
use Phil\ContentWarningsBundle\Application\WarningScreen;
use Phil\ContentWarningsBundle\Infrastructure\Doctrine\ContentWarningsColumnType;
use Phil\ContentWarningsBundle\Infrastructure\Form\ContentWarningsType;
use Phil\ContentWarningsBundle\Infrastructure\Form\WarningPreferencesType;
use Phil\ContentWarningsBundle\Infrastructure\Memory\InMemoryWarningPreferences;
use Phil\ContentWarningsBundle\Infrastructure\Session\SessionWarningPreferences;
use Phil\ContentWarningsBundle\Infrastructure\Twig\ContentWarningsExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

/**
 * `phil/content-warnings` in a Symfony application: the catalogue built from the base list and the application's
 * own definitions, the column type `phil_content_warnings`, the fields that pick warnings and preferences, the Twig
 * functions and the folded display, and the store of the current member's preferences.
 *
 * The catalogue is built once when the container is compiled, so a definition that breaks a rule stops the build.
 */
final class PhilContentWarningsBundle extends AbstractBundle
{
    public const string FOLD_TEMPLATE = '@PhilContentWarnings/fold.html.twig';

    public const string FORM_THEME = '@PhilContentWarnings/form/theme.html.twig';

    #[Override]
    protected string $extensionAlias = 'phil_content_warnings';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('base_catalogue')
                    ->info('Starts from the base list of the library; false starts from an empty catalogue.')
                    ->defaultTrue()
                ->end()
                ->arrayNode('catalogue_files')
                    ->info('PHP or JSON files of catalogue definitions, applied in order after the base list.')
                    ->scalarPrototype()->end()
                ->end()
                ->variableNode('catalogue')
                    ->info('A catalogue definition applied last: categories, themes, remove, remove_categories, replace.')
                    ->defaultValue([])
                    ->validate()
                        ->ifTrue(static fn (mixed $value): bool => !\is_array($value))
                        ->thenInvalid('The catalogue is a definition array: %s.')
                    ->end()
                ->end()
                ->enumNode('default_display')
                    ->info('What happens to warned content for a visitor who chose nothing: "show", "fold" or "hide".')
                    ->values(array_map(static fn (Display $display): string => $display->value, Display::cases()))
                    ->defaultValue(Display::Fold->value)
                ->end()
                ->arrayNode('fallback_locales')
                    ->info('The locales a label is read in when it has none in the current locale, in order.')
                    ->scalarPrototype()->end()
                    ->defaultValue(['en'])
                ->end()
                ->scalarNode('preferences_store')
                    ->info('The service of WarningPreferencesStore; the session store by default, in memory without HttpFoundation.')
                    ->defaultNull()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine') && class_exists(Type::class)) {
            $container->extension('doctrine', ['dbal' => ['types' => [ContentWarningsColumnType::NAME => ContentWarningsColumnType::class]]]);
        }

        if ($builder->hasExtension('twig')) {
            $container->extension('twig', ['form_themes' => [self::FORM_THEME]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{base_catalogue: bool, catalogue_files: list<string>, catalogue: array<array-key, mixed>, default_display: string, fallback_locales: list<string>, preferences_store: string|null} $config */
        $config = $builder->getParameterBag()->resolveValue($config);
        $factory = [$config['base_catalogue'], $config['catalogue_files'], $config['catalogue']];
        new CatalogueFactory(...$factory)->create();
        $default = Display::from($config['default_display']);

        $services = $container->services();
        $services->set(CatalogueFactory::class)->args($factory);
        $services->set(Catalogue::class)->factory([new Reference(CatalogueFactory::class), 'create']);
        $services->set(CatalogueLabels::class)->args([new Reference(Catalogue::class), $config['fallback_locales']]);
        $services->set(InMemoryWarningPreferences::class)->args([$default])->tag('kernel.reset', ['method' => 'reset']);
        $store = InMemoryWarningPreferences::class;

        if (class_exists(RequestStack::class)) {
            $services->set(SessionWarningPreferences::class)->args([new Reference('request_stack'), $default]);
            $store = SessionWarningPreferences::class;
        }

        $services->alias(WarningPreferencesStore::class, $config['preferences_store'] ?? $store);
        $services->set(WarningScreen::class)->args([new Reference(Catalogue::class), new Reference(WarningPreferencesStore::class)]);
        $services->set(ContentWarningsType::class)->args([new Reference(CatalogueLabels::class)])->tag('form.type');
        $services->set(WarningPreferencesType::class)->args([new Reference(CatalogueLabels::class), $default])->tag('form.type');

        if (class_exists(AsTwigFunction::class)) {
            $services->set(ContentWarningsExtension::class)
                ->args([new Reference(WarningScreen::class), new Reference(CatalogueLabels::class)])
                ->tag('twig.attribute_extension')
                ->tag('twig.runtime');
        }
    }
}
