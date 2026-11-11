<?php

declare(strict_types=1);

namespace Phil\GeoBundle\Tests\Unit;

use Phil\Geo\Geocoding\Adapter\AdressesQuebecGeocoder;
use Phil\Geo\Geocoding\Adapter\AzureMapsGeocoder;
use Phil\Geo\Geocoding\Adapter\CachedGeocoder;
use Phil\Geo\Geocoding\Adapter\ChainGeocoder;
use Phil\Geo\Geocoding\Adapter\GoogleMapsGeocoder;
use Phil\Geo\Geocoding\Adapter\HereGeocoder;
use Phil\Geo\Geocoding\Adapter\IpstackLocator;
use Phil\Geo\Geocoding\Adapter\MapboxGeocoder;
use Phil\Geo\Geocoding\Adapter\MaxMindLocator;
use Phil\Geo\Geocoding\Adapter\NominatimGeocoder;
use Phil\Geo\Geocoding\Adapter\NrcanGeocoder;
use Phil\Geo\Geocoding\Adapter\OpenCageGeocoder;
use Phil\Geo\Geocoding\Adapter\PhotonGeocoder;
use Phil\Geo\Geocoding\Geocoder;
use Phil\Geo\Geocoding\IpLocator;
use Phil\Geo\Geocoding\MaxMind\Database;
use Phil\GeoBundle\Infrastructure\Console\UpdateMaxMindDatabaseCommand;
use Phil\GeoBundle\Infrastructure\DependencyInjection\GeocodingServices;
use Phil\GeoBundle\Infrastructure\MaxMind\DatabaseUpdater;
use Phil\GeoBundle\PhilGeoBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilGeoBundle::class)]
#[CoversClass(GeocodingServices::class)]
final class GeocodingServicesTest extends TestCase
{
    public function testWithoutProvidersNothingIsGeocoded(): void
    {
        $container = $this->load([]);

        self::assertFalse($container->hasAlias(Geocoder::class));
        self::assertFalse($container->hasAlias(IpLocator::class));
        self::assertSame([], array_values(array_filter(array_keys($container->getDefinitions()), static fn (string $id): bool => str_starts_with($id, 'phil_geo.'))));
    }

    public function testOneProviderIsTheGeocoder(): void
    {
        $container = $this->load(['geocoding' => ['providers' => ['nominatim'], 'nominatim' => ['user_agent' => 'Site (a@b.ca)']]]);

        self::assertSame('phil_geo.geocoder.nominatim', (string) $container->getAlias(Geocoder::class));
        self::assertSame(NominatimGeocoder::class, $container->getDefinition('phil_geo.geocoder.nominatim')->getClass());
        self::assertEquals([new Reference('http_client'), 'Site (a@b.ca)', 'https://nominatim.openstreetmap.org', 5], $container->getDefinition('phil_geo.geocoder.nominatim')->getArguments());
        self::assertFalse($container->hasDefinition('phil_geo.geocoder.chain'));
    }

