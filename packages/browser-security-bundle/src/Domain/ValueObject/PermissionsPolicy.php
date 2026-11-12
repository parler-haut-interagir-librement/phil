<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Domain\ValueObject;

use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;

/**
 * The value of a `Permissions-Policy` header, from an allowlist by feature: no origin gives `camera=()`, `self` and
 * `*` stay keywords, an origin is quoted, `geolocation=(self "https://maps.example.com")`.
 */
final readonly class PermissionsPolicy
{
    private const string FEATURE = '/^[a-z][a-z0-9-]{0,63}$/D';

    private const string ORIGIN = '~^https?://[A-Za-z0-9.-]+(?::[0-9]{1,5})?$~D';

    public string $header;

    /**
     * @param array<array-key, list<string>> $features origins by feature name
     *
     * @throws InvalidPolicy when a feature name or an origin is malformed
     */
    public function __construct(array $features)
    {
        $parts = [];

        foreach ($features as $feature => $origins) {
            $feature = (string) $feature;

            if (1 !== preg_match(self::FEATURE, $feature)) {
                throw new InvalidPolicy(\sprintf('"%s" is not a feature of a permissions policy.', $feature));
            }

            $parts[] = $feature.'=('.implode(' ', array_map($this->origin(...), $origins)).')';
        }

        $this->header = implode(', ', $parts);
    }

    private function origin(string $origin): string
    {
        if ('self' === $origin || '*' === $origin) {
            return $origin;
        }

        if (1 !== preg_match(self::ORIGIN, $origin)) {
            throw new InvalidPolicy(\sprintf('"%s" is not an origin of a permissions policy: self, * or a scheme and a host.', $origin));
        }

        return '"'.$origin.'"';
    }
}
