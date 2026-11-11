<?php

declare(strict_types=1);

namespace Phil\GeoBundle\Tests\Functional\App;

use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * Stands for Nominatim: every address is the Olympic Stadium, and the requests are counted.
 */
final class NominatimResponses
{
    /** @var list<string> */
    public array $urls = [];

    public function __invoke(string $method, string $url): JsonMockResponse
    {
        $this->urls[] = $url;

        return new JsonMockResponse([['lat' => '45.558', 'lon' => '-73.5517', 'display_name' => 'Stade olympique, Montréal', 'address' => ['city' => 'Montréal', 'country_code' => 'ca']]]);
    }
}
