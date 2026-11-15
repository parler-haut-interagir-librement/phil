<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Model;

use DateTimeImmutable;
use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;
use Phil\NewsletterBundle\Domain\ValueObject\Site;

/**
 * A list people subscribe to, on one site: its key in addresses and forms, its name, whether a subscription waits for
 * the confirmation of a double opt-in, and the sender profile its campaigns go out with.
 */
final class MailingList
{
    public private(set) string $name;

    public function __construct(
        public readonly string $id,
        public readonly Site $site,
        public readonly string $key,
        string $name,
        public private(set) bool $doubleOptIn,
        public private(set) string $senderProfile,
        public readonly DateTimeImmutable $createdAt,
    ) {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $key)) {
            throw InvalidNewsletterValue::because(\sprintf('"%s" is not a list key: a lowercase letter or digit, then up to 63 lowercase letters, digits, "_" or "-".', $key));
        }

        $this->name = $this->name($name);
    }

    public function change(string $name, bool $doubleOptIn, string $senderProfile): void
    {
        $this->name = $this->name($name);
        $this->doubleOptIn = $doubleOptIn;
        $this->senderProfile = $senderProfile;
    }

    private function name(string $name): string
    {
        $name = trim($name);

        if ('' === $name || mb_strlen($name) > 120) {
            throw InvalidNewsletterValue::because('The name of a list is not blank and holds at most 120 characters.');
        }

        return $name;
    }
}
