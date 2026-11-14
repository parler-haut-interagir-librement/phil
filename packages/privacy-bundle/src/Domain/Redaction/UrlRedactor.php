<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Redaction;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * Redacts the parts of a URL that carry a credential, wherever the URL appears in a string.
 *
 * - **The user information of an absolute URL**, whole: `redis://app:secret@valkey:6379` becomes
 *   `redis://[redacted]@valkey:6379`. A DSN carries its password there, and the messages of Doctrine, of a
 *   cache adapter or of a mailer transport quote the DSN they failed to reach. The user name goes with the
 *   password, because a token passed as a user name (`https://token@host`) is a credential too.
 * - **Every query string**, whole: `/reset?token=abc&lang=fr` becomes `/reset?[redacted]`. Parameter names
 *   are dropped along with values, because a name can be the secret (`?abc123`) and because a list of
 *   "safe" parameters is a list that someone forgets to update.
 * - **The segment after a declared token path prefix**: with `/p/` declared, `/p/abc123/edit` becomes
 *   `/p/[redacted]/edit`. A token in the path survives query string redaction, so the routes that carry one are
 *   **declared** in configuration rather than guessed by a pattern. Every prefix is matched by one pattern, the
 *   longest first; where one declared prefix starts another (`/p/` and `/p/x/`), the token of each goes:
 *   `/p/x/abc` becomes `/p/[redacted]/[redacted]`, whatever the order of declaration.
 *
 * **Redaction errs towards too much.** Any `?` directly followed by non-space characters is treated as the start
 * of a query string, wherever it stands — at the start of the text, after a space, after a path — so `what?now`
 * is redacted too; a log line that loses a word is cheaper than one that keeps a token. A fragment (`#...`) is
 * left alone: browsers do not send it to a server.
 *
 * **A raw query string, without its `?`, is not recognisable** (`token=abc&x=1` is what
 * `Request::getQueryString()` returns). The key it is logged under (`query`, `query_string`) belongs in the
 * denylist.
 */
final readonly class UrlRedactor
{
    public const string PLACEHOLDER = '[redacted]';

    private const string QUERY_STRING = '~\?[^\s#"\'<>]+~';

    /**
     * A scheme, `//`, and everything up to the last `@` before the host ends — at a `/`, `?`, `#`, whitespace or
     * quote — so that a password holding an `@` is taken whole.
     */
    private const string USER_INFORMATION = '~([a-z][a-z0-9+.-]*://)[^/?#\s"\'<>]*@~i';

    /**
     * One pattern for every declared token path prefix, the longest first, each marked with its position in
     * {@see self::$replacements} (`t0`, `t1`…); `null` when no prefix is declared.
     */
    private ?string $tokenPathPattern;

    /**
     * @var array<string, string> each declared prefix as it is written back, under its mark, a segment that follows
     *                            a shorter declared prefix already redacted
     */
    private array $replacements;

    /**
     * @param list<string> $tokenPathPrefixes path prefixes such as `/p/`, each followed by a token segment
     */
    public function __construct(array $tokenPathPrefixes = [])
    {
        foreach ($tokenPathPrefixes as $prefix) {
            if (1 !== preg_match('~^/(?:[^/\s?#]+/)+$~D', $prefix)) {
                throw InvalidPrivacyValue::because(\sprintf('"%s" is not a token path prefix: expected one or more path segments between slashes, such as "/p/".', $prefix));
            }
        }

        // The longest prefix is tried first, so `/p/x/` wins over `/p/` where both start: its token is redacted,
        // and so is the segment after `/p/`, which is a token under the shorter prefix.
        usort($tokenPathPrefixes, static fn (string $a, string $b): int => \strlen($b) <=> \strlen($a));

        $alternatives = [];
        $replacements = [];

        foreach ($tokenPathPrefixes as $index => $prefix) {
            $alternatives[] = preg_quote($prefix, '~').'(*MARK:t'.$index.')';
            $replacements['t'.$index] = $this->replacement($prefix, $tokenPathPrefixes);
        }

        // The prefix must start a path: at the beginning of the string, after whitespace or a quote, or right after
        // a scheme and host. `/shop/p/x` is not under `/p/`.
        $this->tokenPathPattern = [] === $alternatives ? null : '~(^|[\s"\'(=<]|[a-z][a-z0-9+.-]*://[^/\s"\'<>]+)(?:'.implode('|', $alternatives).')[^/?#\s"\'<>]+~i';
        $this->replacements = $replacements;
    }

    public function redact(string $text): string
    {
        if (null !== $this->tokenPathPattern) {
            $replacements = $this->replacements;
            $text = preg_replace_callback(
                $this->tokenPathPattern,
                static fn (array $match): string => $match[1].$replacements[$match['MARK']].self::PLACEHOLDER,
                $text,
            );

            if (null === $text) {
                return self::PLACEHOLDER;
            }
        }

        $text = preg_replace(self::USER_INFORMATION, '$1'.self::PLACEHOLDER.'@', $text);

        // A pattern that fails (a backtracking limit on a huge line) must not hand the text back unredacted.
        return null === $text ? self::PLACEHOLDER : preg_replace(self::QUERY_STRING, '?'.self::PLACEHOLDER, $text) ?? self::PLACEHOLDER;
    }

    /**
     * The prefix with the segment that follows each shorter declared prefix it starts with redacted: `/p/x/`, with
     * `/p/` declared too, is written back `/p/[redacted]/`.
     *
     * @param list<string> $prefixes
     */
    private function replacement(string $prefix, array $prefixes): string
    {
        $segments = explode('/', $prefix);

        foreach ($prefixes as $shorter) {
            if (\strlen($shorter) < \strlen($prefix) && 0 === strncasecmp($prefix, $shorter, \strlen($shorter))) {
                $segments[substr_count($shorter, '/')] = self::PLACEHOLDER;
            }
        }

        return implode('/', $segments);
    }
}