    public function testSeveralProvidersAreChainedAndCached(): void
    {
        $container = $this->load(['geocoding' => [
            'providers' => ['google_maps', 'nominatim'],
            'nominatim' => ['user_agent' => 'ua', 'base_url' => 'https://geo.example.com', 'limit' => 40],
            'google_maps' => ['api_key' => 'key', 'base_url' => 'https://maps.example.com'],
            'cache' => ['pool' => 'cache.app', 'lifetime' => 60],
        ]]);

        self::assertEquals([new Reference('http_client'), 'key', 'https://maps.example.com'], $container->getDefinition('phil_geo.geocoder.google_maps')->getArguments());
        self::assertSame(GoogleMapsGeocoder::class, $container->getDefinition('phil_geo.geocoder.google_maps')->getClass());
        self::assertEquals([new Reference('http_client'), 'ua', 'https://geo.example.com', 40], $container->getDefinition('phil_geo.geocoder.nominatim')->getArguments());
        self::assertSame(ChainGeocoder::class, $container->getDefinition('phil_geo.geocoder.chain')->getClass());
        self::assertEquals([[new Reference('phil_geo.geocoder.google_maps'), new Reference('phil_geo.geocoder.nominatim')]], $container->getDefinition('phil_geo.geocoder.chain')->getArguments());
        self::assertSame(CachedGeocoder::class, $container->getDefinition('phil_geo.geocoder.cached')->getClass());
        self::assertEquals([new Reference('phil_geo.geocoder.chain'), new Reference('cache.app'), 60], $container->getDefinition('phil_geo.geocoder.cached')->getArguments());
        self::assertSame('phil_geo.geocoder.cached', (string) $container->getAlias(Geocoder::class));
        self::assertEquals([new Reference('phil_geo.geocoder.nominatim'), new Reference('cache.app'), 604800], $this->load(['geocoding' => ['providers' => ['nominatim'], 'nominatim' => ['user_agent' => 'ua'], 'cache' => ['pool' => 'cache.app']]])->getDefinition('phil_geo.geocoder.cached')->getArguments());
        self::assertFalse($this->load(['geocoding' => ['cache' => ['pool' => 'cache.app']]])->hasDefinition('phil_geo.geocoder.cached'));
        self::assertSame([1, 1], [
            $this->load(['geocoding' => ['providers' => ['nominatim'], 'nominatim' => ['user_agent' => 'ua', 'limit' => 1]]])->getDefinition('phil_geo.geocoder.nominatim')->getArgument(3),
            $this->load(['geocoding' => ['providers' => ['nominatim'], 'nominatim' => ['user_agent' => 'ua'], 'cache' => ['pool' => 'p', 'lifetime' => 1]]])->getDefinition('phil_geo.geocoder.cached')->getArgument(2),
        ]);
    }

    /**
     * Natural Resources Canada needs no key: naming it is enough.
     */
    public function testNaturalResourcesCanada(): void
    {
        $container = $this->load(['geocoding' => ['providers' => ['nrcan']]]);

        self::assertSame('phil_geo.geocoder.nrcan', (string) $container->getAlias(Geocoder::class));
        self::assertSame(NrcanGeocoder::class, $container->getDefinition('phil_geo.geocoder.nrcan')->getClass());
        self::assertEquals([new Reference('http_client'), 'https://www.geolocator.api.geo.ca/geolocation', 5], $container->getDefinition('phil_geo.geocoder.nrcan')->getArguments());
        self::assertEquals([new Reference('http_client'), 'https://geo.example.com', 2], $this->load(['geocoding' => ['providers' => ['nrcan'], 'nrcan' => ['base_url' => 'https://geo.example.com', 'limit' => 2]]])->getDefinition('phil_geo.geocoder.nrcan')->getArguments());
        self::assertSame([1, 40], [
            $this->load(['geocoding' => ['providers' => ['nrcan'], 'nrcan' => ['limit' => 1]]])->getDefinition('phil_geo.geocoder.nrcan')->getArgument(2),
            $this->load(['geocoding' => ['providers' => ['nrcan'], 'nrcan' => ['limit' => 40]]])->getDefinition('phil_geo.geocoder.nrcan')->getArgument(2),
        ]);
        self::assertSame(GeocodingServices::NRCAN_URL, NrcanGeocoder::BASE_URL);
    }

    /**
     * Photon needs no key either; another server is a base URL, and the languages it knows.
     */
    public function testPhoton(): void
    {
        $container = $this->load(['geocoding' => ['providers' => ['photon']]]);

        self::assertSame('phil_geo.geocoder.photon', (string) $container->getAlias(Geocoder::class));
        self::assertSame(PhotonGeocoder::class, $container->getDefinition('phil_geo.geocoder.photon')->getClass());
        self::assertEquals([new Reference('http_client'), 'https://photon.komoot.io', 5, ['de', 'en', 'fr']], $container->getDefinition('phil_geo.geocoder.photon')->getArguments());
        self::assertEquals([new Reference('http_client'), 'https://photon.example.com', 2, ['fr', 'it']], $this->load(['geocoding' => ['providers' => ['photon'], 'photon' => ['base_url' => 'https://photon.example.com', 'limit' => 2, 'languages' => ['fr', 'it']]]])->getDefinition('phil_geo.geocoder.photon')->getArguments());
        self::assertSame([1, 40], [
            $this->load(['geocoding' => ['providers' => ['photon'], 'photon' => ['limit' => 1]]])->getDefinition('phil_geo.geocoder.photon')->getArgument(2),
            $this->load(['geocoding' => ['providers' => ['photon'], 'photon' => ['limit' => 40]]])->getDefinition('phil_geo.geocoder.photon')->getArgument(2),
        ]);
        self::assertSame([GeocodingServices::PHOTON_URL, GeocodingServices::PHOTON_LANGUAGES], [PhotonGeocoder::BASE_URL, PhotonGeocoder::LANGUAGES]);
    }

