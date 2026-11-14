<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Domain\ValueObject;

use Phil\MentionsBundle\Domain\Exception\InvalidMention;

/**
 * What a handle looks like after its `@`. The default takes 1 to 64 letters of any script, digits, `_`, `.` and `-`,
 * starting and ending with a letter, a digit or `_`, so that the full stop or the hyphen after a handle ends the
 * sentence, not the handle. An application gives its own expression — `[a-z0-9_]{3,20}` — without delimiters nor anchors;
 * it is checked once, may not match an empty string, and is read in UTF-8.
 *
 * The `@` counts only where a word does not go on before it, so `ada@example.com` holds no mention, nor `\@ada` nor
 * `@@ada`.
 */
final readonly class MentionPattern
{
    public const string DEFAULT = '[\p{L}\p{N}_](?:[\p{L}\p{N}_.\-]{0,62}[\p{L}\p{N}_])?';

    public const int MAX_LENGTH = 256;

    public string $handle;

    public string $regex;

    private function __construct(string $handle)
    {
        if ('' === $handle || \strlen($handle) > self::MAX_LENGTH) {
            throw InvalidMention::because(\sprintf('The pattern of a handle is 1 to %d bytes.', self::MAX_LENGTH));
        }

        if (str_contains($handle, '~') || 1 === preg_match('/\(\?P?<handle>/', $handle)) {
            throw InvalidMention::because('The pattern of a handle holds no "~" nor a group named "handle".');
        }

        $regex = '~(?<![\p{L}\p{N}_@\\\\/])@(?<handle>'.$handle.')~u';

        // On its own first: an expression that compiles alone keeps its parentheses balanced, so it cannot close the
        // group around it and escape the condition before the `@`.
        if (false === @preg_match('~'.$handle.'~u', '') || false === @preg_match($regex, '')) {
            throw InvalidMention::because(\sprintf('The pattern of a handle "%s" is not a valid regular expression.', $handle));
        }

        if (1 === @preg_match('~^(?:'.$handle.')$~u', '')) {
            throw InvalidMention::because(\sprintf('The pattern of a handle "%s" matches an empty handle.', $handle));
        }

        $this->handle = $handle;
        $this->regex = $regex;
    }

    public static function default(): self
    {
        return new self(self::DEFAULT);
    }

    /**
     * @throws InvalidMention when the expression is empty, too long, invalid, or matches an empty handle
     */
    public static function of(string $handle): self
    {
        return new self($handle);
    }
}
