<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Domain\ValueObject;

use Phil\WellKnownBundle\Domain\Exception\InvalidValue;

/**
 * Where a document is served: a name under `/.well-known/` (RFC 8615), such as `security.txt` or `matrix/server`, or
 * an absolute path for a document a well-known one points to, such as `/nodeinfo/2.1`.
 *
 * A segment starts with a letter or a digit and holds letters, digits, `.`, `_`, `~` and `-`: nothing to percent-encode,
 * no `..`, no empty segment. The route of the document is named after its path unless it is given a name.
 */
final readonly class WellKnownPath
{
    public const string PREFIX = '/.well-known/';

    public const int MAX_LENGTH = 200;

    private const string SEGMENTS = '~^[A-Za-z0-9][A-Za-z0-9._\~-]*(?:/[A-Za-z0-9][A-Za-z0-9._\~-]*)*$~D';

    private const string ROUTE = '~^[A-Za-z0-9_.]{1,128}$~D';

    private function __construct(
        public string $path,
        public string $route,
    ) {
    }

    /**
     * @throws InvalidValue when the path or the route name is outside their grammar
     */
    public static function of(string $path, ?string $route = null): self
    {
        $absolute = str_starts_with($path, '/');
        $name = $absolute ? substr($path, 1) : $path;

        if (\strlen($name) > self::MAX_LENGTH || 1 !== preg_match(self::SEGMENTS, $name)) {
            throw InvalidValue::because(\sprintf('"%s" is not a well-known path: segments of letters, digits, ".", "_", "~" and "-", each starting with a letter or a digit, %d characters at most.', $path, self::MAX_LENGTH));
        }

        $route ??= 'phil_well_known_'.trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($name)), '_');

        if (1 !== preg_match(self::ROUTE, $route)) {
            throw InvalidValue::because(\sprintf('"%s" is not a route name: 1 to 128 letters, digits, "_" or ".".', $route));
        }

        return new self($absolute ? $path : self::PREFIX.$path, $route);
    }
}
