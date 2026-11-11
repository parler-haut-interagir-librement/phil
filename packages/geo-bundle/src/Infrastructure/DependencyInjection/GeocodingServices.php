<?php

declare(strict_types=1);

namespace Phil\GeoBundle\Infrastructure\DependencyInjection;

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
use Phil\GeoBundle\Infrastructure\MaxMind\DatabaseUpdater;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The geocoders of the configuration, asked in its order — chained when there are several, cached when a pool is
 * named —, autowired as `Geocoder`, and the IP locator, autowired as `IpLocator`, on the HTTP client of the application.
 *
 * The IP locator is the MaxMind database on this server when one is named — an address looked up never leaves it —,
 * and ipstack otherwise. Reading the database needs no HTTP client: only with a MaxMind account, its identifier and
 * licence key both set, are the updater and `phil:geo:maxmind:update` registered, on the HTTP client of the
 * application, to download the latest database.
 *
 * @phpstan-type GeocodingConfig array{providers: list<string>, nominatim: array{user_agent: ?string, base_url: string, limit: int}, google_maps: array{api_key: ?string, base_url: string}, nrcan: array{base_url: string, limit: int}, photon: array{base_url: string, limit: int, languages: list<string>}, opencage: array{api_key: ?string, base_url: string, limit: int}, here: array{api_key: ?string, geocode_url: string, reverse_url: string, limit: int}, mapbox: array{access_token: ?string, base_url: string, limit: int, permanent: bool}, azure_maps: array{subscription_key: ?string, base_url: string, limit: int, api_version: string}, adresses_quebec: array{base_url: string, limit: int}, cache: array{pool: ?string, lifetime: int}}
 * @phpstan-type IpConfig array{maxmind: array{database: ?string, locale: string, account_id: ?string, license_key: ?string, edition: string, download_url: string}, ipstack: array{access_key: ?string, base_url: string}}
 */
final class GeocodingServices
{
    public const string ID_PREFIX = 'phil_geo.geocoder.';

    /** @var list<string> */
    public const array PROVIDERS = [NominatimGeocoder::NAME, GoogleMapsGeocoder::NAME, NrcanGeocoder::NAME, PhotonGeocoder::NAME, OpenCageGeocoder::NAME, HereGeocoder::NAME, MapboxGeocoder::NAME, AzureMapsGeocoder::NAME, AdressesQuebecGeocoder::NAME];

    public const string NOMINATIM_URL = NominatimGeocoder::BASE_URL;

    public const string GOOGLE_MAPS_URL = GoogleMapsGeocoder::BASE_URL;

    public const string NRCAN_URL = NrcanGeocoder::BASE_URL;

    public const string PHOTON_URL = PhotonGeocoder::BASE_URL;

    /** @var list<string> */
    public const array PHOTON_LANGUAGES = PhotonGeocoder::LANGUAGES;

    public const string OPENCAGE_URL = OpenCageGeocoder::BASE_URL;

    public const string HERE_GEOCODE_URL = HereGeocoder::GEOCODE_URL;

    public const string HERE_REVERSE_URL = HereGeocoder::REVERSE_URL;

    public const string MAPBOX_URL = MapboxGeocoder::BASE_URL;

    public const string AZURE_MAPS_URL = AzureMapsGeocoder::BASE_URL;

    public const string AZURE_MAPS_API_VERSION = AzureMapsGeocoder::API_VERSION;

    public const string ADRESSES_QUEBEC_URL = AdressesQuebecGeocoder::BASE_URL;

    public const string IPSTACK_URL = IpstackLocator::BASE_URL;

    public const string MAXMIND_EDITION = DatabaseUpdater::EDITION;

    public const string MAXMIND_DOWNLOAD_URL = DatabaseUpdater::BASE_URL;

