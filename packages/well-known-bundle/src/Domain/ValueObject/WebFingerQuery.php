<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Domain\ValueObject;

use Phil\WellKnownBundle\Domain\Exception\InvalidValue;

/**
 * A WebFinger query (RFC 7033, section 4.1): one `resource` and any number of `rel`, read from the raw query string.
 *
 * The query string is read here rather than from PHP's parsed parameters, which keep only the last of the repeated
 * `rel`. Other parameters are ignored. The query is bounded: 4,096 bytes and 32 `rel` at most.
 */
final readonly class WebFingerQuery
{
    public const int MAX_LENGTH = 4096;

    public const int MAX_RELS = 32;

    /**
     * @param list<string> $rels the link relations asked for, in their order; empty for every link
     */
    private function __construct(
        public WebFingerResource $resource,
        public array $rels,
    ) {
    }

    /**
     * @throws InvalidValue when the query has no `resource`, more than one, a resource that is not one, or exceeds its bounds
     */
    public static function parse(string $query): self
    {
        if (\strlen($query) > self::MAX_LENGTH) {
            throw InvalidValue::because(\sprintf('A WebFinger query is %d bytes at most.', self::MAX_LENGTH));
        }

        $resources = [];
        $rels = [];

        foreach (explode('&', $query) as $pair) {
            [$name, $value] = explode('=', $pair, 2) + [1 => ''];

            match (urldecode($name)) {
                'resource' => $resources[] = urldecode($value),
                'rel' => $rels[] = urldecode($value),
                default => null,
            };
        }

        if (1 !== \count($resources)) {
            throw InvalidValue::because('A WebFinger query names one resource.');
        }

        if (\count($rels) > self::MAX_RELS) {
            throw InvalidValue::because(\sprintf('A WebFinger query names %d link relations at most.', self::MAX_RELS));
        }

        return new self(WebFingerResource::parse($resources[0]), array_values(array_unique($rels)));
    }
}
