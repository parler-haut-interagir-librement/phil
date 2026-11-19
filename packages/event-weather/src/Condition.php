<?php

declare(strict_types=1);

namespace Phil\EventWeather;

/**
 * The sky of a period, in the few words every provider can be read into; the provider's own words stay in the
 * period's summary.
 */
enum Condition: string
{
    case Clear = 'clear';
    case PartlyCloudy = 'partly-cloudy';
    case Cloudy = 'cloudy';
    case Fog = 'fog';
    case Rain = 'rain';
    case Showers = 'showers';
    case Thunderstorm = 'thunderstorm';
    case FreezingRain = 'freezing-rain';
    case Sleet = 'sleet';
    case Snow = 'snow';
    case Unknown = 'unknown';

    /**
     * Whether people outdoors get wet or cold from the sky.
     */
    public function isWet(): bool
    {
        return match ($this) {
            self::Rain, self::Showers, self::Thunderstorm, self::FreezingRain, self::Sleet, self::Snow => true,
            default => false,
        };
    }

    /**
     * The condition of English words such as Environment Canada writes them, `Chance of flurries` or `Mainly
     * sunny`, the most severe word first.
     */
    public static function fromWords(string $words): self
    {
        $text = strtolower($words);

        return match (true) {
            str_contains($text, 'thunder') => self::Thunderstorm,
            str_contains($text, 'freezing') => self::FreezingRain,
            str_contains($text, 'ice pellet'), str_contains($text, 'sleet'), str_contains($text, 'rain and snow'), str_contains($text, 'snow and rain'), str_contains($text, 'wet snow') => self::Sleet,
            str_contains($text, 'snow'), str_contains($text, 'flurr'), str_contains($text, 'blizzard') => self::Snow,
            str_contains($text, 'shower') => self::Showers,
            str_contains($text, 'rain'), str_contains($text, 'drizzle') => self::Rain,
            str_contains($text, 'fog'), str_contains($text, 'mist'), str_contains($text, 'haze') => self::Fog,
            str_contains($text, 'partly'), str_contains($text, 'mix of sun'), str_contains($text, 'few clouds') => self::PartlyCloudy,
            str_contains($text, 'cloud'), str_contains($text, 'overcast') => self::Cloudy,
            str_contains($text, 'sun'), str_contains($text, 'clear') => self::Clear,
            default => self::Unknown,
        };
    }

    /**
     * The condition of a MET Norway symbol code, `lightrainshowers_day` or `partlycloudy_night`.
     */
    public static function fromMetSymbol(string $symbol): self
    {
        $code = (string) preg_replace('/_(?:day|night|polartwilight)$/D', '', $symbol);

        return match (true) {
            str_contains($code, 'thunder') => self::Thunderstorm,
            str_contains($code, 'sleet') => self::Sleet,
            str_contains($code, 'snow') => self::Snow,
            str_contains($code, 'showers') => self::Showers,
            str_contains($code, 'rain') => self::Rain,
            'fog' === $code => self::Fog,
            'cloudy' === $code => self::Cloudy,
            'partlycloudy' === $code, 'fair' === $code => self::PartlyCloudy,
            'clearsky' === $code => self::Clear,
            default => self::Unknown,
        };
    }
}
