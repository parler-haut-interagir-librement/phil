<?php

declare(strict_types=1);

namespace Phil\EventWeather;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Phil\EventWeather\Contract\AlertSource;
use Phil\EventWeather\Contract\ForecastProvider;
use Phil\EventWeather\Exception\InvalidArgument;
use Phil\EventWeather\Exception\WeatherUnavailable;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * The forecast of an outdoor event: the providers are tried in the order given, the first that supports the event's
 * place, reaches its start within its horizon and forecasts some of its time wins; the alerts come from the first
 * alert source of the place. A provider that fails is logged and the next one tried; when none answers, the forecast
 * is unavailable rather than an error on the event's page.
 *
 * Every instant is compared in UTC, now coming from the clock given. The service keeps no state between calls.
 */
final readonly class EventWeather
{
    /**
     * @param list<ForecastProvider> $providers    in the order they are tried, the preferred first
     * @param list<AlertSource>      $alertSources in the order they are tried
     */
    public function __construct(
        private array $providers,
        private ClockInterface $clock,
        private array $alertSources = [],
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @throws InvalidArgument when the event ends before it starts
     */
    public function forecastFor(EventLocation $location, DateTimeImmutable $start, DateTimeImmutable $end): EventForecast
    {
        $utc = new DateTimeZone('UTC');
        $start = $start->setTimezone($utc);
        $end = $end->setTimezone($utc);
        $now = $this->clock->now()->setTimezone($utc);

        if ($end < $start) {
            throw new InvalidArgument('An event ends after it starts.');
        }

        if ($end <= $now) {
            return new EventForecast(Availability::Past, $start, $end);
        }

        $supporting = array_values(array_filter($this->providers, static fn (ForecastProvider $provider): bool => $provider->supports($location)));

        if ([] === $supporting) {
            return new EventForecast(Availability::Unsupported, $start, $end);
        }

        $reaching = array_values(array_filter($supporting, static fn (ForecastProvider $provider): bool => $start < $now->add(new DateInterval('PT'.$provider->horizonHours().'H'))));

        if ([] === $reaching) {
            return new EventForecast(Availability::TooFar, $start, $end);
        }

        [$alerts, $alertAttribution] = $this->alerts($location, $start, $end);
        $answered = false;

        foreach ($reaching as $provider) {
            try {
                $forecast = $provider->forecast($location);
            } catch (WeatherUnavailable $exception) {
                $this->logger->warning('The forecast of {provider} is unavailable: {message}', ['provider' => $provider->attribution()->provider, 'message' => $exception->getMessage(), 'exception' => $exception]);

                continue;
            }

            $answered = true;
            $periods = $forecast->between($start, $end);

            if ([] !== $periods) {
                $attributions = null === $alertAttribution || $alertAttribution->provider === $forecast->attribution->provider ? [$forecast->attribution] : [$forecast->attribution, $alertAttribution];

                return new EventForecast(Availability::Available, $start, $end, $forecast, $periods, $alerts, $attributions);
            }
        }

        return new EventForecast($answered ? Availability::TooFar : Availability::Unavailable, $start, $end, alerts: $alerts, attributions: null === $alertAttribution ? [] : [$alertAttribution]);
    }

    /**
     * @return array{list<WeatherAlert>, ?Attribution}
     */
    private function alerts(EventLocation $location, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        foreach ($this->alertSources as $source) {
            if (!$source->supports($location)) {
                continue;
            }

            try {
                $alerts = array_values(array_filter($source->alerts($location), static fn (WeatherAlert $alert): bool => $alert->isInEffectDuring($start, $end)));
            } catch (WeatherUnavailable $exception) {
                $this->logger->warning('The alerts of {provider} are unavailable: {message}', ['provider' => $source->attribution()->provider, 'message' => $exception->getMessage(), 'exception' => $exception]);

                return [[], null];
            }

            return [$alerts, [] === $alerts ? null : $source->attribution()];
        }

        return [[], null];
    }
}
