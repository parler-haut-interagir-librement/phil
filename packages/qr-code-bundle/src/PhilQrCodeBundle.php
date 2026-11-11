<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle;

use Override;
use Phil\QrCode\QrCode;
use Phil\QrCodeBundle\Infrastructure\DependencyInjection\QrCodeServices;
use Phil\QrCodeBundle\Infrastructure\DependencyInjection\SvgBlocksPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * phil/qr-code in a Symfony application: the defaults of every code, `qr_code()` in Twig, the accessible component
 * `<twig:PhilQrCode:QrCode>`, the signed route that serves codes as SVG or PNG, the `qrCode` blocks of UJO documents
 * drawn for phil/svg-blocks-bundle, and codes drawn in PDF documents of phil/paged-media.
 */
final class PhilQrCodeBundle extends AbstractBundle
{
    private const string COLOUR = '/^#[0-9a-fA-F]{6}$/D';

    #[Override]
    protected string $extensionAlias = 'phil_qr_code';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('error_correction')->info('The level of error correction: L, M, Q or H.')->values(['L', 'M', 'Q', 'H'])->defaultValue('M')->end()
                ->integerNode('quiet_zone')->info('The light modules around a code; the standard asks for 4.')->defaultValue(4)->min(0)->max(16)->end()
                ->scalarNode('dark')
                    ->info('The colour of the dark modules, #rrggbb.')
                    ->defaultValue('#000000')
                    ->validate()->ifTrue(static fn (mixed $colour): bool => !\is_string($colour) || 1 !== preg_match(self::COLOUR, $colour))->thenInvalid('%s is not a colour written #rrggbb.')->end()
                ->end()
                ->scalarNode('light')
                    ->info('The colour of the light modules, #rrggbb.')
                    ->defaultValue('#ffffff')
                    ->validate()->ifTrue(static fn (mixed $colour): bool => !\is_string($colour) || 1 !== preg_match(self::COLOUR, $colour))->thenInvalid('%s is not a colour written #rrggbb.')->end()
                ->end()
                ->integerNode('size')->info('The width and height of an SVG in pixels; 0 lets its container size it.')->defaultValue(0)->min(0)->max(4096)->end()
                ->integerNode('png_scale')->info('The pixels on a side of a module of a PNG.')->defaultValue(8)->min(1)->max(32)->end()
                ->booleanNode('eci')->info('Whether bytes beyond ASCII are announced as UTF-8 by an ECI header.')->defaultTrue()->end()
                ->booleanNode('kanji')->info('Whether the double-byte characters of Shift JIS are written in the kanji mode.')->defaultTrue()->end()
                ->integerNode('max_bytes')->info('The longest payload a code may hold, in bytes.')->defaultValue(2048)->min(1)->max(7089)->end()
                ->arrayNode('logos')
                    ->info('The logos by name, each a file and the share of the side of the code its zone takes.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('path')->isRequired()->cannotBeEmpty()->end()
                            ->floatNode('zone')->defaultValue(0.2)->min(0.05)->max(QrCode::MAX_LOGO_ZONE)->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('route')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->info('Whether codes are served as images at signed addresses; import config/routes.php too.')->defaultFalse()->end()
                        ->integerNode('cache_max_age')->info('The seconds a served code may be kept by browsers and shared caches.')->defaultValue(31_536_000)->min(0)->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new SvgBlocksPass());
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{error_correction: string, quiet_zone: int, dark: string, light: string, size: int, png_scale: int, eci: bool, kanji: bool, max_bytes: int, logos: array<string, array{path: string, zone: float}>, route: array{enabled: bool, cache_max_age: int}} $config */
        QrCodeServices::register($builder, $config);
    }
}
