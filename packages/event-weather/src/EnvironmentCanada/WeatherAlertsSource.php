<?php

declare(strict_types=1);

namespace Phil\EventWeather\EnvironmentCanada;

use Phil\EventWeather\AlertSeverity;
use Phil\EventWeather\Attribution;
use Phil\EventWeather\Contact;
use Phil\EventWeather\Contract\AlertSource;
use Phil\EventWeather\EventLocation;
use Phil\EventWeather\Internal\Data;
use Phil\EventWeather\Internal\JsonClient;
use Phil\EventWeather\Internal\Polygon;
use Phil\EventWeather\WeatherAlert;
use Phil\Geo\BoundingBox;
use Phil\Geo\Distance;
use Phil\Url\Url;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Environment and Climate Change Canada's public weather alerts, read from the `weather-alerts` collection of its MSC
 * GeoMet OGC API: the watches, warnings, advisories and statements whose area contains the event, ended ones left out.
 * The colour of its risk scale gives the severity — red extreme, orange severe, yellow moderate —, and the texts are
 * kept as issued, in the language chosen. The same alert issued for several areas around the event is kept once.
 */
final readonly class WeatherAlertsSource implements AlertSource
{
    public const string ENDPOINT = 'https://api.weather.gc.ca';

    private JsonClient $json;

    private Url $endpoint;

    /**
     * @param 'en'|'fr' $language
     */
    public function __construct(
        HttpClientInterface $client,
        Contact $contact,
        private string $language = 'en',
        ?Url $endpoint = null,
    ) {
        $this->json = new JsonClient($client, $contact, 'Environment Canada');
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

    public function alerts(EventLocation $location): array
    {
        $box = BoundingBox::around($location->coordinates, Distance::kilometers(1));
        $data = $this->json->get(rtrim($this->endpoint->toString(), '/').'/collections/weather-alerts/items?'.http_build_query([
            'f' => 'json',
            'limit' => 500,
            'bbox' => implode(',', [$box->west, $box->south, $box->east, $box->north]),
        ]));
        $alerts = [];

        foreach (Data::list(Data::at($data, 'features')) as $feature) {
            $properties = Data::at($feature, 'properties');
            $geometry = Data::at($feature, 'geometry');

            if ('ended' === Data::at($properties, 'status_en') || (null !== $geometry && !Polygon::contains($geometry, $location->coordinates))) {
                continue;
            }

            $alert = $this->alert($feature, $properties);

            if ($alert instanceof WeatherAlert) {
                $alerts[$alert->id] ??= $alert;
            }
        }

        return array_values($alerts);
    }

    private function alert(mixed $feature, mixed $properties): ?WeatherAlert
    {
        $name = Data::string(Data::at($properties, 'alert_name_'.$this->language));

        if (null === $name) {
            return null;
        }

        $code = Data::string(Data::at($properties, 'alert_code')) ?? Data::string(Data::at($feature, 'id')) ?? $name;
        $effective = Data::instant(Data::at($properties, 'validity_datetime'));
        $expires = Data::instant(Data::at($properties, 'event_end_datetime')) ?? Data::instant(Data::at($properties, 'expiration_datetime'));

        return new WeatherAlert(
            implode('|', [$code, $effective?->format('Y-m-d\TH:i:s\Z') ?? '', $expires?->format('Y-m-d\TH:i:s\Z') ?? '']),
            $name,
            match (Data::at($properties, 'risk_colour_en')) {
                'red' => AlertSeverity::Extreme,
                'orange' => AlertSeverity::Severe,
                'yellow' => AlertSeverity::Moderate,
                default => AlertSeverity::Unknown,
            },
            'Environment and Climate Change Canada',
            $effective,
            $expires,
            Data::string(Data::at($properties, 'alert_short_name_'.$this->language)),
            Data::string(Data::at($properties, 'alert_text_'.$this->language)),
            area: Data::string(Data::at($properties, 'feature_name_'.$this->language)),
            language: $this->language,
        );
    }
}
