<?php

declare(strict_types=1);

namespace Phil\BounceParser;

use Override;
use Stringable;

/**
 * An enhanced mail system status code (RFC 3463): its class — 2 success, 4 persistent transient failure, 5 permanent
 * failure —, its subject and its detail, `5.1.1` for a mailbox that does not exist.
 */
final readonly class StatusCode implements Stringable
{
    private const string PATTERN = '(?<class>[245])\.(?<subject>\d{1,3})\.(?<detail>\d{1,3})';

    private const array SUBJECTS = [
        0 => 'other or undefined status',
        1 => 'addressing status',
        2 => 'mailbox status',
        3 => 'mail system status',
        4 => 'network and routing status',
        5 => 'mail delivery protocol status',
        6 => 'message content or media status',
        7 => 'security or policy status',
    ];

    private function __construct(
        public int $class,
        public int $subject,
        public int $detail,
    ) {
    }

    /**
     * The code written alone, `5.1.1`, or `null` when the text is not one.
     */
    public static function parse(string $text): ?self
    {
        return 1 === preg_match('/^'.self::PATTERN.'$/', trim($text), $match) ? self::of($match) : null;
    }

    /**
     * The first code in a text, such as a diagnostic or the body of a bounce, or `null`. A code inside a longer run of
     * digits and dots — a version or an address — is not one.
     */
    public static function find(string $text): ?self
    {
        return 1 === preg_match('/(?<![\d.])'.self::PATTERN.'(?![\d.]*\d)/', $text, $match) ? self::of($match) : null;
    }

    public function isSuccess(): bool
    {
        return 2 === $this->class;
    }

    public function isTransient(): bool
    {
        return 4 === $this->class;
    }

    public function isPermanent(): bool
    {
        return 5 === $this->class;
    }

    /**
     * Whether a permanent failure still says nothing against the mailbox itself: a full mailbox (`x.2.2`), a message
     * too long for it (`x.2.3`, `x.3.4`), or a refusal by policy, such as a spam filter or a block list (`x.7.*`).
     */
    public function isAboutTheMessage(): bool
    {
        return 7 === $this->subject
            || (2 === $this->subject && \in_array($this->detail, [2, 3], true))
            || (3 === $this->subject && 4 === $this->detail);
    }

    /**
     * The subject of the code in a few English words.
     */
    public function describe(): string
    {
        return self::SUBJECTS[$this->subject] ?? 'unknown status';
    }

    public function toString(): string
    {
        return $this->class.'.'.$this->subject.'.'.$this->detail;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @param array<array-key, string> $match
     */
    private static function of(array $match): self
    {
        return new self((int) $match['class'], (int) $match['subject'], (int) $match['detail']);
    }
}
