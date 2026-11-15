<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Model;

use DateTimeImmutable;
use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;
use Phil\NewsletterBundle\Domain\ValueObject\Site;

/**
 * A template an administrator or a member wrote: a subject and a Twig source, for one site or, without a site, shared
 * by all of them. It is always rendered in the sandbox, never by the application's own Twig.
 */
final class Template
{
    public const int MAX_SOURCE_BYTES = 65_536;

    public function __construct(
        public readonly string $key,
        public readonly ?Site $site,
        public private(set) string $subject,
        public private(set) string $source,
        public private(set) DateTimeImmutable $updatedAt,
    ) {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $key)) {
            throw InvalidNewsletterValue::because(\sprintf('"%s" is not a template key: a lowercase letter or digit, then up to 63 lowercase letters, digits, "_", "." or "-".', $key));
        }

        $this->check($subject, $source);
    }

    public function change(string $subject, string $source, DateTimeImmutable $now): void
    {
        $this->check($subject, $source);
        $this->subject = $subject;
        $this->source = $source;
        $this->updatedAt = $now;
    }

    public function isShared(): bool
    {
        return !$this->site instanceof Site;
    }

    private function check(string $subject, string $source): void
    {
        if (mb_strlen($subject) > 200 || \strlen($source) > self::MAX_SOURCE_BYTES || 1 !== preg_match('//u', $source)) {
            throw InvalidNewsletterValue::because(\sprintf('A template has a subject of at most 200 characters and a source of at most %d bytes of UTF-8.', self::MAX_SOURCE_BYTES));
        }
    }
}
