<?php

declare(strict_types=1);

namespace Phil\Geo\Tests\Unit\Geocoding;

use InvalidArgumentException;
use Phil\Geo\Coordinates;
use Phil\Geo\Exception\GeocodingFailed;
use Phil\Geo\Geocoding\Adapter\CachedGeocoder;
use Phil\Geo\Geocoding\Adapter\ChainGeocoder;
use Phil\Geo\Geocoding\Adapter\NominatimGeocoder;
use Phil\Geo\Geocoding\Geocoder;
use Phil\Geo\Geocoding\Place;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

#[CoversClass(ChainGeocoder::class)]
#[CoversClass(CachedGeocoder::class)]
final class ChainAndCacheTest extends TestCase
{
    public function testTheFirstThatFindsAnswers(): void
    {
        $empty = $this->geocoder('empty', []);
        $failing = $this->geocoder('failing', null);
        $found = $this->geocoder('found', ['A', 'B']);

        self::assertSame(['A', 'B'], $this->labels(new ChainGeocoder([$failing, $empty, $found, $this->geocoder('never', null)])->geocode('x')));
        self::assertSame(['A', 'B'], $this->labels(new ChainGeocoder([$empty, $found])->reverse(Coordinates::of(1, 2), 'fr')));
        self::assertSame(['failing geocode x -', 'empty geocode x -', 'found geocode x -', 'empty reverse 1,2 fr', 'found reverse 1,2 fr'], $this->asked);
        self::assertSame([], new ChainGeocoder([$failing, $empty])->geocode('x'));
        self::assertSame([], new ChainGeocoder([$empty, $failing])->reverse(Coordinates::of(0, 0)));
    }

    public function testEveryOneFailing(): void
    {
        $this->expectException(GeocodingFailed::class);
        $this->expectExceptionMessageIsOrContains('The geocoding provider "second" failed: down.');

        new ChainGeocoder([$this->geocoder('first', null), $this->geocoder('second', null)])->geocode('x');
    }

    public function testAProviderGivingABadCountryIsPassedOver(): void
    {
        $badCountry = new NominatimGeocoder(new MockHttpClient([new JsonMockResponse([['lat' => '1', 'lon' => '2', 'display_name' => 'Wrong', 'address' => ['country_code' => 'Canada']]])]), 'ua');

        self::assertSame(['A'], $this->labels(new ChainGeocoder([$badCountry, $this->geocoder('found', ['A'])])->geocode('x')));
    }

    public function testAChainHoldsOneAtLeast(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('A chain of geocoders holds one at least.');

        self::assertSame([], new ChainGeocoder([])->geocode('x'));
    }

    public function testAnswersAreKept(): void
    {
        $cache = new ArrayAdapter();
        $cached = new CachedGeocoder($this->geocoder('found', ['A']), $cache, 60);

        self::assertSame(['A'], $this->labels($cached->geocode('x', 'fr')));
        self::assertSame(['A'], $this->labels($cached->geocode('x', 'fr')));
        self::assertSame(['A'], $this->labels($cached->geocode('x')));
        self::assertSame(['A'], $this->labels($cached->geocode('y', 'fr')));
        self::assertSame(['A'], $this->labels($cached->reverse(Coordinates::of(1, 2))));
        self::assertSame(['A'], $this->labels($cached->reverse(Coordinates::of(1, 2))));
        self::assertSame(['A'], $this->labels($cached->geocode('1,2')));
        self::assertSame(['A'], $this->labels($cached->geocode('ab')));
        self::assertSame(['A'], $this->labels($cached->geocode('a', 'b')));
        self::assertSame(['found geocode x fr', 'found geocode x -', 'found geocode y fr', 'found reverse 1,2 -', 'found geocode 1,2 -', 'found geocode ab -', 'found geocode a b'], $this->asked);
        self::assertCount(7, $cache->getValues());
        self::assertSame([], array_filter(array_keys($cache->getValues()), static fn (string $key): bool => !str_starts_with($key, 'phil_geo.')));

        $expiry = new ReflectionProperty(ArrayAdapter::class, 'expiries');
        $expiries = $expiry->getValue($cache);
        self::assertIsArray($expiries);
        self::assertEqualsWithDelta(microtime(true) + 60, reset($expiries), 0.5);

        $default = new CachedGeocoder($this->geocoder('found', ['A']), $cache = new ArrayAdapter());
        $default->geocode('z');

        $expiries = $expiry->getValue($cache);
        self::assertIsArray($expiries);
        self::assertEqualsWithDelta(microtime(true) + 604800, reset($expiries), 0.5);

        $this->expectException(GeocodingFailed::class);
        new CachedGeocoder($this->geocoder('failing', null), new ArrayAdapter())->geocode('x');
    }

    /** @var list<string> */
    private array $asked = [];

    /**
     * @param ?list<string> $labels the labels of the places it finds, or null when it fails
     */
    private function geocoder(string $name, ?array $labels): Geocoder
    {
        return new readonly class($name, $labels, $this) implements Geocoder {
            /**
             * @param ?list<string> $labels
             */
            public function __construct(
                private string $name,
                private ?array $labels,
                private ChainAndCacheTest $test,
            ) {
            }

            public function geocode(string $address, ?string $locale = null): array
            {
                return $this->answer('geocode '.$address.' '.($locale ?? '-'));
            }

            public function reverse(Coordinates $at, ?string $locale = null): array
            {
                return $this->answer('reverse '.$at->toString().' '.($locale ?? '-'));
            }

            /**
             * @return list<Place>
             */
            private function answer(string $asked): array
            {
                $this->test->ask($this->name.' '.$asked);

                if (null === $this->labels) {
                    throw GeocodingFailed::because($this->name, 'down.');
                }

                return array_map(fn (string $label): Place => new Place(Coordinates::of(0, 0), $this->name, $label), $this->labels);
            }
        };
    }

    public function ask(string $what): void
    {
        $this->asked[] = $what;
    }

    /**
     * @param list<Place> $places
     *
     * @return list<?string>
     */
    private function labels(array $places): array
    {
        return array_map(static fn (Place $place): ?string => $place->label, $places);
    }
}
