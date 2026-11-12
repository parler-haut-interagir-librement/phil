<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Domain\Syntax;

use Phil\BrowserSecurityBundle\Domain\Enum\Protection;
use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;
use Phil\BrowserSecurityBundle\Domain\ValueObject\PermissionsPolicy;

/**
 * The values of each protection: what the configuration, an attribute or a route may write, what adding to a value
 * means, and the text of its header. A value is checked once, where it is declared.
 *
 * @phpstan-type Value string|true|list<string>|array<string, list<string>>
 */
final class ProtectionSyntax
{
    private const string TRANSPORT = '/^max-age=[0-9]{1,10}(?:; includeSubDomains)?(?:; preload)?$/D';

    private const string COOKIE = '/^[!#$%&\'*+\-.0-9A-Z^_`a-z|~]{1,128}$/D';

    private const string HOST = '/^\.?[a-z0-9](?:[a-z0-9-]{0,62})(?:\.[a-z0-9](?:[a-z0-9-]{0,62}))*$/D';

    private const array SITE_DATA = ['*', 'cache', 'cookies', 'storage', 'clientHints', 'executionContexts', 'prefetchCache', 'prerenderCache'];

    private const array DESTINATIONS = ['script', 'style'];

    /**
     * @return Value
     *
     * @throws InvalidPolicy when the value is not one the protection takes
     */
    public static function normalize(Protection $protection, mixed $value): string|bool|array
    {
        if ($protection->isCollection()) {
            return self::collection($protection, $value);
        }

        return match ($protection) {
            Protection::StrictTransportSecurity => self::transport($value),
            Protection::HttpsRedirect => true === $value ? true : throw new InvalidPolicy('The value of "https-redirect" is true; "remove" turns it off.'),
            default => self::keyword($protection, $value),
        };
    }

    /**
     * The value of a protection that holds a collection, which a page may add to.
     *
     * @return list<string>|array<string, list<string>>
     *
     * @throws InvalidPolicy when the protection holds one value, or the value is not one it takes
     */
    public static function collection(Protection $protection, mixed $value): array
    {
        return match ($protection) {
            Protection::PermissionsPolicy => self::permissions($value),
            Protection::ClearSiteData => self::among($protection, $value, self::SITE_DATA),
            Protection::IntegrityPolicy, Protection::IntegrityPolicyReportOnly => self::among($protection, $value, self::DESTINATIONS),
            Protection::SignedCookies => self::matching($protection, $value, self::COOKIE),
            Protection::ExternalRedirects => self::hosts($value),
            default => throw new InvalidPolicy(\sprintf('A page cannot add to %s, which holds one value: replace it.', $protection->value)),
        };
    }

    /**
     * What a page adds to a collection: the origins of a feature join those it has, the items of a list follow its own.
     *
     * @param list<string>|array<string, list<string>> $current
     * @param list<string>|array<string, list<string>> $added
     *
     * @return list<string>|array<string, list<string>>
     */
    public static function merge(array $current, array $added): array
    {
        if ([] === $added) {
            return $current;
        }

        if (array_is_list($added)) {
            return array_values(array_unique([...self::items($current), ...self::items($added)]));
        }

        $groups = self::groups($current);

        foreach (self::groups($added) as $key => $values) {
            $groups[$key] = array_values(array_unique([...$groups[$key] ?? [], ...$values]));
        }

        return $groups;
    }

    /**
     * The text of a header, empty when its value has nothing to say.
     *
     * @param Value       $value
     * @param string|null $group the endpoint of the reports, or none
     */
    public static function render(Protection $protection, string|bool|array $value, ?string $group = null): string
    {
        if (\is_string($value)) {
            return $value.(null !== $group && $protection->reports() ? '; report-to="'.$group.'"' : '');
        }

        if (!\is_array($value) || [] === $value) {
            return '';
        }

        return match ($protection) {
            Protection::PermissionsPolicy => new PermissionsPolicy(self::groups($value))->header,
            Protection::IntegrityPolicy, Protection::IntegrityPolicyReportOnly => 'blocked-destinations=('.implode(' ', self::items($value)).'), sources=(inline)'.(null === $group ? '' : ', endpoints=('.$group.')'),
            default => '"'.implode('", "', self::items($value)).'"',
        };
    }

    /**
     * The items of a list, none for a value that is not one.
     *
     * @param Value|null $value
     *
     * @return list<string>
     */
    public static function items(string|bool|array|null $value): array
    {
        $items = [];

        foreach (\is_array($value) ? $value : [] as $item) {
            if (\is_string($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * The groups of a map, none for a value that is not one.
     *
     * @param Value|null $value
     *
     * @return array<string, list<string>>
     */
    public static function groups(string|bool|array|null $value): array
    {
        $groups = [];

        foreach (\is_array($value) ? $value : [] as $key => $item) {
            if (\is_string($key) && \is_array($item)) {
                $groups[$key] = $item;
            }
        }

        return $groups;
    }

    /**
     * @param list<string> $allowed
     *
     * @return list<string>
     */
    private static function among(Protection $protection, mixed $value, array $allowed): array
    {
        $list = self::strings($protection, $value);
        $unknown = array_diff($list, $allowed);

        if ([] !== $unknown) {
            throw new InvalidPolicy(\sprintf('"%s" is not a value of %s: %s.', implode('", "', $unknown), $protection->value, implode(', ', $allowed)));
        }

        return array_values(array_unique($list));
    }

    /**
     * @return list<string>
     */
    private static function matching(Protection $protection, mixed $value, string $pattern): array
    {
        $list = self::strings($protection, $value);

        foreach ($list as $item) {
            if (1 !== preg_match($pattern, $item)) {
                throw new InvalidPolicy(\sprintf('"%s" is not a value of %s.', $item, $protection->value));
            }
        }

        return array_values(array_unique($list));
    }

    /**
     * @return list<string> the hosts in lower case
     */
    private static function hosts(mixed $value): array
    {
        return self::matching(Protection::ExternalRedirects, array_map(strtolower(...), self::strings(Protection::ExternalRedirects, $value)), self::HOST);
    }

    /**
     * @return list<string>
     */
    private static function strings(Protection $protection, mixed $value): array
    {
        if (!\is_array($value) || !array_is_list($value) || array_any($value, static fn (mixed $item): bool => !\is_string($item))) {
            throw new InvalidPolicy(\sprintf('The value of %s is a list of strings.', $protection->value));
        }

        /** @var list<string> $value */
        return $value;
    }

    private static function transport(mixed $value): string
    {
        if (!\is_string($value) || 1 !== preg_match(self::TRANSPORT, $value)) {
            throw new InvalidPolicy('The value of Strict-Transport-Security is "max-age=<seconds>", then "; includeSubDomains" and "; preload".');
        }

        return $value;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function permissions(mixed $value): array
    {
        if (!\is_array($value) || ([] !== $value && array_is_list($value))) {
            throw new InvalidPolicy('The value of Permissions-Policy is a map of origins by feature.');
        }

        $features = [];

        foreach ($value as $feature => $origins) {
            $features[(string) $feature] = self::strings(Protection::PermissionsPolicy, $origins);
        }

        // Written once to check the names of the features and the origins.
        new PermissionsPolicy($features);

        return $features;
    }

    private static function keyword(Protection $protection, mixed $value): string
    {
        $keywords = $protection->keywords();

        if (!\is_string($value) || !\in_array($value, $keywords, true)) {
            throw new InvalidPolicy(\sprintf('"%s" is not a value of %s: %s.', \is_string($value) ? $value : get_debug_type($value), $protection->value, implode(', ', $keywords)));
        }

        return $value;
    }
}