    public function testTheProvidersWithAKeyAndAdressesQuebec(): void
    {
        $container = $this->load(['geocoding' => [
            'providers' => ['opencage', 'here', 'mapbox', 'azure_maps', 'adresses_quebec'],
            'opencage' => ['api_key' => 'oc'],
            'here' => ['api_key' => 'he'],
            'mapbox' => ['access_token' => 'mb'],
            'azure_maps' => ['subscription_key' => 'az'],
        ]]);
        $client = new Reference('http_client');

        self::assertSame([OpenCageGeocoder::class, HereGeocoder::class, MapboxGeocoder::class, AzureMapsGeocoder::class, AdressesQuebecGeocoder::class], array_map(static fn (string $name): ?string => $container->getDefinition('phil_geo.geocoder.'.$name)->getClass(), ['opencage', 'here', 'mapbox', 'azure_maps', 'adresses_quebec']));
        self::assertEquals([$client, 'oc', 'https://api.opencagedata.com/geocode/v1/json', 5], $container->getDefinition('phil_geo.geocoder.opencage')->getArguments());
        self::assertEquals([$client, 'he', 'https://geocode.search.hereapi.com/v1/geocode', 'https://revgeocode.search.hereapi.com/v1/revgeocode', 5], $container->getDefinition('phil_geo.geocoder.here')->getArguments());
        self::assertEquals([$client, 'mb', 'https://api.mapbox.com/search/geocode/v6', 5, false], $container->getDefinition('phil_geo.geocoder.mapbox')->getArguments());
        self::assertEquals([$client, 'az', 'https://atlas.microsoft.com', 5, '2026-01-01'], $container->getDefinition('phil_geo.geocoder.azure_maps')->getArguments());
        self::assertEquals([$client, 'https://servicescarto.mrnf.gouv.qc.ca/pes/rest/services/Territoire/Adresse_Geocodage/GeocodeServer', 5], $container->getDefinition('phil_geo.geocoder.adresses_quebec')->getArguments());

        $set = $this->load(['geocoding' => [
            'providers' => ['opencage', 'here', 'mapbox', 'azure_maps', 'adresses_quebec'],
            'opencage' => ['api_key' => 'oc', 'base_url' => 'https://oc.example.com', 'limit' => 100],
            'here' => ['api_key' => 'he', 'geocode_url' => 'https://g.example.com', 'reverse_url' => 'https://r.example.com', 'limit' => 100],
            'mapbox' => ['access_token' => 'mb', 'base_url' => 'https://mb.example.com', 'limit' => 10, 'permanent' => true],
            'azure_maps' => ['subscription_key' => 'az', 'base_url' => 'https://az.example.com', 'limit' => 20, 'api_version' => '2025-01-01'],
            'adresses_quebec' => ['base_url' => 'https://aq.example.com', 'limit' => 40],
        ]]);
        self::assertEquals([
            [$client, 'oc', 'https://oc.example.com', 100],
            [$client, 'he', 'https://g.example.com', 'https://r.example.com', 100],
            [$client, 'mb', 'https://mb.example.com', 10, true],
            [$client, 'az', 'https://az.example.com', 20, '2025-01-01'],
            [$client, 'https://aq.example.com', 40],
        ], array_map(static fn (string $name): array => $set->getDefinition('phil_geo.geocoder.'.$name)->getArguments(), ['opencage', 'here', 'mapbox', 'azure_maps', 'adresses_quebec']));
        self::assertSame([1, 1, 1, 1, 1], array_map(fn (string $name): mixed => $this->load(['geocoding' => ['providers' => [$name], 'opencage' => ['api_key' => 'k', 'limit' => 1], 'here' => ['api_key' => 'k', 'limit' => 1], 'mapbox' => ['access_token' => 't', 'limit' => 1], 'azure_maps' => ['subscription_key' => 'k', 'limit' => 1], 'adresses_quebec' => ['limit' => 1]]])->getDefinition('phil_geo.geocoder.'.$name)->getArgument(['opencage' => 3, 'here' => 4, 'mapbox' => 3, 'azure_maps' => 3, 'adresses_quebec' => 2][$name]), ['opencage', 'here', 'mapbox', 'azure_maps', 'adresses_quebec']));
        self::assertSame(
            [OpenCageGeocoder::BASE_URL, HereGeocoder::GEOCODE_URL, HereGeocoder::REVERSE_URL, MapboxGeocoder::BASE_URL, AzureMapsGeocoder::BASE_URL, AzureMapsGeocoder::API_VERSION, AdressesQuebecGeocoder::BASE_URL],
            [GeocodingServices::OPENCAGE_URL, GeocodingServices::HERE_GEOCODE_URL, GeocodingServices::HERE_REVERSE_URL, GeocodingServices::MAPBOX_URL, GeocodingServices::AZURE_MAPS_URL, GeocodingServices::AZURE_MAPS_API_VERSION, GeocodingServices::ADRESSES_QUEBEC_URL],
        );
    }

