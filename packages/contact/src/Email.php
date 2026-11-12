<?php

declare(strict_types=1);

namespace Phil\Contact;

use Override;
use Phil\Contact\Exception\InvalidContact;
use Stringable;

/**
 * An email address, its domain in lowercase. A domain in another script is checked in its ASCII form and kept as
 * written: `ada@exämple.com`.
 */
final readonly class Email implements Stringable
{
    private function __construct(
        public string $value,
    ) {
    }

    public static function of(string $input): self
    {
        $text = trim($input);
        $at = strrpos($text, '@');
        $domain = false === $at ? '' : mb_strtolower(substr($text, $at + 1));
        $local = false === $at ? '' : substr($text, 0, $at);

        // At most 254 bytes as written, the at sign included, whatever the length of the domain's ASCII form.
        if ('' === $domain || \strlen($local) + \strlen($domain) >= 254) {
            throw InvalidContact::email($input);
        }

        $ascii = idn_to_ascii($domain);

        if (false === $ascii || false === filter_var($local.'@'.$ascii, \FILTER_VALIDATE_EMAIL)) {
            throw InvalidContact::email($input);
        }

        return new self($local.'@'.$domain);
    }

    /**
     * The `mailto:` address.
     */
    public function link(): string
    {
        return 'mailto:'.$this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
