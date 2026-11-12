<?php

declare(strict_types=1);

namespace Phil\EmailAddress;

/**
 * Why a text is not an email address that SMTP can deliver to.
 */
enum SyntaxError: string
{
    case Empty = 'empty';
    case TooLong = 'too_long';
    case MissingAt = 'missing_at';
    case EmptyLocalPart = 'empty_local_part';
    case LocalPartTooLong = 'local_part_too_long';
    case MisplacedDot = 'misplaced_dot';
    case InvalidLocalPart = 'invalid_local_part';
    case InternationalLocalPart = 'international_local_part';
    case QuotedLocalPart = 'quoted_local_part';
    case EmptyDomain = 'empty_domain';
    case IpLiteral = 'ip_literal';
    case InvalidIpLiteral = 'invalid_ip_literal';
    case InvalidDomain = 'invalid_domain';
    case DomainTooLong = 'domain_too_long';
    case LabelTooLong = 'label_too_long';
    case SingleLabelDomain = 'single_label_domain';
    case InvalidTopLevelDomain = 'invalid_top_level_domain';

    /**
     * The reason in a few English words, for a log or a developer; a form translates the case instead.
     */
    public function describe(): string
    {
        return match ($this) {
            self::Empty => 'the address is empty',
            self::TooLong => 'the address is longer than 254 octets',
            self::MissingAt => 'the address has no @',
            self::EmptyLocalPart => 'nothing comes before the @',
            self::LocalPartTooLong => 'the part before the @ is longer than 64 octets',
            self::MisplacedDot => 'a dot starts or ends the part before the @, or two dots follow each other',
            self::InvalidLocalPart => 'the part before the @ holds a character an address cannot hold',
            self::InternationalLocalPart => 'the part before the @ holds a non-ASCII character',
            self::QuotedLocalPart => 'the part before the @ is quoted',
            self::EmptyDomain => 'nothing comes after the @',
            self::IpLiteral => 'the domain is an IP address',
            self::InvalidIpLiteral => 'the domain is a malformed IP address',
            self::InvalidDomain => 'the domain is not a valid domain name',
            self::DomainTooLong => 'the domain is longer than 253 octets',
            self::LabelTooLong => 'a label of the domain is longer than 63 octets',
            self::SingleLabelDomain => 'the domain has no dot',
            self::InvalidTopLevelDomain => 'the top-level domain is numeric or a single letter',
        };
    }
}