    public function testTheIpLocator(): void
    {
        $container = $this->load(['ip_locator' => ['ipstack' => ['access_key' => 'secret']]]);

        self::assertSame('phil_geo.ip_locator.ipstack', (string) $container->getAlias(IpLocator::class));
        self::assertSame(IpstackLocator::class, $container->getDefinition('phil_geo.ip_locator.ipstack')->getClass());
        self::assertEquals([new Reference('http_client'), 'secret', 'https://api.ipstack.com'], $container->getDefinition('phil_geo.ip_locator.ipstack')->getArguments());
        self::assertSame('https://ip.example.com', $this->load(['ip_locator' => ['ipstack' => ['access_key' => 'k', 'base_url' => 'https://ip.example.com']]])->getDefinition('phil_geo.ip_locator.ipstack')->getArgument(2));
        self::assertFalse($container->hasDefinition('phil_geo.ip_locator.maxmind'));
    }

    /**
     * A MaxMind database on this server comes before ipstack: an address looked up never leaves it.
     */
    public function testTheMaxMindDatabase(): void
    {
        $container = $this->load(['ip_locator' => ['maxmind' => ['database' => '/var/geoip/GeoLite2-City.mmdb', 'locale' => 'fr'], 'ipstack' => ['access_key' => 'secret']]]);

        self::assertSame('phil_geo.ip_locator.maxmind', (string) $container->getAlias(IpLocator::class));
        self::assertSame('phil_geo.maxmind.database', (string) $container->getAlias(Database::class));
        self::assertSame([MaxMindLocator::class, Database::class], [$container->getDefinition('phil_geo.ip_locator.maxmind')->getClass(), $container->getDefinition('phil_geo.maxmind.database')->getClass()]);
        self::assertEquals([new Reference('phil_geo.maxmind.database'), 'fr'], $container->getDefinition('phil_geo.ip_locator.maxmind')->getArguments());
        self::assertSame(['/var/geoip/GeoLite2-City.mmdb'], $container->getDefinition('phil_geo.maxmind.database')->getArguments());
        self::assertTrue($container->hasDefinition('phil_geo.ip_locator.ipstack'));
        self::assertSame('en', $this->load(['ip_locator' => ['maxmind' => ['database' => 'city.mmdb']]])->getDefinition('phil_geo.ip_locator.maxmind')->getArgument(1));
        self::assertFalse($this->load([])->hasAlias(Database::class));
        self::assertFalse($this->load([])->hasDefinition('phil_geo.maxmind.updater'));

        $account = $this->load(['ip_locator' => ['maxmind' => ['database' => '/var/geoip/GeoLite2-City.mmdb', 'account_id' => '12345', 'license_key' => 'secret']]]);
        self::assertEquals([new Reference('http_client'), '/var/geoip/GeoLite2-City.mmdb', '12345', 'secret', 'GeoLite2-City', 'https://download.maxmind.com/geoip/databases'], $account->getDefinition('phil_geo.maxmind.updater')->getArguments());
        self::assertSame(DatabaseUpdater::class, $account->getDefinition('phil_geo.maxmind.updater')->getClass());
        self::assertSame(UpdateMaxMindDatabaseCommand::class, $account->getDefinition('phil_geo.maxmind.update_command')->getClass());
        self::assertEquals([new Reference('phil_geo.maxmind.updater')], $account->getDefinition('phil_geo.maxmind.update_command')->getArguments());
        self::assertTrue($account->getDefinition('phil_geo.maxmind.update_command')->hasTag('console.command'));

        $mirror = $this->load(['ip_locator' => ['maxmind' => ['database' => 'city.mmdb', 'account_id' => '12345', 'license_key' => 'secret', 'edition' => 'GeoIP2-City', 'download_url' => 'https://mirror.example.com']]]);
        self::assertSame(['12345', 'secret', 'GeoIP2-City', 'https://mirror.example.com'], \array_slice($mirror->getDefinition('phil_geo.maxmind.updater')->getArguments(), 2));
        self::assertSame([GeocodingServices::MAXMIND_EDITION, GeocodingServices::MAXMIND_DOWNLOAD_URL], [DatabaseUpdater::EDITION, DatabaseUpdater::BASE_URL]);
        self::assertSame(['phil_geo.geocoder.', ['nominatim', 'google_maps', 'nrcan', 'photon', 'opencage', 'here', 'mapbox', 'azure_maps', 'adresses_quebec']], [GeocodingServices::ID_PREFIX, GeocodingServices::PROVIDERS]);
    }

