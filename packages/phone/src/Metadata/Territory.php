<?php

declare(strict_types=1);

namespace Phil\Phone\Metadata;

use Phil\Phone\PhoneNumberType;

/**
 * A territory's numbering plan: a country, or `001` for the numbers of a country code that belong to none.
 */
final readonly class Territory
{
    private const array TYPES = [
        'premiumRate' => PhoneNumberType::PremiumRate,
        'tollFree' => PhoneNumberType::TollFree,
        'sharedCost' => PhoneNumberType::SharedCost,
        'voip' => PhoneNumberType::Voip,
        'personalNumber' => PhoneNumberType::PersonalNumber,
        'pager' => PhoneNumberType::Pager,
        'uan' => PhoneNumberType::Uan,
        'voicemail' => PhoneNumberType::Voicemail,
    ];

    /**
     * @param array<string, NumberDesc> $types
     * @param list<NumberFormat>        $formats
     */
    public function __construct(
        public string $id,
        public int $countryCode,
        public ?string $internationalPrefix,
        public ?string $nationalPrefix,
        public ?string $nationalPrefixForParsing,
        public ?string $nationalPrefixTransformRule,
        public ?string $leadingDigits,
        public ?string $preferredExtnPrefix,
        public NumberDesc $general,
        public array $types,
        public array $formats,
    ) {
    }

    /**
     * @param array{id: string, countryCode: int, internationalPrefix: ?string, nationalPrefix: ?string, nationalPrefixForParsing: ?string, nationalPrefixTransformRule: ?string, leadingDigits: ?string, preferredExtnPrefix: ?string, general: array{pattern: string, lengths: list<int>}, types: array<string, array{pattern: string, lengths: list<int>}>, formats: list<array{pattern: string, leadingDigits: ?string, format: string, nationalRule: ?string, intlFormat: ?string}>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            $data['countryCode'],
            $data['internationalPrefix'],
            $data['nationalPrefix'],
            $data['nationalPrefixForParsing'],
            $data['nationalPrefixTransformRule'],
            $data['leadingDigits'],
            $data['preferredExtnPrefix'],
            new NumberDesc($data['general']['pattern'], $data['general']['lengths']),
            array_map(static fn (array $type): NumberDesc => new NumberDesc($type['pattern'], $type['lengths']), $data['types']),
            array_map(static fn (array $format): NumberFormat => new NumberFormat($format['pattern'], $format['leadingDigits'], $format['format'], $format['nationalRule'], $format['intlFormat']), $data['formats']),
        );
    }

    /**
     * The type of a national number of this territory, `Unknown` when the territory has no such number.
     */
    public function typeOf(string $nationalNumber): PhoneNumberType
    {
        if (!$this->general->matches($nationalNumber)) {
            return PhoneNumberType::Unknown;
        }

        foreach (self::TYPES as $key => $type) {
            if ($this->matches($key, $nationalNumber)) {
                return $type;
            }
        }

        $fixed = $this->types['fixedLine'] ?? null;

        if (null !== $fixed && $fixed->matches($nationalNumber)) {
            return $this->matches('mobile', $nationalNumber) || $fixed->pattern === ($this->types['mobile'] ?? null)?->pattern ? PhoneNumberType::FixedLineOrMobile : PhoneNumberType::FixedLine;
        }

        return $this->matches('mobile', $nationalNumber) ? PhoneNumberType::Mobile : PhoneNumberType::Unknown;
    }

    /**
     * Whether a national number written in this territory starts with the territory's own leading digits.
     */
    public function claims(string $nationalNumber): bool
    {
        return null !== $this->leadingDigits && 1 === preg_match('/^(?:'.$this->leadingDigits.')/', $nationalNumber);
    }

    /**
     * The national number without the national prefix it was dialled with: `0612345678` in France is `612345678`.
     */
    public function withoutNationalPrefix(string $digits): string
    {
        $match = [];
        $prefix = null === $this->nationalPrefixForParsing || 1 !== preg_match('/^(?:'.$this->nationalPrefixForParsing.')/', $digits, $match, \PREG_UNMATCHED_AS_NULL) ? '' : $match[0] ?? '';

        $rest = substr($digits, \strlen($prefix));
        $last = \count($match) > 1 ? $match[\count($match) - 1] : null;
        $candidate = null === $this->nationalPrefixTransformRule || null === $last
            ? $rest
            : preg_replace_callback('/\$(\d)/', static fn (array $group): string => $match[$group[1]] ?? '', $this->nationalPrefixTransformRule).$rest;

        return $this->viable($digits) && !$this->viable($candidate) ? $digits : $candidate;
    }

    public function dialledInternationally(string $digits): ?string
    {
        if (null === $this->internationalPrefix || 1 !== preg_match('/^(?:'.$this->internationalPrefix.')/', $digits, $match)) {
            return null;
        }

        $rest = substr($digits, \strlen($match[0]));

        return '' === $rest || str_starts_with($rest, '0') ? null : $rest;
    }

    private function matches(string $type, string $nationalNumber): bool
    {
        return ($this->types[$type] ?? null)?->matches($nationalNumber) ?? false;
    }

    private function viable(string $nationalNumber): bool
    {
        return 1 === preg_match('/^(?:'.$this->general->pattern.')$/', $nationalNumber);
    }
}
