<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * A route a site declares for counting: a name, and a path pattern such as `/events/{slug}`.
 *
 * **The counted page is a declared route, never a URL.** A `{placeholder}` segment matches exactly one non-empty
 * path segment and is never kept: `/u/{name}` counts every profile under one route and keeps no name. A literal
 * segment matches itself, case-sensitively.
 */
final readonly class DeclaredRoute
{
    /**
     * @param list<string|null> $segments a literal segment, or `null` for a placeholder
     * @param int               $literals the number of literal segments
     */
    private function __construct(
        public string $name,
        public string $pattern,
        private array $segments,
        private int $literals,
    ) {
    }

    public static function of(string $name, string $pattern): self
    {
        if (1 !== preg_match(CounterKey::NAME_PATTERN, $name)) {
            throw InvalidValue::because(\sprintf('"%s" is not a route name: 1 to 64 characters among a-z, 0-9, "_", "." and "-", starting with a letter.', $name));
        }

        if (!str_starts_with($pattern, '/')) {
            throw InvalidValue::because(\sprintf('The route pattern "%s" does not start with "/".', $pattern));
        }

        $segments = [];

        foreach (self::split($pattern) as $segment) {
            if (1 === preg_match('/^\{[A-Za-z_][A-Za-z0-9_]*\}$/D', $segment)) {
                $segments[] = null;

                continue;
            }

            if (1 !== preg_match('/^[A-Za-z0-9._~!$&\'()*+,;=:@%-]+$/D', $segment)) {
                throw InvalidValue::because(\sprintf('The route pattern "%s" has a segment that is neither a literal nor a {placeholder}: "%s".', $pattern, $segment));
            }

            $segments[] = $segment;
        }

        return new self($name, $pattern, $segments, \count(array_filter($segments, static fn (?string $segment): bool => null !== $segment)));
    }

    /**
     * @param list<string> $path the path segments, as {@see self::split()} returns them
     */
    public function matches(array $path): bool
    {
        if (\count($path) !== \count($this->segments)) {
            return false;
        }

        return array_all($this->segments, static fn (?string $segment, int $index): bool => null === $segment || $segment === $path[$index]);
    }

    /**
     * The number of literal segments: when two routes match a path, the one with more literals wins.
     */
    public function specificity(): int
    {
        return $this->literals;
    }

    /**
     * The number of segments a matching path has.
     */
    public function length(): int
    {
        return \count($this->segments);
    }

    /**
     * The segments of a path, a trailing slash ignored: `/` is no segment, `/a/b/` is `a` and `b`.
     *
     * @return list<string>
     */
    public static function split(string $path): array
    {
        $trimmed = trim($path, '/');

        return '' === $trimmed ? [] : explode('/', $trimmed);
    }

    /**
     * Whether two routes match exactly the same paths.
     */
    public function hasSameShapeAs(self $other): bool
    {
        return $this->segments === $other->segments;
    }
}
