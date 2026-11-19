<?php

declare(strict_types=1);

namespace Phil\EventWeather\EnvironmentCanada;

use DateInterval;
use DateTimeImmutable;
use Phil\EventWeather\Attribution;
use Phil\EventWeather\Condition;
use Phil\EventWeather\Contact;
use Phil\EventWeather\Contract\ForecastProvider;
use Phil\EventWeather\EventLocation;
use Phil\EventWeather\Exception\InvalidArgument;
use Phil\EventWeather\Exception\WeatherUnavailable;
use Phil\EventWeather\Forecast;
use Phil\EventWeather\ForecastPeriod;
use Phil\EventWeather\Internal\Data;
use Phil\EventWeather\Internal\JsonClient;
use Phil\Geo\BoundingBox;
use Phil\Geo\Coordinates;
use Phil\Geo\Distance;
use Phil\Url\Url;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Environment and Climate Change Canada's city page forecasts, read from the `citypageweather-realtime` collection of
 * its MSC GeoMet OGC API: the forecast site nearest the event within a search distance, and its hourly forecast, about
 * a day ahead. For Canadian places only, the event's country being `CA`; beyond its horizon, the next provider of the
 * list takes over.
 */
final readonly class CitypageWeatherProvider implements ForecastProvider
{
    public const string ENDPOINT = 'https://api.weather.gc.ca';

    private const string NAME = 'Environment Canada';

    private const array COMPASS = ['N' => 0, 'NNE' => 23, 'NE' => 45, 'ENE' => 68, 'E' => 90, 'ESE' => 113, 'SE' => 135, 'SSE' => 158, 'S' => 180, 'SSW' => 203, 'SW' => 225, 'WSW' => 248, 'W' => 270, 'WNW' => 293, 'NW' => 315, 'NNW' => 338];

    private JsonClient $json;

    private Url $endpoint;

    /**
     * @param 'en'|'fr' $language the language of the provider's words kept in the summaries
     *
     * @throws InvalidArgument when the search distance is not between 1 and 200 km
     */
    public function __construct(
        HttpClientInterface $client,
        Contact $contact,
        private string $language = 'en',
        ?Url $endpoint = null,
        private float $searchKilometers = 50.0,
    ) {
        if ($searchKilometers < 1 || $searchKilometers > 200) {
            throw new InvalidArgument('The search distance of a forecast site is between 1 and 200 km.');
        }

        $this->json = new JsonClient($client, $contact, self::NAME);
        $this->endpoint = $endpoint ?? Url::fromString(self::ENDPOINT);
    }

    public function attribution(): Attribution
    {
        return Attribution::environmentCanada();
    }

    public function supports(EventLocation $location): bool
    {
        return $location->isIn('CA');
    }

    public function horizonHours(): int
    {
        return 24;
    }

    public function forecast(EventLocation $location): Forecast
    {
        $box = BoundingBox::around($location->coordinates, Distance::kilometers($this->searchKilometers));
        $data = $this->json->get(rtrim($this->endpoint->toString(), '/').'/collections/citypageweather-realtime/items?'.http_build_query([
            'f' => 'json',
            'lang' => $this->language,
            'limit' => 100,
            'bbox' => implode(',', [$box->west, $box->south, $box->east, $box->north]),
        ]));
        $site = $this->nearest(Data::list(Data::at($data, 'features')), $location->coordinates);
        $group = Data::at($site, 'properties', 'forecastGroup', 'hourlyForecastGroup');
        $issued = Data::instant(Data::at($group, 'timestamp', 'en')) ?? Data::instant(Data::at($site, 'properties', 'lastUpdated'));

        if (!$issued instanceof DateTimeImmutable) {
            throw WeatherUnavailable::format(self::NAME, 'no time of issue');
        }

        $periods = [];

        foreach (Data::list(Data::at($group, 'hourlyForecasts')) as $hour) {
            $period = $this->period($hour);

            if ($period instanceof ForecastPeriod) {
                $periods[] = $period;
            }
        }

        return new Forecast(Attribution::environmentCanada(), $issued, $periods);
    }

    /**
     * @param list<mixed> $features
     *
     * @throws WeatherUnavailable when no site is within the search distance
     */
    private function nearest(array $features, Coordinates $event): mixed
    {
        $nearest = null;
        $shortest = Distance::kilometers($this->searchKilometers);

        foreach ($features as $feature) {
            $longitude = Data::float(Data::at($feature, 'geometry', 'coordinates', 0));
            $latitude = Data::float(Data::at($feature, 'geometry', 'coordinates', 1));

            if (null === $longitude || null === $latitude || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                continue;
            }

            $distance = Coordinates::of($latitude, $longitude)->distanceTo($event);

            if (!$shortest->isShorterThan($distance)) {
                $nearest = $feature;
                $shortest = $distance;
            }
        }

        if (null === $nearest) {
            throw WeatherUnavailable::noSite(self::NAME);
        }

        return $nearest;
    }

    private function period(mixed $hour): ?ForecastPeriod
    {
        $from = Data::instant(Data::at($hour, 'timestamp'));

        if (!$from instanceof DateTimeImmutable) {
            return null;
        }

        $words = Data::string(Data::at($hour, 'condition', 'en'));
        $chance = Data::int(Data::at($hour, 'lop', 'value', 'en'));

        return new ForecastPeriod(
            $from,
            $from->add(new DateInterval('PT1H')),
            null === $words ? Condition::Unknown : Condition::fromWords($words),
            temperature: Data::float(Data::at($hour, 'temperature', 'value', 'en')),
            precipitationProbability: null === $chance ? null : max(0, min(100, $chance)),
            windSpeed: Data::float(Data::at($hour, 'wind', 'speed', 'value', 'en')),
            windGust: Data::float(Data::at($hour, 'wind', 'gust', 'value', 'en')),
            windDirection: self::COMPASS[Data::string(Data::at($hour, 'wind', 'direction', 'value', 'en')) ?? ''] ?? null,
            summary: Data::string(Data::at($hour, 'condition', $this->language)),
        );
    }
}
