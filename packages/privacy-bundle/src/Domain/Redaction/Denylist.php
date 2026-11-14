<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Redaction;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * The keys whose values never reach a log line: those the application lists, such as `password`, `token`,
 * `secret`, the `Authorization` and `Cookie` headers.
 *
 * **The list belongs to the application**, in configuration, and **it is empty by default**: with no
 * `phil_privacy.log_redaction.denylist`, no key is redacted, not even `password`. The bundle decides only how a
 * key is compared.
 *
 * **A key is compared by its normalised form, exactly.** Case, `_`, `-`, `.` and spaces are dropped on both
 * sides, so `totp_secret`, `totpSecret` and `TOTP-Secret` are one key and an HTTP header matches whatever case
 * it arrives in. The comparison is **not** a substring search: `code` would otherwise redact `postal_code` and
 * `country_code`, and a list that redacts everything stops being read.
 *
 * **A pattern is explicit, and opt-in.** An entry that starts or ends with `*` matches a family of keys rather
 * than one: `seed*` every key that starts with `seed` (`seed`, `seedPhrase`), `*seed` every key that ends with
 * it (`totp_seed`, `recoverySeed`), `*seed*` every key that contains it. The part between the stars is
 * normalised like any key, so `*_seed` and `*seed` are one pattern. A star anywhere else is refused: a pattern
 * is a deliberate widening, written where it can be read, never a guess at what a key might look like.
 */
final readonly class Denylist
{
    private const string WILDCARD = '*';

    /**
     * @param array<string, string> $keys      the normalised keys, each under itself
     * @param array<string, string> $prefixes  the normalised stems of `stem*` patterns, each under itself
     * @param array<string, string> $suffixes  the normalised stems of `*stem` patterns, each under itself
     * @param array<string, string> $fragments the normalised stems of `*stem*` patterns, each under itself
     */
    private function __construct(
        private array $keys,
        private array $prefixes,
        private array $suffixes,
        private array $fragments,
    ) {
    }

    public static function of(string ...$keys): self
    {
        return self::fromList($keys);
    }

    /**
     * @param array<string> $keys exact keys, and patterns that start or end with `*`
     */
    public static function fromList(array $keys): self
    {
        $exact = [];
        $prefixes = [];
        $suffixes = [];
        $fragments = [];

        foreach ($keys as $key) {
            $entry = trim($key);
            $leading = str_starts_with($entry, self::WILDCARD);
            $rest = $leading ? substr($entry, 1) : $entry;
            $trailing = str_ends_with($rest, self::WILDCARD);
            $stem = $trailing ? substr($rest, 0, -1) : $rest;

            if (str_contains($stem, self::WILDCARD)) {
                throw InvalidPrivacyValue::because(\sprintf('"%s" is not a denylist key: "*" may only open or close a pattern, such as "seed*", "*seed" or "*seed*".', $key));
            }

            $form = self::normalise($stem);

            if ('' === $form) {
                throw InvalidPrivacyValue::because(\sprintf('"%s" is not a denylist key: nothing is left once case, separators and pattern stars are dropped.', $key));
            }

            match (true) {
                $leading && $trailing => $fragments[$form] = $form,
                $leading => $suffixes[$form] = $form,
                $trailing => $prefixes[$form] = $form,
                default => $exact[$form] = $form,
            };
        }

        return new self($exact, $prefixes, $suffixes, $fragments);
    }

    public function denies(string $key): bool
    {
        $form = self::normalise($key);

        return isset($this->keys[$form])
            || array_any($this->prefixes, static fn (string $prefix): bool => str_starts_with($form, $prefix))
            || array_any($this->suffixes, static fn (string $suffix): bool => str_ends_with($form, $suffix))
            || array_any($this->fragments, static fn (string $fragment): bool => str_contains($form, $fragment));
    }

    /**
     * @return list<string> the normalised keys, and the patterns with their stars, sorted
     */
    public function keys(): array
    {
        $keys = $this->keys;

        foreach ($this->prefixes as $stem) {
            $keys[] = $stem.self::WILDCARD;
        }

        foreach ($this->suffixes as $stem) {
            $keys[] = self::WILDCARD.$stem;
        }

        foreach ($this->fragments as $stem) {
            $keys[] = self::WILDCARD.$stem.self::WILDCARD;
        }

        sort($keys);

        return $keys;
    }

    private static function normalise(string $key): string
    {
        return strtolower(str_replace(['_', '-', '.', ' '], '', trim($key)));
    }
}
