<?php

declare(strict_types=1);

namespace Phil\ContactBundle;

use Doctrine\DBAL\Types\Type;
use Override;
use Phil\Contact\JSContactReader;
use Phil\ContactBundle\Infrastructure\Doctrine\ContactCardColumnType;
use Phil\ContactBundle\Infrastructure\Twig\ContactExtension;
use Phil\StructuredData\Contract\NodeCollector;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Attribute\AsTwigFunction;

/**
 * `phil/contact` in a Symfony application: the card form and the person name form, the column type `phil_contact`,
 * cards and person names in Twig with the form theme that adds and removes entries, and the JSContact reader as a
 * service, its bounds in configuration. With phil/structured-data-bundle, `contact_json_ld` adds the card to the graph
 * of the page.
 *
 * The card form uses the fields of `phil/phone-bundle`, `phil/url-bundle` and `phil/instant-messenger-bundle`:
 * register those bundles too.
 */
final class PhilContactBundle extends AbstractBundle
{
    public const string FORM_THEME = '@PhilContact/form/theme.html.twig';

    #[Override]
    protected string $extensionAlias = 'phil_contact';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('jscontact')
                    ->info('The bounds of the JSContact reader, and the country of the phone numbers it reads without their country code.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('max_bytes')->min(1)->defaultValue(65_536)->info('The longest card read, in bytes.')->end()
                        ->integerNode('max_depth')->min(2)->defaultValue(16)->info('The deepest nesting of its JSON.')->end()
                        ->integerNode('max_entries')->min(1)->defaultValue(100)->info('The most entries of one map or list.')->end()
                        ->scalarNode('region')->defaultNull()->info('The ISO 3166-1 alpha-2 code of the country of the phone numbers written without their country code: "CA".')->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine') && class_exists(Type::class)) {
            $container->extension('doctrine', ['dbal' => ['types' => [ContactCardColumnType::NAME => ContactCardColumnType::class]]]);
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
        /** @var array{max_bytes: int, max_depth: int, max_entries: int, region: ?string} $jscontact */
        $jscontact = $config['jscontact'];
        $container->services()->set(JSContactReader::class)
            ->args([$jscontact['max_bytes'], $jscontact['max_depth'], $jscontact['max_entries'], $jscontact['region']]);

        if (class_exists(AsTwigFunction::class)) {
            $container->services()->set(ContactExtension::class)
                ->args([new Reference(NodeCollector::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)])
                ->tag('twig.attribute_extension')
                ->tag('twig.runtime');
        }
    }
}
