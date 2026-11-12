<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * A list of host names a referrer host is matched against: search engines, social networks, referrer spam.
 *
 * The application builds it from the lists it chooses (public-domain lists are the intended source) and the
 * bundle's own code does the matching: no referrer-parsing library is involved.
 *
 * An entry matches the host itself and every subdomain of it: `example.com` matches `example.com` and
 * `www.example.com`, never `badexample.com`.
 *
 * **A label written `{}` stands for a public suffix, never for a registrable domain**: one top-level label
 * (`com`, `fr`), or a generic second-level label followed by a two-letter country code (`co.uk`, `com.au`). So
 * `google.{}` matches `google.com`, `www.google.co.uk` and `google.fr`, but not `google.attacker.example`,
 * which anyone can register: a forged referrer would otherwise be classified as a search engine, and a spam
 * entry would discard legitimate sites under the same prefix.
 *
 * **Matching costs the labels of the host, not the length of the list.** A literal entry is kept in a hash set,
 * and a host is looked up there once per suffix (`www.example.com`, `example.com`, `com`). The entries with a `{}`
 * label are kept in a tree of labels read from the right, where `{}` is one more branch: the host is walked down that
 * tree one label at a time, so a list of thousands of templated entries costs the same walk as a list of one. No
 * pattern is built from the entries, so no list is too long to match.
 */
final readonly class HostList
{
    /**
     * The generic second-level labels a country code registry places under its code (`co.uk`, `com.au`).
     */
    private const array GENERIC_SECOND_LEVEL = [
        'ac' => true, 'co' => true, 'com' => true, 'edu' => true, 'go' => true, 'gob' => true,
        'gov' => true, 'ne' => true, 'net' => true, 'or' => true, 'org' => true,
    ];

    private const string WILDCARD = '{}';

    private const string LETTERS = 'abcdefghijklmnopqrstuvwxyz';

    private const string TOP_LEVEL_CHARACTERS = 'abcdefghijklmnopqrstuvwxyz0123456789-';

    /**
     * @param list<string>          $entries
     * @param array<string, string> $literals the entries without a `{}` label, keyed by themselves
     * @param array<string, bool>   $tree     the tree of the `{}` entries: every node, named by the labels that lead to
     *                                        it from the right, each followed by a dot (`{}.google.`), to whether an
     *                                        entry ends there
     */
    private function __construct(
        public array $entries,
        private array $literals,
        private array $tree,
    ) {
    }

    /**
     * @param iterable<string> $entries
     */
    public static function of(iterable $entries): self
    {
        $normalized = [];
        $literals = [];
        $tree = [];

        foreach ($entries as $entry) {
            $host = strtolower(trim($entry));

            if (1 !== preg_match('/^(?:\{\}|[a-z0-9_](?:[a-z0-9_-]*[a-z0-9_])?)(?:\.(?:\{\}|[a-z0-9_](?:[a-z0-9_-]*[a-z0-9_])?))*$/', $host)) {
                throw InvalidValue::because(\sprintf('"%s" is not a host name a referrer can be matched against.', $entry));
            }

            if (isset($normalized[$host])) {
                continue;
            }

            $normalized[$host] = $host;

            if (!str_contains($host, self::WILDCARD)) {
                $literals[$host] = $host;

                continue;
            }

            $node = '';

            foreach (array_reverse(explode('.', $host)) as $label) {
                $node .= $label.'.';
                $tree[$node] ??= false;
            }

            $tree[$node] = true;
        }

        return new self(array_values($normalized), $literals, $tree);
    }

    public static function empty(): self
    {
        return new self([], [], []);
    }

    public function matches(string $host): bool
    {
        // An empty host matches nothing: every entry, literal or templated, holds at least one character.
        $host = rtrim(strtolower($host), '.');

        for ($suffix = $host;; $suffix = substr($suffix, $dot + 1)) {
            if (isset($this->literals[$suffix])) {
                return true;
            }

            $dot = strpos($suffix, '.');

            if (false === $dot) {
                break;
            }
        }

        // A list without a `{}` entry has no tree to walk: its literal lookups have answered.
        return [] !== $this->tree && $this->walk(explode('.', $host));
    }

    /**
     * Walks the tree of the `{}` entries down the labels of the host, from the right: a literal label follows the
     * branch of the same label, `{}` follows its branch over one label or over a generic second level and its country
     * code. Each pair of a node and a position is visited once, so the walk is bounded by the labels of the host
     * times the depth of the tree, whatever the number of entries.
     *
     * @param non-empty-list<string> $labels
     */
    private function walk(array $labels): bool
    {
        $pending = [['', \count($labels)]];
        $seen = [];

        while (null !== ($state = array_pop($pending))) {
            [$node, $left] = $state;

            if ($this->tree[$node] ?? false) {
                return true;
            }

            if (0 === $left || ($seen[$node][$left] ?? false)) {
                continue;
            }

            $seen[$node][$left] = true;
            $label = $labels[$left - 1];

            // A host label written `{}` is not a host name: it never stands in for the wildcard of an entry.
            if (self::WILDCARD !== $label && isset($this->tree[$node.$label.'.'])) {
                $pending[] = [$node.$label.'.', $left - 1];
            }

            $wildcard = $node.self::WILDCARD.'.';

            if (!isset($this->tree[$wildcard])) {
                continue;
            }

            if ($this->isTopLevel($label)) {
                $pending[] = [$wildcard, $left - 1];
            }

            if (1 < $left && $this->isCountryCode($label) && isset(self::GENERIC_SECOND_LEVEL[$labels[$left - 2]])) {
                $pending[] = [$wildcard, $left - 2];
            }
        }

        return false;
    }

    private function isTopLevel(string $label): bool
    {
        return '' !== $label && \strlen($label) === strspn($label, self::TOP_LEVEL_CHARACTERS);
    }

    private function isCountryCode(string $label): bool
    {
        return 2 === \strlen($label) && 2 === strspn($label, self::LETTERS);
    }
}
