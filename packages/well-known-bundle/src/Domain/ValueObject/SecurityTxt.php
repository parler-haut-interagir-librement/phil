<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Phil\WellKnownBundle\Domain\Exception\InvalidValue;

/**
 * A security.txt file (RFC 9116): how to report a vulnerability, until when the file holds.
 *
 * `Contact` (one or more) and `Expires` (once) are required. Each value is checked against what its field takes:
 * contacts are `mailto:`, `tel:` or `https://` URIs; `Encryption` an `https://`, `dns:` or `openpgp4fpr:` URI;
 * `Acknowledgments`, `Canonical`, `Policy` and `Hiring` `https://` URIs; `Preferred-Languages` language tags (RFC 5646),
 * written on one line. No value holds a space or a control character, so that none can add a line of its own.
 *
 * The file is written with its fields in the order of the RFC, one per line, `Expires` in UTC.
 */
final readonly class SecurityTxt
{
    public const int MAX_VALUE_LENGTH = 2048;

    private const string LANGUAGE_TAG = '/^[A-Za-z]{1,8}(?:-[A-Za-z0-9]{1,8})*$/D';

    private const string DATE = '/^(\d{4}-\d{2}-\d{2})(?:[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d{1,6})?)?(?:Z|[+-]\d{2}:\d{2})?)?$/D';

    /** @var list<string> */
    public array $contact;

    /** @var list<string> */
    public array $encryption;

    /** @var list<string> */
    public array $acknowledgments;

    /** @var list<string> */
    public array $preferredLanguages;

    /** @var list<string> */
    public array $canonical;

    /** @var list<string> */
    public array $policy;

    /** @var list<string> */
    public array $hiring;

    /**
     * @param list<string> $contact
     * @param list<string> $encryption
     * @param list<string> $acknowledgments
     * @param list<string> $preferredLanguages
     * @param list<string> $canonical
     * @param list<string> $policy
     * @param list<string> $hiring
     *
     * @throws InvalidValue when a field is missing or holds a value it does not take
     */
    public function __construct(
        array $contact,
        public DateTimeImmutable $expires,
        array $encryption = [],
        array $acknowledgments = [],
        array $preferredLanguages = [],
        array $canonical = [],
        array $policy = [],
        array $hiring = [],
    ) {
        if ([] === $contact) {
            throw InvalidValue::because('A security.txt file needs at least one Contact.');
        }

        $this->contact = $this->uris('Contact', $contact, ['mailto:', 'tel:', 'https://']);
        $this->encryption = $this->uris('Encryption', $encryption, ['https://', 'dns:', 'openpgp4fpr:']);
        $this->acknowledgments = $this->uris('Acknowledgments', $acknowledgments, ['https://']);
        $this->canonical = $this->uris('Canonical', $canonical, ['https://']);
        $this->policy = $this->uris('Policy', $policy, ['https://']);
        $this->hiring = $this->uris('Hiring', $hiring, ['https://']);

        foreach ($preferredLanguages as $language) {
            if (1 !== preg_match(self::LANGUAGE_TAG, $language)) {
                throw InvalidValue::because(\sprintf('Preferred-Languages takes language tags such as "en" or "fr-CA"; "%s" is not one.', $language));
            }
        }

        $this->preferredLanguages = $preferredLanguages;
    }

    /**
     * Reads the `Expires` of a configuration: a date, with or without a time and an offset, read in UTC when it has none.
     * A relative date such as "+1 year" is refused, since it would move each time it is read.
     *
     * @throws InvalidValue when the text is not such a date, or names a day the calendar does not have
     */
    public static function expiresAt(string $date): DateTimeImmutable
    {
        if (1 !== preg_match(self::DATE, $date, $matches)) {
            throw InvalidValue::because(\sprintf('Expires takes a date such as "2027-06-30T00:00:00Z"; "%s" is not one.', $date));
        }

        try {
            $expires = new DateTimeImmutable($date, new DateTimeZone('UTC'));
        } catch (Exception) {
            throw InvalidValue::because(\sprintf('Expires names a day the calendar does not have: "%s".', $date));
        }

        // The date keeps the offset it is written with: a day the calendar lacks, such as February 30, rolls over.
        if ($expires->format('Y-m-d') !== $matches[1]) {
            throw InvalidValue::because(\sprintf('Expires names a day the calendar does not have: "%s".', $date));
        }

        return $expires;
    }

    public function toString(): string
    {
        $lines = [];

        foreach ($this->contact as $contact) {
            $lines[] = 'Contact: '.$contact;
        }

        $lines[] = 'Expires: '.$this->expires->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');

        foreach (['Encryption' => $this->encryption, 'Acknowledgments' => $this->acknowledgments] as $field => $values) {
            foreach ($values as $value) {
                $lines[] = $field.': '.$value;
            }
        }

        if ([] !== $this->preferredLanguages) {
            $lines[] = 'Preferred-Languages: '.implode(', ', $this->preferredLanguages);
        }

        foreach (['Canonical' => $this->canonical, 'Policy' => $this->policy, 'Hiring' => $this->hiring] as $field => $values) {
            foreach ($values as $value) {
                $lines[] = $field.': '.$value;
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param list<string>           $values
     * @param non-empty-list<string> $schemes
     *
     * @return list<string>
     */
    private function uris(string $field, array $values, array $schemes): array
    {
        foreach ($values as $value) {
            $scheme = array_find($schemes, static fn (string $scheme): bool => str_starts_with(strtolower($value), $scheme));

            if (null === $scheme || \strlen($value) <= \strlen($scheme) || \strlen($value) > self::MAX_VALUE_LENGTH || 1 === preg_match('/[\x00-\x20\x7F]/', $value)) {
                throw InvalidValue::because(\sprintf('%s takes %s URIs without spaces, %d characters at most; "%s" is not one.', $field, implode(', ', $schemes), self::MAX_VALUE_LENGTH, $value));
            }
        }

        return $values;
    }
}