    /**
     * A database kept up to date by other means is only read: nothing that downloads is registered, so the
     * application needs no HTTP client.
     */
    public function testAMaxMindDatabaseWithoutAnAccountNeedsNoHttpClient(): void
    {
        $container = $this->load(['ip_locator' => ['maxmind' => ['database' => '/var/geoip/GeoLite2-City.mmdb']]]);

        self::assertFalse($container->hasDefinition('phil_geo.maxmind.updater'));
        self::assertFalse($container->hasDefinition('phil_geo.maxmind.update_command'));
        self::assertSame('phil_geo.ip_locator.maxmind', (string) $container->getAlias(IpLocator::class));

        foreach ($container->getDefinitions() as $id => $definition) {
            foreach ($definition->getArguments() as $argument) {
                self::assertFalse($argument instanceof Reference && 'http_client' === (string) $argument, $id.' asks for the HTTP client.');
            }
        }

        $container->setParameter('kernel.debug', false);
        $container->getDefinition('phil_geo.ip_locator.maxmind')->setPublic(true);
        $container->compile(true);
        self::assertSame(MaxMindLocator::class, $container->getDefinition('phil_geo.ip_locator.maxmind')->getClass());
    }

    /**
     * Mapbox's permanent answers, which it bills, may be cached, and its temporary ones when nothing caches them.
     */
    public function testMapboxIsCachedOnlyWhenItsAnswersArePermanent(): void
    {
        $permanent = $this->load(['geocoding' => ['providers' => ['mapbox'], 'mapbox' => ['access_token' => 't', 'permanent' => true], 'cache' => ['pool' => 'cache.app']]]);
        self::assertSame('phil_geo.geocoder.cached', (string) $permanent->getAlias(Geocoder::class));

        $temporary = $this->load(['geocoding' => ['providers' => ['mapbox'], 'mapbox' => ['access_token' => 't']]]);
        self::assertSame('phil_geo.geocoder.mapbox', (string) $temporary->getAlias(Geocoder::class));

        $notAsked = $this->load(['geocoding' => ['providers' => ['opencage'], 'opencage' => ['api_key' => 'k'], 'cache' => ['pool' => 'cache.app']]]);
        self::assertSame('phil_geo.geocoder.cached', (string) $notAsked->getAlias(Geocoder::class));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function badConfigurations(): iterable
    {
        yield 'mapbox temporary answers in a cache' => [['geocoding' => ['providers' => ['nrcan', 'mapbox'], 'mapbox' => ['access_token' => 't'], 'cache' => ['pool' => 'cache.app']]], 'The terms of Mapbox forbid keeping its temporary answers: set phil_geo.geocoding.mapbox.permanent, which Mapbox bills, or name no phil_geo.geocoding.cache.pool.'];
        yield 'a MaxMind account without its key' => [['ip_locator' => ['maxmind' => ['database' => 'city.mmdb', 'account_id' => '1']]], 'A MaxMind download takes both the account and its licence key: set phil_geo.ip_locator.maxmind.account_id and license_key.'];
        yield 'a MaxMind key without its account' => [['ip_locator' => ['maxmind' => ['database' => 'city.mmdb', 'license_key' => 'k']]], 'A MaxMind download takes both the account and its licence key: set phil_geo.ip_locator.maxmind.account_id and license_key.'];
        yield 'a MaxMind account without a database' => [['ip_locator' => ['maxmind' => ['account_id' => '1', 'license_key' => 'k']]], 'A MaxMind account downloads a database to a path: set phil_geo.ip_locator.maxmind.database.'];
        yield 'nominatim without a user agent' => [['geocoding' => ['providers' => ['nominatim']]], 'Nominatim asks for a user agent that names the site and how to reach it: set phil_geo.geocoding.nominatim.user_agent.'];
        yield 'google maps without a key' => [['geocoding' => ['providers' => ['nominatim', 'google_maps'], 'nominatim' => ['user_agent' => 'ua']]], 'Google Maps asks for a key: set phil_geo.geocoding.google_maps.api_key.'];
        yield 'opencage without a key' => [['geocoding' => ['providers' => ['opencage']]], 'OpenCage asks for a key: set phil_geo.geocoding.opencage.api_key.'];
        yield 'here without a key' => [['geocoding' => ['providers' => ['opencage', 'here'], 'opencage' => ['api_key' => 'k']]], 'HERE asks for a key: set phil_geo.geocoding.here.api_key.'];
        yield 'mapbox without a token' => [['geocoding' => ['providers' => ['mapbox']]], 'Mapbox asks for an access token: set phil_geo.geocoding.mapbox.access_token.'];
        yield 'azure maps without a key' => [['geocoding' => ['providers' => ['adresses_quebec', 'azure_maps']]], 'Azure Maps asks for a subscription key: set phil_geo.geocoding.azure_maps.subscription_key.'];
        yield 'too many places from OpenCage' => [['geocoding' => ['opencage' => ['limit' => 101]]], 'limit'];
        yield 'too many places from HERE' => [['geocoding' => ['here' => ['limit' => 101]]], 'limit'];
        yield 'too many places from Mapbox' => [['geocoding' => ['mapbox' => ['limit' => 11]]], 'limit'];
        yield 'too many places from Azure Maps' => [['geocoding' => ['azure_maps' => ['limit' => 21]]], 'limit'];
        yield 'too many places from Adresses Québec' => [['geocoding' => ['adresses_quebec' => ['limit' => 41]]], 'limit'];
        yield 'no place from OpenCage' => [['geocoding' => ['opencage' => ['limit' => 0]]], 'limit'];
        yield 'no place from HERE' => [['geocoding' => ['here' => ['limit' => 0]]], 'limit'];
        yield 'no place from Mapbox' => [['geocoding' => ['mapbox' => ['limit' => 0]]], 'limit'];
        yield 'no place from Azure Maps' => [['geocoding' => ['azure_maps' => ['limit' => 0]]], 'limit'];
        yield 'no place from Adresses Québec' => [['geocoding' => ['adresses_quebec' => ['limit' => 0]]], 'limit'];
        yield 'an unknown provider' => [['geocoding' => ['providers' => ['bing']]], 'providers'];
        yield 'too many places' => [['geocoding' => ['nominatim' => ['limit' => 41]]], 'limit'];
        yield 'no place' => [['geocoding' => ['nominatim' => ['limit' => 0]]], 'limit'];
        yield 'too many places from NRCan' => [['geocoding' => ['nrcan' => ['limit' => 41]]], 'limit'];
        yield 'no place from NRCan' => [['geocoding' => ['nrcan' => ['limit' => 0]]], 'limit'];
        yield 'too many places from Photon' => [['geocoding' => ['photon' => ['limit' => 41]]], 'limit'];
        yield 'no place from Photon' => [['geocoding' => ['photon' => ['limit' => 0]]], 'limit'];
        yield 'no Photon server' => [['geocoding' => ['photon' => ['base_url' => '']]], 'base_url'];
        yield 'an empty Photon language' => [['geocoding' => ['photon' => ['languages' => ['']]]], 'languages'];
        yield 'no lifetime' => [['geocoding' => ['cache' => ['lifetime' => 0]]], 'lifetime'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('badConfigurations')]
    public function testABadConfigurationIsRefused(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load($config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilGeoBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
