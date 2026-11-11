<?php

declare(strict_types=1);

namespace Phil\Geo\Geocoding\Adapter;

use Phil\Geo\Coordinates;
use Phil\Geo\Exception\GeocodingFailed;
use Phil\Geo\Exception\InvalidCoordinates;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * What a provider answered, read as JSON: its values by their path, typed, and missing ones null.
 *
 * A failure to read the answer is told by its HTTP status or its kind only. The HTTP client's own message, and the
 * exception that carries it, name the whole request URL, whose query holds the key and the address or IP address
 * asked about; neither goes into the failure, so a log or an error page never shows them.
 *
 * @internal
 */
final readonly class Answer
{
    /**
     * @param array<mixed> $data
     */
    private function __construct(
        private array $data,
        private string $provider,
    ) {
    }

    public static function of(ResponseInterface $response, string $provider): self
    {
        try {
            return new self($response->toArray(), $provider);
        } catch (ExceptionInterface $exception) {
            throw GeocodingFailed::because($provider, self::reason($exception));
        }
    }

    /**
     * A country the provider gave, checked: two letters of ISO 3166-1, or no country. Anything else is an answer the
     * provider should not give, and a failure, so that a chain asks the next provider.
     */
    public function country(?string $code): ?string
    {
        if (null !== $code && 1 !== preg_match('/^[A-Za-z]{2}$/D', $code)) {
            throw GeocodingFailed::because($this->provider, \sprintf('it answered "%s" as a country, which is no ISO 3166-1 alpha-2 code.', $code));
        }

        return $code;
    }

    public function string(string|int ...$path): ?string
    {
        $value = $this->value($path);

        return \is_string($value) && '' !== trim($value) ? trim($value) : (\is_int($value) || \is_float($value) ? (string) $value : null);
    }

    public function float(string|int ...$path): ?float
    {
        $value = $this->value($path);

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @return list<self>
     */
    public function list(string|int ...$path): array
    {
        $value = $this->value($path);
        $list = [];

        foreach (\is_array($value) ? $value : [] as $item) {
            if (\is_array($item)) {
                $list[] = new self($item, $this->provider);
            }
        }

        return $list;
    }

    public function coordinates(string $latitude, string $longitude): Coordinates
    {
        $at = [$this->float(...explode('.', $latitude)), $this->float(...explode('.', $longitude))];

        if (null === $at[0] || null === $at[1]) {
            throw GeocodingFailed::because($this->provider, 'it answered a place without coordinates.');
        }

        try {
            return Coordinates::of(...$at);
        } catch (InvalidCoordinates $invalidCoordinates) {
            throw GeocodingFailed::because($this->provider, $invalidCoordinates->getMessage(), $invalidCoordinates);
        }
    }

    private static function reason(ExceptionInterface $exception): string
    {
        return match (true) {
            $exception instanceof HttpExceptionInterface => \sprintf('it answered HTTP %d.', $exception->getResponse()->getStatusCode()),
            $exception instanceof DecodingExceptionInterface => 'it answered something other than JSON.',
            $exception instanceof TransportExceptionInterface => 'it could not be reached.',
            default => 'its answer could not be read.',
        };
    }

    /**
     * @param array<string|int> $path
     */
    private function value(array $path): mixed
    {
        $value = $this->data;

        foreach ($path as $key) {
            $value = \is_array($value) ? $value[$key] ?? null : null;
        }

        return $value;
    }
}
