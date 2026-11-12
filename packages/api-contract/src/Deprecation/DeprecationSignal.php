<?php

declare(strict_types=1);

namespace Phil\ApiContract\Deprecation;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\ApiContract\Exception\InvalidContractValue;
use Phil\ApiContract\Http\HttpDate;

/**
 * What a response says about the retirement of the resource that served it.
 *
 * - `Deprecation` (RFC 9745) carries the date the resource was, or will be, deprecated, as a structured-field
 *   date: `@` followed by a Unix timestamp.
 * - `Sunset` (RFC 8594) carries the date the resource stops being served, as an HTTP-date.
 * - `Link: <…>; rel="deprecation"` points at a document that says what to do instead.
 *
 * A breaking change is a new resource next to the old one; the old one carries these headers until its sunset.
 * The minimum notice between the two dates, and who decides them, are the application's: `noticeInDays()`
 * gives the figure, the library does not judge it.
 */
final readonly class DeprecationSignal
{
    public const string DEPRECATION_HEADER = 'Deprecation';

    public const string SUNSET_HEADER = 'Sunset';

    public const string LINK_HEADER = 'Link';

    public const string LINK_RELATION = 'deprecation';

    private const int SECONDS_PER_DAY = 86_400;

    private function __construct(
        public ?DateTimeImmutable $deprecatedAt,
        public ?DateTimeImmutable $sunsetAt,
        public ?string $link,
    ) {
    }

    /**
     * A signal carries at least one of the two dates. RFC 9745 §3: a sunset earlier than the deprecation is
     * meaningless, and is refused here rather than sent.
     */
    public static function of(?DateTimeImmutable $deprecatedAt, ?DateTimeImmutable $sunsetAt = null, ?string $link = null): self
    {
        if (!$deprecatedAt instanceof DateTimeImmutable && !$sunsetAt instanceof DateTimeImmutable) {
            throw InvalidContractValue::because('A deprecation signal carries a deprecation date, a sunset date, or both.');
        }

        if ($deprecatedAt instanceof DateTimeImmutable && $sunsetAt instanceof DateTimeImmutable && $sunsetAt < $deprecatedAt) {
            throw InvalidContractValue::because('The sunset date precedes the deprecation date.');
        }

        if (null !== $link && (str_contains($link, '>') || 1 === preg_match('/[\s\x00-\x1F\x7F]/', $link) || '' === $link)) {
            throw InvalidContractValue::because('The deprecation link is not a URI that fits in a Link header.');
        }

        return new self(self::wholeSeconds($deprecatedAt), self::wholeSeconds($sunsetAt), $link);
    }

    /**
     * Reads the signal from response headers, tolerantly: a malformed value is ignored, as though absent.
     * Returns `null` when the response carries no usable signal.
     *
     * @param list<string> $linkHeaders every `Link` header line of the response
     */
    public static function fromHeaders(?string $deprecation, ?string $sunset, array $linkHeaders = []): ?self
    {
        $deprecatedAt = null === $deprecation ? null : self::parseStructuredDate($deprecation);
        $sunsetAt = null === $sunset ? null : HttpDate::parse($sunset);

        if (!$deprecatedAt instanceof DateTimeImmutable && !$sunsetAt instanceof DateTimeImmutable) {
            return null;
        }

        // A sender that got its dates backwards still announced a retirement: keep the sunset, which is the
        // date that breaks the caller, and drop the deprecation date that contradicts it.
        if ($deprecatedAt instanceof DateTimeImmutable && $sunsetAt instanceof DateTimeImmutable && $sunsetAt < $deprecatedAt) {
            $deprecatedAt = null;
        }

        return new self($deprecatedAt, $sunsetAt, LinkHeader::firstWithRelation($linkHeaders, self::LINK_RELATION));
    }

    /**
     * The header lines that announce this signal, keyed by header name.
     *
     * @return array<string, string>
     */
    public function toHeaders(): array
    {
        $headers = [];

        if ($this->deprecatedAt instanceof DateTimeImmutable) {
            $headers[self::DEPRECATION_HEADER] = '@'.$this->deprecatedAt->getTimestamp();
        }

        if ($this->sunsetAt instanceof DateTimeImmutable) {
            $headers[self::SUNSET_HEADER] = HttpDate::format($this->sunsetAt);
        }

        if (null !== $this->link) {
            $headers[self::LINK_HEADER] = \sprintf('<%s>; rel="%s"', $this->link, self::LINK_RELATION);
        }

        return $headers;
    }

    public function isDeprecatedAt(DateTimeInterface $now): bool
    {
        return $this->deprecatedAt instanceof DateTimeImmutable && $this->deprecatedAt <= $now;
    }

    public function isSunsetAt(DateTimeInterface $now): bool
    {
        return $this->sunsetAt instanceof DateTimeImmutable && $this->sunsetAt <= $now;
    }

    /**
     * Whole days between the deprecation and the sunset, or `null` if either date is missing.
     */
    public function noticeInDays(): ?int
    {
        if (!$this->deprecatedAt instanceof DateTimeImmutable || !$this->sunsetAt instanceof DateTimeImmutable) {
            return null;
        }

        return intdiv($this->sunsetAt->getTimestamp() - $this->deprecatedAt->getTimestamp(), self::SECONDS_PER_DAY);
    }

    /**
     * RFC 9745 §2.1: an sf-date, `@` and an integer of at most fifteen digits. Parameters are allowed on a
     * structured-field item and carry nothing this reader needs; they are dropped.
     */
    private static function parseStructuredDate(string $value): ?DateTimeImmutable
    {
        $item = trim(explode(';', $value)[0]);

        if (1 !== preg_match('/^@(-?\d{1,15})$/', $item, $matches)) {
            return null;
        }

        return new DateTimeImmutable('@'.$matches[1]);
    }

    /**
     * Both headers carry whole seconds; dropping the fraction here keeps a signal equal to its own round trip.
     */
    private static function wholeSeconds(?DateTimeImmutable $moment): ?DateTimeImmutable
    {
        return $moment instanceof DateTimeImmutable ? new DateTimeImmutable('@'.$moment->getTimestamp()) : null;
    }
}
