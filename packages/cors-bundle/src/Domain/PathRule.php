<?php

declare(strict_types=1);

namespace Phil\CorsBundle\Domain;

use Phil\CorsBundle\Domain\Exception\InvalidCorsPolicy;

/**
 * A regular expression on the path of a request, such as `^/api/`, and the policy of the paths it matches. The
 * expression is written without delimiters and is compiled when the rule is built: one that does not compile is refused.
 */
final readonly class PathRule
{
    private string $regex;

    /**
     * @throws InvalidCorsPolicy when the pattern does not compile
     */
    public function __construct(public string $pattern, public CorsPolicy $policy)
    {
        $this->regex = self::compile($pattern, 'path pattern');
    }

    /**
     * @param array<array-key, mixed> $policy the policy, with the configuration's keys of {@see CorsPolicy::fromConfig()}
     *
     * @throws InvalidCorsPolicy when the pattern or the policy is refused
     */
    public static function fromConfig(string $pattern, array $policy): self
    {
        /** @var array{allow_origins: list<string>, allow_origin_patterns: list<string>, allow_methods: list<string>, allow_headers: list<string>, expose_headers: list<string>, allow_credentials: bool, max_age: int|null, allow_private_network: bool} $policy */
        return new self($pattern, CorsPolicy::fromConfig($policy));
    }

    public function matches(string $path): bool
    {
        return 1 === preg_match($this->regex, $path);
    }

    /**
     * The pattern between braces, the delimiters Symfony's request matchers use, after checking that it compiles.
     *
     * @throws InvalidCorsPolicy when it does not
     */
    public static function compile(string $pattern, string $what): string
    {
        $regex = '{'.$pattern.'}D';
        // preg_match() only warns about a pattern that does not compile, never throws: the warning is the answer.
        set_error_handler(static fn (): bool => true);
        $compiles = '' !== $pattern && false !== preg_match($regex, '');
        restore_error_handler();

        if (!$compiles) {
            throw InvalidCorsPolicy::because(\sprintf('The %s "%s" is not a regular expression that compiles.', $what, $pattern));
        }

        return $regex;
    }
}
