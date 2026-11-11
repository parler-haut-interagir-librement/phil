<?php

declare(strict_types=1);

namespace Phil\Phone;

use Override;
use Phil\Phone\Exception\InvalidPhoneNumber;
use Phil\Phone\Metadata\Territories;
use Phil\Phone\Metadata\Territory;
use Stringable;
use WeakMap;

/**
 * A phone number: its country code, its national number and its extension.
 *
 * `parse()` reads what people type — `+1 514-555-0123 ext. 12`, `06 12 34 56 78` in France, `011 33 6…` dialled
 * from North America —; whether the number exists in its country's plan is `isValid()`, which a form checks.
 */
final class PhoneNumber implements Stringable
{
    /**
     * The longest input read, in characters: what a person types fits well within it, and the patterns below never
     * run on more.
     */
    public const int MAX_INPUT = 100;

    private const string EXTENSION = '/^(?<number>.+?)\s*+(?:;\s*ext=|(?:extension|extn|ext|anexo|int|poste|x)\.?|#)\s*(?<extension>\d{1,7})$/iu';

    private const string WRITTEN = '/^\+?[\d\s().\/\-\x{00A0}\x{2010}-\x{2015}]+$/u';

    /**
     * The placement of each number asked for, kept while the number lives and out of the value itself, so that
     * `region()` and `type()` walk the plans once.
     *
     * @var ?WeakMap<self, array{?string, PhoneNumberType}>
     */
    private static ?WeakMap $placements = null;

    /**
     * @param string  $nationalNumber the digits after the country code, leading zeros kept
     * @param ?string $extension      digits
     */
    private function __construct(
        public readonly int $countryCode,
        public readonly string $nationalNumber,
        public readonly ?string $extension,
    ) {
    }

    /**
     * An input longer than `MAX_INPUT` characters is refused before it is read.
     *
     * @param ?string $region the ISO 3166-1 alpha-2 code of the country a number without its country code is written in
     */
    public static function parse(string $input, ?string $region = null): self
    {
        if (mb_strlen($input) > self::MAX_INPUT) {
            throw InvalidPhoneNumber::notANumber($input);
        }

        $text = trim(preg_replace('/^tel:/i', '', trim($input)) ?? '');
        $extension = null;

        if (1 === preg_match(self::EXTENSION, $text, $parts)) {
            $text = $parts['number'];
            $extension = $parts['extension'];
        }

        if (mb_strlen($text) > 50 || 1 !== preg_match(self::WRITTEN, $text)) {
            throw InvalidPhoneNumber::notANumber($input);
        }

        $digits = preg_replace('/\D/', '', $text) ?? '';
        $territory = null === $region ? null : Territories::region($region);
        $international = str_starts_with($text, '+') ? $digits : $territory?->dialledInternationally($digits);

        if (null !== $international) {
            [$countryCode, $national, $territory] = self::split($international, $input);
        } elseif ($territory instanceof Territory) {
            [$countryCode, $national] = [$territory->countryCode, $digits];
        } else {
            throw InvalidPhoneNumber::noRegion($input);
        }

        $national = $territory->withoutNationalPrefix($national);

        if (\strlen($national) < 2 || \strlen($national) > 17) {
            throw InvalidPhoneNumber::notANumber($input);
        }

        return new self($countryCode, $national, $extension);
    }

    /**
     * The country the number belongs to, `001` for the numbers of no country, or `null` when it is in no plan.
     */
    public function region(): ?string
    {
        return $this->placement()[0];
    }

    public function type(): PhoneNumberType
    {
        return $this->placement()[1];
    }

    /**
     * Whether the number exists in its country's numbering plan.
     */
    public function isValid(): bool
    {
        return PhoneNumberType::Unknown !== $this->type();
    }

    public function format(PhoneFormat $format): string
    {
        $territory = Territories::main($this->countryCode);
        $extension = null === $this->extension ? '' : ($territory->preferredExtnPrefix ?? ' ext. ').$this->extension;

        return match ($format) {
            PhoneFormat::E164 => '+'.$this->countryCode.$this->nationalNumber,
            PhoneFormat::National => $this->grouped($territory, national: true).$extension,
            PhoneFormat::International => '+'.$this->countryCode.' '.$this->grouped($territory, national: false).$extension,
            PhoneFormat::Rfc3966 => 'tel:+'.$this->countryCode.'-'.(preg_replace('/\D+/', '-', $this->grouped($territory, national: false)) ?? '').(null === $this->extension ? '' : ';ext='.$this->extension),
        };
    }

    public function equals(self $other): bool
    {
        return $this->countryCode === $other->countryCode && $this->nationalNumber === $other->nationalNumber && $this->extension === $other->extension;
    }

    /**
     * The number in E.164, with its extension as RFC 3966 writes it: what `parse()` reads back.
     */
    #[Override]
    public function __toString(): string
    {
        return '+'.$this->countryCode.$this->nationalNumber.(null === $this->extension ? '' : ';ext='.$this->extension);
    }

    /**
     * The region the number belongs to and its type there, found in one pass over the plans of its country code: the
     * first that claims the number by its leading digits, or the first that knows its type.
     *
     * @return array{?string, PhoneNumberType}
     */
    private function placement(): array
    {
        self::$placements ??= new WeakMap();

        return self::$placements[$this] ??= $this->place();
    }

    /**
     * @return array{?string, PhoneNumberType}
     */
    private function place(): array
    {
        foreach (Territories::regionsOf($this->countryCode) as $region) {
            $territory = Territories::of($region, $this->countryCode);
            $type = $territory->typeOf($this->nationalNumber);

            if (PhoneNumberType::Unknown !== $type || $territory->claims($this->nationalNumber)) {
                return [$region, $type];
            }
        }

        return [null, PhoneNumberType::Unknown];
    }

    private function grouped(?Territory $territory, bool $national): string
    {
        foreach ($territory->formats ?? [] as $format) {
            $pattern = $national ? $format->format : $format->intlFormat;

            if (null === $pattern || !$format->applies($this->nationalNumber)) {
                continue;
            }

            if ($national && null !== $format->nationalRule && 1 === preg_match('/\$\d/', $pattern, $first, \PREG_OFFSET_CAPTURE)) {
                // The rule's `$1` is the format's first group, whichever group that is.
                $pattern = substr_replace($pattern, str_replace('$1', $first[0][0], $format->nationalRule), $first[0][1], 2);
            }

            return $format->apply($this->nationalNumber, $pattern);
        }

        return $this->nationalNumber;
    }

    /**
     * @return array{int, string, Territory}
     */
    private static function split(string $digits, string $input): array
    {
        for ($length = 1; $length <= 3; ++$length) {
            $code = (int) substr($digits, 0, $length);
            $main = Territories::main($code);

            if (!str_starts_with($digits, '0') && $main instanceof Territory) {
                return [$code, substr($digits, $length), $main];
            }
        }

        throw InvalidPhoneNumber::unknownCountryCode($input);
    }
}
