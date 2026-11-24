<?php

declare(strict_types=1);

namespace Phil\Geo\Geocoding\Adapter;

use Phil\Geo\Exception\GeocodingFailed;
use Phil\Geo\Exception\InvalidPlace;
use Phil\Geo\Geocoding\IpLocator;
use Phil\Geo\Geocoding\Place;
use Phil\Geo\Geocoding\Timeouts;
use SensitiveParameter;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * ipstack, with an access key, over HTTPS. A plan that answers over plain HTTP only is a base URL of `http://`, chosen
 * knowingly: the visitor's IP address, which is personal information, and the access key then cross the network in
 * clear.
 */
final readonly class IpstackLocator implements IpLocator
{
    public const string NAME = 'ipstack';

    public const string BASE_URL = 'https://api.ipstack.com';

    private HttpClientInterface $client;

    public function __construct(
        HttpClientInterface $client,
        #[SensitiveParameter]
        private string $accessKey,
        private string $baseUrl = self::BASE_URL,
        Timeouts $timeouts = new Timeouts(),
    ) {
        $this->client = $timeouts->apply($client);
    }

    public function locate(string $ip): ?Place
    {
        if (false === filter_var($ip, \FILTER_VALIDATE_IP)) {
            throw InvalidPlace::ip($ip);
        }

        // A private or reserved address — a proxy's own, behind a proxy the application does not trust — locates no
        // visitor, and is never looked up.
        if (false === filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        $answer = Answer::of($this->client->request('GET', rtrim($this->baseUrl, '/').'/'.$ip, ['query' => ['access_key' => $this->accessKey]]), self::NAME);

        $error = $answer->string('error', 'info');

        if (null !== $error) {
            throw GeocodingFailed::because(self::NAME, $error);
        }

        if (null === $answer->float('latitude')) {
            return null;
        }

        $label = array_filter([$answer->string('city'), $answer->string('region_name'), $answer->string('country_name')], static fn (?string $part): bool => null !== $part);

        return new Place(
            $answer->coordinates('latitude', 'longitude'),
            self::NAME,
            [] === $label ? null : implode(', ', $label),
            null,
            $answer->string('city'),
            $answer->string('region_name'),
            $answer->string('zip'),
            $answer->country($answer->string('country_code')),
        );
    }
}