    /**
     * @param GeocodingConfig $geocoding
     * @param IpConfig        $ip
     */
    public static function register(ContainerBuilder $container, array $geocoding, array $ip): void
    {
        $geocoders = [];

        foreach ($geocoding['providers'] as $provider) {
            $geocoders[] = new Reference(self::ID_PREFIX.$provider);
            [$class, $arguments] = match ($provider) {
                NominatimGeocoder::NAME => [NominatimGeocoder::class, [$geocoding['nominatim']['user_agent'], $geocoding['nominatim']['base_url'], $geocoding['nominatim']['limit']]],
                NrcanGeocoder::NAME => [NrcanGeocoder::class, [$geocoding['nrcan']['base_url'], $geocoding['nrcan']['limit']]],
                PhotonGeocoder::NAME => [PhotonGeocoder::class, [$geocoding['photon']['base_url'], $geocoding['photon']['limit'], $geocoding['photon']['languages']]],
                OpenCageGeocoder::NAME => [OpenCageGeocoder::class, [$geocoding['opencage']['api_key'], $geocoding['opencage']['base_url'], $geocoding['opencage']['limit']]],
                HereGeocoder::NAME => [HereGeocoder::class, [$geocoding['here']['api_key'], $geocoding['here']['geocode_url'], $geocoding['here']['reverse_url'], $geocoding['here']['limit']]],
                MapboxGeocoder::NAME => [MapboxGeocoder::class, [$geocoding['mapbox']['access_token'], $geocoding['mapbox']['base_url'], $geocoding['mapbox']['limit'], $geocoding['mapbox']['permanent']]],
                AzureMapsGeocoder::NAME => [AzureMapsGeocoder::class, [$geocoding['azure_maps']['subscription_key'], $geocoding['azure_maps']['base_url'], $geocoding['azure_maps']['limit'], $geocoding['azure_maps']['api_version']]],
                AdressesQuebecGeocoder::NAME => [AdressesQuebecGeocoder::class, [$geocoding['adresses_quebec']['base_url'], $geocoding['adresses_quebec']['limit']]],
                default => [GoogleMapsGeocoder::class, [$geocoding['google_maps']['api_key'], $geocoding['google_maps']['base_url']]],
            };
            $container->register(self::ID_PREFIX.$provider, $class)->setArguments([new Reference('http_client'), ...$arguments]);
        }

        if (\count($geocoders) > 1) {
            $container->register(self::ID_PREFIX.'chain', ChainGeocoder::class)->setArguments([$geocoders]);
            $geocoders = [new Reference(self::ID_PREFIX.'chain')];
        }

        if (null !== $geocoding['cache']['pool'] && [] !== $geocoders) {
            $container->register(self::ID_PREFIX.'cached', CachedGeocoder::class)
                ->setArguments([$geocoders[0], new Reference($geocoding['cache']['pool']), $geocoding['cache']['lifetime']]);
            $geocoders = [new Reference(self::ID_PREFIX.'cached')];
        }

        if ([] !== $geocoders) {
            $container->setAlias(Geocoder::class, (string) $geocoders[0]);
        }

        if (null !== $ip['ipstack']['access_key']) {
            $container->register('phil_geo.ip_locator.ipstack', IpstackLocator::class)
                ->setArguments([new Reference('http_client'), $ip['ipstack']['access_key'], $ip['ipstack']['base_url']]);
            $container->setAlias(IpLocator::class, 'phil_geo.ip_locator.ipstack');
        }

        if (null !== $ip['maxmind']['database']) {
            $container->register('phil_geo.maxmind.database', Database::class)->setArguments([$ip['maxmind']['database']]);
            $container->setAlias(Database::class, 'phil_geo.maxmind.database');
            $container->register('phil_geo.ip_locator.maxmind', MaxMindLocator::class)
                ->setArguments([new Reference('phil_geo.maxmind.database'), $ip['maxmind']['locale']]);
            $container->setAlias(IpLocator::class, 'phil_geo.ip_locator.maxmind');
        }

        if (isset($ip['maxmind']['database'], $ip['maxmind']['account_id'], $ip['maxmind']['license_key'])) {
            $container->register('phil_geo.maxmind.updater', DatabaseUpdater::class)->setArguments([
                new Reference('http_client'),
                $ip['maxmind']['database'],
                $ip['maxmind']['account_id'],
                $ip['maxmind']['license_key'],
                $ip['maxmind']['edition'],
                $ip['maxmind']['download_url'],
            ]);

            if (class_exists(Command::class)) {
                $container->register('phil_geo.maxmind.update_command', UpdateMaxMindDatabaseCommand::class)
                    ->setArguments([new Reference('phil_geo.maxmind.updater')])
                    ->addTag('console.command');
            }
        }
    }
}
