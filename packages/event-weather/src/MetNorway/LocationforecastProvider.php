<?php

declare(strict_types=1);

namespace Phil\EventWeather\MetNorway;

use DateInterval;
use DateTimeImmutable;
use Phil\EventWeather\Attribution;
use Phil\EventWeather\Condition;
use Phil\EventWeather\Contact;
use Phil\EventWeather\Contract\ForecastProvider;
use Phil\EventWeather\EventLocation;
use Phil\EventWeather\Exception\WeatherUnavailable;
use Phil\EventWeather\Forecast;
use Phil\EventWeather\ForecastPeriod;
use Phil\EventWeather\Internal\Data;
use Phil\EventWeather\Internal\JsonClient;
use Phil\Url\Url;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * MET Norway's Locationforecast 2.0, `complete` variant: a forecast for any point on Earth, hour by hour for the first
 * days and by six hours after, nine days ahead. The coordinates are cut to four decimals, as its terms require, which
 * also lets a cache share an answer between events a few metres apart. Each request carries the application's
 * `User-Agent`; the HTTP client given honours `Expires` and `If-Modified-Since` when the application wraps it in
 * Symfony's `CachingHttpClient`.
 */
final readonly class LocationforecastProvider implements ForecastProvider
{
    public const string ENDPOINT = 'https://api.met.no/weatherapi/locationforecast/2.0/complete';

    private const string NAME = 'MET Norway';

    private JsonClient $json;

    private Url $endpoint;

    public function __construct(HttpClientInterface $client, Contact $contact, ?Url $endpoint = null)
    {
        $this->json = new JsonClient($client, $contact, self::NAME);
        $this->endpoint = $endpoint ?? Url::fromString(self::ENDPOINT);
    }

    public function attribution(): Attribution
    {
        return Attribution::metNorway();
    }

    public function supports(EventLocation $location): bool
    {
        return true;
    }

    public function horizonHours(): int
    {
        return 216;
    }

    public function forecast(EventLocation $location): Forecast
    {
        $data = $this->json->get($this->endpoint->toString().'?'.http_build_query([
            'lat' => self::truncated($location->coordinates->latitude),
            'lon' => self::truncated($location->coordinates->longitude),
        ]));
        $issued = Data::instant(Data::at($data, 'properties', 'meta', 'updated_at'));
        $series = Data::at($data, 'properties', 'timeseries');

        if (!$issued instanceof DateTimeImmutable || !\is_array($series) || !array_is_list($series)) {
            throw WeatherUnavailable::format(self::NAME, 'no "properties.meta.updated_at" or "properties.timeseries"');
        }

        $periods = [];

        foreach ($series as $entry) {
            $period = $this->period($entry);

            if ($period instanceof ForecastPeriod) {
                $periods[] = $period;
            }
        }

        return new Forecast(Attribution::metNorway(), $issued, $periods);
    }

    /**
     * The degrees cut, not rounded, to four decimals.
     */
    public static function truncated(float $degrees): string
    {
        return number_format(($degrees < 0 ? -1 : 1) * floor(abs($degrees) * 10_000) / 10_000, 4, '.', '');
    }

    private function period(mixed $entry): ?ForecastPeriod
    {
        $from = Data::instant(Data::at($entry, 'time'));
        $instant = Data::at($entry, 'data', 'instant', 'details');
        [$hours, $next] = null !== Data::at($entry, 'data', 'next_1_hours')
            ? [1, Data::at($entry, 'data', 'next_1_hours')]
            : [6, Data::at($entry, 'data', 'next_6_hours')];

        if (!$from instanceof DateTimeImmutable || null === $next) {
            return null;
        }

        $symbol = Data::string(Data::at($next, 'summary', 'symbol_code'));
        $wind = Data::float(Data::at($instant, 'wind_speed'));
        $gust = Data::float(Data::at($instant, 'wind_speed_of_gust'));
        $direction = Data::int(Data::at($instant, 'wind_from_direction'));
        $chance = Data::int(Data::at($next, 'details', 'probability_of_precipitation'));

        return new ForecastPeriod(
            $from,
            $from->add(new DateInterval('PT'.$hours.'H')),
            null === $symbol ? Condition::Unknown : Condition::fromMetSymbol($symbol),
            temperature: Data::float(Data::at($instant, 'air_temperature')),
            precipitationProbability: null === $chance ? null : max(0, min(100, $chance)),
            precipitation: Data::float(Data::at($next, 'details', 'precipitation_amount')),
            windSpeed: null === $wind ? null : round($wind * 3.6, 1),
            windGust: null === $gust ? null : round($gust * 3.6, 1),
            windDirection: null === $direction ? null : (($direction % 360) + 360) % 360,
            minTemperature: Data::float(Data::at($next, 'details', 'air_temperature_min')),
            maxTemperature: Data::float(Data::at($next, 'details', 'air_temperature_max')),
            summary: $symbol,
        );
    }
}
