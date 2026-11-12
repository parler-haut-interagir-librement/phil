<?php

declare(strict_types=1);

namespace Phil\EmailAddress;

use Normalizer;
use Override;
use Phil\EmailAddress\Exception\InvalidEmailAddress;
use Phil\EmailAddress\Internal\DomainName;
use Stringable;

/**
 * An email address a mail server can deliver to: the mailbox of RFC 5321, with the international forms of RFC 6531
 * the policy allows. The part before the @ is kept as written — it is case-sensitive —, the domain in lowercase, in
 * its Unicode form for people and its ASCII form for SMTP.
 */
final readonly class EmailAddress implements Stringable
{
    private const int MAX_LENGTH = 254;

    private const int MAX_LOCAL_LENGTH = 64;

    private const string ATEXT = 'A-Za-z0-9!#$%&\'*+\/=?^_`{|}~.\-';

    private function __construct(
        public string $localPart,
        public string $domain,
        public string $asciiDomain,
    ) {
    }

    /**
     * Reads an address as people type it: spaces and line breaks around it are dropped, nothing else is repaired.
     *
     * @throws InvalidEmailAddress
     */
    public static function parse(string $input, SyntaxPolicy $policy = new SyntaxPolicy()): self
    {
        $text = trim($input, " \t\r\n");

        if ('' === $text) {
            throw new InvalidEmailAddress(SyntaxError::Empty);
        }

        // Bounded before any work: nothing longer can be delivered.
        if (\strlen($text) > self::MAX_LENGTH) {
            throw new InvalidEmailAddress(SyntaxError::TooLong);
        }

        $at = strrpos($text, '@');

        if (false === $at) {
            throw new InvalidEmailAddress(SyntaxError::MissingAt);
        }

        $local = self::localPart(substr($text, 0, $at), $policy);
        [$domain, $ascii] = self::domain(substr($text, $at + 1), $policy);

        if (\strlen($local) + 1 + \strlen($ascii) > self::MAX_LENGTH) {
            throw new InvalidEmailAddress(SyntaxError::TooLong);
        }

        return new self($local, $domain, $ascii);
    }

    /**
     * The address for people, its domain in its own script.
     */
    public function toString(): string
    {
        return $this->localPart.'@'.$this->domain;
    }

    /**
     * The address for SMTP, its domain in ASCII. The part before the @ stays as written: when it is not ASCII, the
     * address needs SMTPUTF8 (`requiresSmtpUtf8()`).
     */
    public function toAscii(): string
    {
        return $this->localPart.'@'.$this->asciiDomain;
    }

    public function requiresSmtpUtf8(): bool
    {
        return 1 === preg_match('/[^\x00-\x7F]/', $this->localPart);
    }

    public function isIpLiteral(): bool
    {
        return str_starts_with($this->asciiDomain, '[');
    }

    /**
     * The same mailbox: the same part before the @, the same domain.
     */
    public function equals(self $other): bool
    {
        return $this->localPart === $other->localPart && $this->asciiDomain === $other->asciiDomain;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->toString();
    }

    private static function localPart(string $local, SyntaxPolicy $policy): string
    {
        if ('' === $local) {
            throw new InvalidEmailAddress(SyntaxError::EmptyLocalPart);
        }

        if ($policy->internationalLocalPart) {
            // Broken UTF-8 cannot be normalised; the character check refuses it below.
            $normalized = Normalizer::normalize($local, Normalizer::FORM_C);
            $local = \is_string($normalized) ? $normalized : $local;
        }

        if (\strlen($local) > self::MAX_LOCAL_LENGTH) {
            throw new InvalidEmailAddress(SyntaxError::LocalPartTooLong);
        }

        $international = $policy->internationalLocalPart ? '\x{A0}-\x{10FFFF}' : '';

        if (str_starts_with($local, '"')) {
            if (!$policy->quotedLocalPart) {
                throw new InvalidEmailAddress(SyntaxError::QuotedLocalPart);
            }

            if (1 !== preg_match('/^"(?:[\x20\x21\x23-\x5B\x5D-\x7E'.$international.']|\\\[\x20-\x7E])*"$/u', $local)) {
                throw new InvalidEmailAddress(SyntaxError::InvalidLocalPart);
            }

            return $local;
        }

        if (str_starts_with($local, '.') || str_ends_with($local, '.') || str_contains($local, '..')) {
            throw new InvalidEmailAddress(SyntaxError::MisplacedDot);
        }

        if (1 === preg_match(\sprintf('/^[%s%s]+$/u', self::ATEXT, $international), $local)) {
            return $local;
        }

        throw new InvalidEmailAddress(!$policy->internationalLocalPart && 1 === preg_match('/^['.self::ATEXT.'\x{A0}-\x{10FFFF}]+$/u', $local) ? SyntaxError::InternationalLocalPart : SyntaxError::InvalidLocalPart);
    }

    /**
     * @return array{string, string} the domain for people and its ASCII form
     */
    private static function domain(string $domain, SyntaxPolicy $policy): array
    {
        if (!str_starts_with($domain, '[')) {
            $name = DomainName::of($domain);

            return [$name->unicode, $name->ascii];
        }

        if (!$policy->ipLiteral) {
            throw new InvalidEmailAddress(SyntaxError::IpLiteral);
        }

        $literal = self::ipLiteral($domain);

        return [$literal, $literal];
    }

    private static function ipLiteral(string $domain): string
    {
        $inner = str_ends_with($domain, ']') ? substr($domain, 1, -1) : '';

        if (0 === strncasecmp($inner, 'IPv6:', 5)) {
            $address = substr($inner, 5);

            if (false !== filter_var($address, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
                return '[IPv6:'.strtolower($address).']';
            }
        } elseif (false !== filter_var($inner, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
            return '['.$inner.']';
        }

        throw new InvalidEmailAddress(SyntaxError::InvalidIpLiteral);
    }
}
