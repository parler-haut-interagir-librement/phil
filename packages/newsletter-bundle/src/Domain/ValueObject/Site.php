<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\ValueObject;

use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;
use Stringable;

/**
 * The site a list, a subscriber, a campaign or a template belongs to. A standalone site has one, `default`; several
 * sites served by one application keep their audiences apart and may share templates.
 */
final readonly class Site implements Stringable
{
    public const string DEFAULT = 'default';

    private function __construct(
        public string $key,
    ) {
    }

    /**
     * @throws InvalidNewsletterValue when the key is not a lowercase name of at most 64 characters
     */
    public static function of(string $key): self
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $key)) {
            throw InvalidNewsletterValue::because(\sprintf('"%s" is not a site key: a lowercase letter or digit, then up to 63 lowercase letters, digits, "_", "." or "-".', $key));
        }

        return new self($key);
    }

    public static function default(): self
    {
        return new self(self::DEFAULT);
    }

    public function equals(self $other): bool
    {
        return $this->key === $other->key;
    }

    public function __toString(): string
    {
        return $this->key;
    }
}
