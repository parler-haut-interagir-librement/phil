<?php

declare(strict_types=1);

namespace Phil\GeoBundle;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Override;
use Phil\GeoBundle\Infrastructure\DependencyInjection\GeocodingServices;
use Phil\GeoBundle\Infrastructure\Form\CoordinatesType;
use Phil\GeoBundle\Infrastructure\Twig\GeoExtension;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Twig\Attribute\AsTwigFilter;

/**
 * `phil/geo` in a Symfony application: coordinates as a Doctrine embeddable, a form type, distances in Twig, the
 * geocoders and the IP locator of the configuration, and the Stimulus controller that sorts places by their distance
 * to the visitor in the browser.
 */
final class PhilGeoBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_geo';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('doctrine_mapping')
                    ->info('Maps Phil\Geo\Coordinates as an embeddable on the ORM.')
                    ->defaultTrue()
                ->end()
                ->arrayNode('geocoding')
                    ->addDefaultsIfNotSet()
                    ->validate()
                        ->ifTrue(static fn (array $geocoding): bool => null !== self::geocodingRefusal($geocoding))
                        ->then(static function (array $geocoding): never {
                            throw new InvalidArgumentException((string) self::geocodingRefusal($geocoding));
                        })
                    ->end()
                    ->children()
                        ->arrayNode('providers')
                            ->info('The geocoders asked, in this order: nominatim, google_maps, nrcan, photon, opencage, here, mapbox, azure_maps, adresses_quebec. None, and no Geocoder is registered.')
                            ->enumPrototype()->values(GeocodingServices::PROVIDERS)->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode('nominatim')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('user_agent')->info('The site and how to reach it, as the usage policy of Nominatim asks.')->defaultNull()->end()
                                ->scalarNode('base_url')->defaultValue(GeocodingServices::NOMINATIM_URL)->cannotBeEmpty()->end()
                                ->integerNode('limit')->info('The most places an address gives.')->defaultValue(5)->min(1)->max(40)->end()
                            ->end()
                        ->end()
                        ->arrayNode('google_maps')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('api_key')->defaultNull()->end()
                                ->scalarNode('base_url')->defaultValue(GeocodingServices::GOOGLE_MAPS_URL)->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                        ->arrayNode('nrcan')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('base_url')->defaultValue(GeocodingServices::NRCAN_URL)->cannotBeEmpty()->end()
                                ->integerNode('limit')->info('The most places an address gives.')->defaultValue(5)->min(1)->max(40)->end()
                            ->end()
                        ->end()
                        ->arrayNode('photon')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('base_url')->info("The public server, or one's own.")->defaultValue(GeocodingServices::PHOTON_URL)->cannotBeEmpty()->end()
                                ->integerNode('limit')->info('The most places an address gives.')->defaultValue(5)->min(1)->max(40)->end()
                                ->arrayNode('languages')
                                    ->info('The languages the server knows; a locale in another is not sent, and places come in their local names.')
                                    ->scalarPrototype()->cannotBeEmpty()->end()
                                    ->defaultValue(GeocodingServices::PHOTON_LANGUAGES)
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('opencage')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('api_key')->defaultNull()->end()
                                ->scalarNode('base_url')->defaultValue(GeocodingServices::OPENCAGE_URL)->cannotBeEmpty()->end()
                                ->integerNode('limit')->info('The most places an address gives.')->defaultValue(5)->min(1)->max(100)->end()
                            ->end()
                        ->end()
                        ->arrayNode('here')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('api_key')->defaultNull()->end()
                                ->scalarNode('geocode_url')->defaultValue(GeocodingServices::HERE_GEOCODE_URL)->cannotBeEmpty()->end()
                                ->scalarNode('reverse_url')->defaultValue(GeocodingServices::HERE_REVERSE_URL)->cannotBeEmpty()->end()
                                ->integerNode('limit')->info('The most places an address or a point gives.')->defaultValue(5)->min(1)->max(100)->end()
                            ->end()
                        ->end()
                        ->arrayNode('mapbox')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('access_token')->defaultNull()->end()
                                ->scalarNode('base_url')->defaultValue(GeocodingServices::MAPBOX_URL)->cannotBeEmpty()->end()
                                ->integerNode('limit')->info('The most places an address gives.')->defaultValue(5)->min(1)->max(10)->end()
                                ->booleanNode('permanent')->info('Asks for answers that may be kept, which Mapbox bills; temporary answers may not be cached.')->defaultFalse()->end()
                            ->end()
                        ->end()
                        ->arrayNode('azure_maps')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('subscription_key')->defaultNull()->end()
                                ->scalarNode('base_url')->defaultValue(GeocodingServices::AZURE_MAPS_URL)->cannotBeEmpty()->end()
                                ->integerNode('limit')->info('The most places an address gives.')->defaultValue(5)->min(1)->max(20)->end()
                                ->scalarNode('api_version')->defaultValue(GeocodingServices::AZURE_MAPS_API_VERSION)->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                        ->arrayNode('adresses_quebec')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('base_url')->info('The geocode server of the address register of Québec.')->defaultValue(GeocodingServices::ADRESSES_QUEBEC_URL)->cannotBeEmpty()->end()
                                ->integerNode('limit')->info('The most places an address gives.')->defaultValue(5)->min(1)->max(40)->end()
                            ->end()
                        ->end()
                        ->arrayNode('cache')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('pool')->info('A cache pool that keeps what the geocoders answered: cache.app.')->defaultNull()->end()
                                ->integerNode('lifetime')->info('How many seconds an answer is kept.')->defaultValue(604800)->min(1)->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('ip_locator')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('maxmind')
                            ->addDefaultsIfNotSet()
                            ->validate()
                                ->ifTrue(static fn (array $maxmind): bool => null !== self::maxMindRefusal($maxmind))
                                ->then(static function (array $maxmind): never {
                                    throw new InvalidArgumentException((string) self::maxMindRefusal($maxmind));
                                })
                            ->end()
                            ->children()
                                ->scalarNode('database')->info('The path of a MaxMind city database, GeoLite2-City.mmdb: registers it as IpLocator, before ipstack.')->defaultNull()->end()
                                ->scalarNode('locale')->info('The language of the names, English when the database has none in it.')->defaultValue('en')->cannotBeEmpty()->end()
                                ->scalarNode('account_id')->info('The MaxMind account that phil:geo:maxmind:update downloads the database with; with the licence key, registers that command, which needs the http_client service.')->defaultNull()->end()
                                ->scalarNode('license_key')->info('A licence key of that account.')->defaultNull()->end()
                                ->scalarNode('edition')->info('The edition downloaded: GeoLite2-City, GeoIP2-City.')->defaultValue(GeocodingServices::MAXMIND_EDITION)->cannotBeEmpty()->end()
                                ->scalarNode('download_url')->defaultValue(GeocodingServices::MAXMIND_DOWNLOAD_URL)->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                        ->arrayNode('ipstack')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('access_key')->info('Registers the ipstack locator as IpLocator.')->defaultNull()->end()
                                ->scalarNode('base_url')->info("HTTPS; a plan that answers over plain HTTP only sends the visitor's IP address and the access key in clear.")->defaultValue(GeocodingServices::IPSTACK_URL)->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('doctrine') || !interface_exists(EntityManagerInterface::class)) {
            return;
        }

        // The bundle's own tree, read before its extension loads: the mapping must reach DoctrineBundle first.
        /** @var array{doctrine_mapping: bool} $config */
        $config = new Processor()->processConfiguration(new Configuration($this, $builder, $this->extensionAlias), $builder->getExtensionConfig($this->extensionAlias));
        $orm = array_any($builder->getExtensionConfig('doctrine'), static fn (mixed $doctrine): bool => \is_array($doctrine) && isset($doctrine['orm']));

        if ($config['doctrine_mapping'] && $orm) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilGeo' => [
                'type' => 'xml',
                'is_bundle' => false,
                'dir' => $this->getPath().'/config/doctrine',
                'prefix' => 'Phil\Geo',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{geocoding: array{providers: list<string>, nominatim: array{user_agent: ?string, base_url: string, limit: int}, google_maps: array{api_key: ?string, base_url: string}, nrcan: array{base_url: string, limit: int}, photon: array{base_url: string, limit: int, languages: list<string>}, opencage: array{api_key: ?string, base_url: string, limit: int}, here: array{api_key: ?string, geocode_url: string, reverse_url: string, limit: int}, mapbox: array{access_token: ?string, base_url: string, limit: int, permanent: bool}, azure_maps: array{subscription_key: ?string, base_url: string, limit: int, api_version: string}, adresses_quebec: array{base_url: string, limit: int}, cache: array{pool: ?string, lifetime: int}}, ip_locator: array{maxmind: array{database: ?string, locale: string, account_id: ?string, license_key: ?string, edition: string, download_url: string}, ipstack: array{access_key: ?string, base_url: string}}} $config */
        GeocodingServices::register($builder, $config['geocoding'], $config['ip_locator']);
        $services = $container->services();
        $services->set(CoordinatesType::class)->tag('form.type');

        if (class_exists(AsTwigFilter::class)) {
            $services->set(GeoExtension::class)
                ->tag('twig.attribute_extension')
                ->tag('twig.runtime');
        }
    }

    /**
     * Why a provider of the chain cannot be asked — Nominatim without a user agent, a provider without its key, the
     * temporary answers of Mapbox in a cache its terms forbid —, or null when every one can.
     *
     * @param array<array-key, mixed> $geocoding
     */
    private static function geocodingRefusal(array $geocoding): ?string
    {
        $providers = \is_array($geocoding['providers'] ?? null) ? $geocoding['providers'] : [];
        $nominatim = \is_array($geocoding['nominatim'] ?? null) ? $geocoding['nominatim'] : [];
        $google = \is_array($geocoding['google_maps'] ?? null) ? $geocoding['google_maps'] : [];
        $keys = [
            'opencage' => ['api_key', 'OpenCage asks for a key: set phil_geo.geocoding.opencage.api_key.'],
            'here' => ['api_key', 'HERE asks for a key: set phil_geo.geocoding.here.api_key.'],
            'mapbox' => ['access_token', 'Mapbox asks for an access token: set phil_geo.geocoding.mapbox.access_token.'],
            'azure_maps' => ['subscription_key', 'Azure Maps asks for a subscription key: set phil_geo.geocoding.azure_maps.subscription_key.'],
        ];

        if (\in_array('nominatim', $providers, true) && null === ($nominatim['user_agent'] ?? null)) {
            return 'Nominatim asks for a user agent that names the site and how to reach it: set phil_geo.geocoding.nominatim.user_agent.';
        }

        if (\in_array('google_maps', $providers, true) && null === ($google['api_key'] ?? null)) {
            return 'Google Maps asks for a key: set phil_geo.geocoding.google_maps.api_key.';
        }

        foreach ($keys as $provider => [$key, $message]) {
            $options = \is_array($geocoding[$provider] ?? null) ? $geocoding[$provider] : [];

            if (\in_array($provider, $providers, true) && null === ($options[$key] ?? null)) {
                return $message;
            }
        }

        $mapbox = \is_array($geocoding['mapbox'] ?? null) ? $geocoding['mapbox'] : [];
        $cache = \is_array($geocoding['cache'] ?? null) ? $geocoding['cache'] : [];

        if (\in_array('mapbox', $providers, true) && true !== ($mapbox['permanent'] ?? null) && null !== ($cache['pool'] ?? null)) {
            return 'The terms of Mapbox forbid keeping its temporary answers: set phil_geo.geocoding.mapbox.permanent, which Mapbox bills, or name no phil_geo.geocoding.cache.pool.';
        }

        return null;
    }

    /**
     * Why the MaxMind settings cannot be used — an account without its licence key, or one without a database to keep
     * up to date —, or null when they can.
     *
     * @param array<array-key, mixed> $maxmind
     */
    private static function maxMindRefusal(array $maxmind): ?string
    {
        $account = [$maxmind['account_id'] ?? null, $maxmind['license_key'] ?? null];

        if ([null, null] === $account) {
            return null;
        }

        if (\in_array(null, $account, true)) {
            return 'A MaxMind download takes both the account and its licence key: set phil_geo.ip_locator.maxmind.account_id and license_key.';
        }

        return null === ($maxmind['database'] ?? null)
            ? 'A MaxMind account downloads a database to a path: set phil_geo.ip_locator.maxmind.database.'
            : null;
    }
}
