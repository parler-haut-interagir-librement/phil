<?php

declare(strict_types=1);

namespace Phil\EventWeather;

use Phil\Url\Url;

/**
 * What a page that shows a provider's data must say of it: the credit line, the licence and its address, and the
 * provider's own page. The licences of both providers require it beside the data.
 */
final readonly class Attribution
{
    public function __construct(
        public string $provider,
        public string $text,
        public string $licence,
        public Url $licenceUrl,
        public Url $source,
    ) {
    }

    /**
     * Environment and Climate Change Canada's data, under its Data Servers End-use Licence.
     */
    public static function environmentCanada(): self
    {
        return new self(
            'Environment and Climate Change Canada',
            'Data Source: Environment and Climate Change Canada',
            'Environment and Climate Change Canada Data Servers End-use Licence',
            Url::fromString('https://eccc-msc.github.io/open-data/licence/readme_en/'),
            Url::fromString('https://weather.gc.ca/'),
        );
    }

    /**
     * MET Norway's data, under the Creative Commons Attribution 4.0 licence and the Norwegian Licence for Open
     * Government Data 2.0.
     */
    public static function metNorway(): self
    {
        return new self(
            'MET Norway',
            'Data from MET Norway',
            'CC BY 4.0 and NLOD 2.0',
            Url::fromString('https://creativecommons.org/licenses/by/4.0/'),
            Url::fromString('https://api.met.no/'),
        );
    }
}
