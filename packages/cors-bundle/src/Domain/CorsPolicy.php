<?php

declare(strict_types=1);

namespace Phil\CorsBundle\Domain;

use Phil\CorsBundle\Domain\Exception\InvalidCorsPolicy;

/**
 * What one set of paths lets other origins do: which origins, by exact value or by anchored pattern, which methods and
 * request headers, which response headers the page may read, whether cookies and HTTP authentication travel, how long
 * the browser keeps a preflight, and whether a public page may reach a private network.
 *
 * Every value is checked when the policy is built: a wildcard with credentials, an origin with a path or a default
 * port, the origin `null`, a pattern that does not compile or is not anchored at both ends, and a method or header that
 * is not an HTTP token are refused with {@see InvalidCorsPolicy}. A built policy is immutable and holds no state
 * between requests.
 */
final readonly class CorsPolicy
{
    /**
     * The longest Origin header compared: a scheme, a host of 253 characters and a port fit well within it.
     */
    public const int ORIGIN_MAX_BYTES = 512;

    private const string WILDCARD = '*';

    private const string ORIGIN = '~^([a-z][a-z0-9+.-]*)://([a-z0-9](?:[a-z0-9._-]*[a-z0-9])?|\[[0-9a-f:.]+\])(?::([0-9]{1,5}))?$~D';

    private const string TOKEN = '~^[!#$%&\'*+.^_`|\~0-9A-Za-z-]+$~D';

    private const array DEFAULT_PORTS = ['http' => '80', 'https' => '443'];

    /**
     * @var array<string, true> the exact origins, lowercase
     */
    private array $origins;

    /**
     * @var list<string> the origin patterns, with their delimiters
     */
    private array $originPatterns;

    private bool $anyOrigin;

    /**
     * @var list<string> the methods, uppercase, in the configured order
     */
    public array $methods;

    /**
     * @var array<string, true>
     */
    private array $methodSet;

    /**
     * @var list<string> the request headers, as configured
     */
    public array $allowHeaders;

    /**
     * @var array<string, true> the request headers, lowercase
     */
    private array $allowHeaderSet;

    public bool $anyHeader;

    /**
     * @var list<string> the response headers the page may read, as configured
     */
    public array $exposeHeaders;

    /**
     * @param list<string> $allowOrigins        exact origins, such as `https://app.example.com`, or `*` alone for any
     * @param list<string> $allowOriginPatterns regular expressions anchored by `^` and `$`, without delimiters
     * @param list<string> $allowMethods        the methods a cross-origin request may use
     * @param list<string> $allowHeaders        the request headers it may send, or `*` alone for any
     * @param list<string> $exposeHeaders       the response headers its page may read, or `*` alone for any
     * @param bool         $allowCredentials    whether cookies, TLS client certificates and HTTP authentication travel
     * @param int|null     $maxAge              the seconds a browser keeps the answer to a preflight; null sends nothing
     * @param bool         $allowPrivateNetwork whether a public page may reach this server on a private network
     *
     * @throws InvalidCorsPolicy when a value is refused
     */
    public function __construct(
        array $allowOrigins = [],
        array $allowOriginPatterns = [],
        array $allowMethods = ['GET', 'HEAD', 'POST'],
        array $allowHeaders = [],
        array $exposeHeaders = [],
        public bool $allowCredentials = false,
        public ?int $maxAge = null,
        public bool $allowPrivateNetwork = false,
    ) {
        $this->anyOrigin = $this->wildcard($allowOrigins, 'allow_origins', $allowCredentials);
        $this->origins = $this->anyOrigin ? [] : $this->origins($allowOrigins);
        $this->originPatterns = array_map($this->originPattern(...), $allowOriginPatterns);

        if (!$this->anyOrigin && [] === $this->origins && [] === $this->originPatterns) {
            throw InvalidCorsPolicy::because('The policy allows no origin: give allow_origins or allow_origin_patterns.');
        }

        if ([] === $allowMethods) {
            throw InvalidCorsPolicy::because('The policy allows no method: list them in allow_methods.');
        }

        $this->methods = array_values(array_unique(array_map($this->method(...), $allowMethods)));
        $this->methodSet = array_fill_keys($this->methods, true);

        $this->anyHeader = $this->wildcard($allowHeaders, 'allow_headers', $allowCredentials);
        $this->allowHeaders = $this->anyHeader ? [] : array_map($this->token(...), $allowHeaders);
        $this->allowHeaderSet = array_fill_keys(array_map(strtolower(...), $this->allowHeaders), true);

        $this->exposeHeaders = $this->wildcard($exposeHeaders, 'expose_headers', $allowCredentials)
            ? [self::WILDCARD]
            : array_map($this->token(...), $exposeHeaders);

        if (null !== $maxAge && $maxAge < 0) {
            throw InvalidCorsPolicy::because(\sprintf('max_age is %d: it is a number of seconds, 0 or more.', $maxAge));
        }
    }

    /**
     * The policy of one path of the bundle's configuration, with the configuration's keys.
     *
     * @param array{allow_origins: list<string>, allow_origin_patterns: list<string>, allow_methods: list<string>, allow_headers: list<string>, expose_headers: list<string>, allow_credentials: bool, max_age: int|null, allow_private_network: bool} $config
     *
     * @throws InvalidCorsPolicy when a value is refused
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            $config['allow_origins'],
            $config['allow_origin_patterns'],
            $config['allow_methods'],
            $config['allow_headers'],
            $config['expose_headers'],
            $config['allow_credentials'],
            $config['max_age'],
            $config['allow_private_network'],
        );
    }

    /**
     * Whether a page of this origin may read the answer: the value of its Origin header, compared as sent.
     */
    public function allowsOrigin(string $origin): bool
    {
        if ('' === $origin || \strlen($origin) > self::ORIGIN_MAX_BYTES) {
            return false;
        }

        if ($this->anyOrigin || isset($this->origins[$origin])) {
            return true;
        }

        return array_any($this->originPatterns, static fn (string $pattern): bool => 1 === preg_match($pattern, $origin));
    }

    /**
     * What Access-Control-Allow-Origin says to an allowed origin: `*` when any origin is allowed without credentials,
     * the origin itself otherwise.
     */
    public function allowOriginValue(string $origin): string
    {
        return $this->anyOrigin ? self::WILDCARD : $origin;
    }

    public function allowsMethod(string $method): bool
    {
        return isset($this->methodSet[strtoupper($method)]);
    }

    /**
     * Whether every header a preflight names may be sent.
     *
     * @param list<string> $headers the names, lowercase
     */
    public function allowsHeaders(array $headers): bool
    {
        if ($this->anyHeader) {
            return true;
        }

        return array_all($headers, fn (string $header): bool => isset($this->allowHeaderSet[$header]));
    }

    /**
     * @param list<string> $values
     *
     * @throws InvalidCorsPolicy when `*` is mixed with other values, or given with credentials
     */
    private function wildcard(array $values, string $option, bool $credentials): bool
    {
        if (!\in_array(self::WILDCARD, $values, true)) {
            return false;
        }

        if (1 !== \count($values)) {
            throw InvalidCorsPolicy::because(\sprintf('%s mixes "*" with other values: "*" stands alone.', $option));
        }

        if ($credentials) {
            throw InvalidCorsPolicy::because(\sprintf('%s is "*" with allow_credentials: browsers refuse a wildcard with credentials, list the values.', $option));
        }

        return true;
    }

    /**
     * @param list<string> $origins
     *
     * @return array<string, true>
     */
    private function origins(array $origins): array
    {
        return array_fill_keys(array_map($this->origin(...), $origins), true);
    }

    private function origin(string $origin): string
    {
        $lower = strtolower($origin);

        if ('null' === $lower) {
            throw InvalidCorsPolicy::because('The origin "null" is what sandboxed frames and local files send: any page can claim it, so it is never allowed.');
        }

        if (1 !== preg_match(self::ORIGIN, $lower, $match) || \strlen($lower) > self::ORIGIN_MAX_BYTES) {
            throw InvalidCorsPolicy::because(\sprintf('"%s" is not an origin: a scheme, a host and an optional port, such as https://app.example.com, without a path or a trailing slash.', $origin));
        }

        $port = $match[3] ?? '';

        if ((int) $port > 65535 || (self::DEFAULT_PORTS[$match[1]] ?? null) === $port) {
            throw InvalidCorsPolicy::because(\sprintf('"%s" has the port %s: browsers send no default port and none above 65535.', $origin, $port));
        }

        return $lower;
    }

    private function originPattern(string $pattern): string
    {
        if (!str_starts_with($pattern, '^') || !str_ends_with($pattern, '$') || str_ends_with($pattern, '\$')) {
            throw InvalidCorsPolicy::because(\sprintf('The origin pattern "%s" is not anchored: it starts with ^ and ends with $, so that no other host can end or start with an allowed one.', $pattern));
        }

        return PathRule::compile($pattern, 'origin pattern');
    }

    private function method(string $method): string
    {
        if (self::WILDCARD === $method) {
            throw InvalidCorsPolicy::because('allow_methods is "*": list the methods by name.');
        }

        return strtoupper($this->token($method));
    }

    private function token(string $value): string
    {
        if (1 !== preg_match(self::TOKEN, $value)) {
            throw InvalidCorsPolicy::because(\sprintf('"%s" is not an HTTP method or header name.', $value));
        }

        return $value;
    }
}
