<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Infrastructure\CanadaPost;

use Phil\CommerceLogisticsBundle\Domain\Carrier\LabelRequest;
use Phil\CommerceLogisticsBundle\Domain\Carrier\Parcel;
use Phil\CommerceLogisticsBundle\Domain\Carrier\RateQuote;
use Phil\CommerceLogisticsBundle\Domain\Carrier\RateRequest;
use Phil\CommerceLogisticsBundle\Domain\Carrier\ShippingAddress;
use Phil\CommerceLogisticsBundle\Domain\Exception\CarrierRefused;
use Phil\Money\Currency;
use Phil\Money\Exception\InvalidAmount;
use Phil\Money\Money;

/**
 * The JSON bodies of Canada Post's rating and shipping API, written and read in one place: the requests the carrier
 * is sent, and the quotes, tracking numbers, prices and links read back from its answers. Its amounts are Canadian
 * dollars, its weights kilograms and its dimensions centimetres.
 *
 * Every answer is untrusted: a field of the wrong type, or missing, refuses the whole answer.
 */
final class CanadaPostPayloads
{
    public const string CARRIER = 'canada_post';

    /**
     * @return array<string, mixed>
     */
    public static function rateRequest(RateRequest $request, CanadaPostCredentials $credentials): array
    {
        return array_filter([
            'customer-number' => $credentials->customerNumber,
            'contract-id' => $credentials->contractId,
            'origin-postal-code' => $request->from->postalCode,
            'parcel-characteristics' => self::parcel($request->parcel),
            'destination' => self::destination($request->to),
        ], static fn (mixed $value): bool => null !== $value);
    }

    /**
     * @return array<string, mixed>
     */
    public static function labelRequest(LabelRequest $request, CanadaPostCredentials $credentials): array
    {
        return array_filter([
            'customer-number' => $credentials->customerNumber,
            'contract-id' => $credentials->contractId,
            'requested-shipping-point' => $request->from->postalCode,
            'delivery-spec' => array_filter([
                'service-code' => $request->service,
                'sender' => self::party($request->from) + ['contact-phone' => $request->from->phone],
                'destination' => self::party($request->to) + ['client-voice-number' => $request->to->phone],
                'parcel-characteristics' => self::parcel($request->parcel),
                'preferences' => ['show-packing-instructions' => true],
                'references' => '' === $request->reference ? null : ['customer-ref-1' => $request->reference],
            ], static fn (mixed $value): bool => null !== $value),
        ], static fn (mixed $value): bool => null !== $value);
    }

    /**
     * @return list<RateQuote>
     */
    public static function quotes(mixed $answer): array
    {
        $quotes = [];

        foreach (self::list($answer, 'price-quotes') as $quote) {
            $details = self::map($quote, 'price-details');
            $standard = \is_array($quote) && isset($quote['service-standard']) ? self::map($quote, 'service-standard') : [];
            $transit = $standard['expected-transit-time'] ?? null;

            $quotes[] = new RateQuote(
                self::CARRIER,
                self::code($quote, 'service-code'),
                self::text($quote, 'service-name'),
                self::amount($details, 'due'),
                \is_int($transit) && $transit >= 0 && $transit <= 365 ? $transit : null,
            );
        }

        return $quotes;
    }

    public static function tracking(mixed $answer): string
    {
        return self::code($answer, 'tracking-pin');
    }

    /**
     * The price inside the shipment's answer, when it carries one; `null` when it is to be read from its `price` link.
     */
    public static function shipmentPrice(mixed $answer): ?Money
    {
        if (!\is_array($answer) || !isset($answer['shipment-price'])) {
            return null;
        }

        return self::amount(self::map($answer, 'shipment-price'), 'due-amount');
    }

    public static function price(mixed $answer): Money
    {
        return self::amount($answer, 'due-amount');
    }

    /**
     * The address of the answer's link of that relation, `null` without one.
     */
    public static function link(mixed $answer, string $relation): ?string
    {
        foreach (self::list($answer, 'links') as $link) {
            if (\is_array($link) && ($link['rel'] ?? null) === $relation) {
                return self::text($link, 'href');
            }
        }

        return null;
    }

    /**
     * What the carrier said of a refused request: its messages, or nothing it could read.
     */
    public static function messages(mixed $answer): string
    {
        $messages = [];

        foreach (\is_array($answer) && \is_array($answer['messages'] ?? null) ? $answer['messages'] : [] as $message) {
            if (\is_array($message) && \is_string($message['code'] ?? null) && \is_string($message['description'] ?? null)) {
                $messages[] = mb_substr($message['code'].' '.$message['description'], 0, 200);
            }
        }

        return [] === $messages ? 'an error without a message' : implode('; ', \array_slice($messages, 0, 5));
    }

    /**
     * @return array<string, mixed>
     */
    private static function parcel(Parcel $parcel): array
    {
        // Grams and millimetres are whole: their quotient is already the closest float to the decimal value, and the
        // casts keep a whole kilogram or centimetre a float, which the JSON writes as 2.0 rather than 2.
        $characteristics = ['weight' => (float) $parcel->grams / 1000];

        if ($parcel->hasDimensions()) {
            $characteristics['dimensions'] = [
                'length' => (float) $parcel->lengthMillimetres / 10,
                'width' => (float) $parcel->widthMillimetres / 10,
                'height' => (float) $parcel->heightMillimetres / 10,
            ];
        }

        return $characteristics;
    }

    /**
     * @return array<string, mixed>
     */
    private static function destination(ShippingAddress $to): array
    {
        return match ($to->country) {
            'CA' => ['domestic' => ['postal-code' => $to->postalCode]],
            'US' => ['united-states' => ['zip-code' => $to->postalCode]],
            default => ['international' => ['country-code' => $to->country]],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function party(ShippingAddress $address): array
    {
        return [
            'name' => $address->name,
            'address-details' => array_filter([
                'address-line-1' => $address->street[0] ?? '',
                'address-line-2' => $address->street[1] ?? null,
                'city' => $address->city,
                'prov-state' => '' === $address->region ? null : $address->region,
                'country-code' => $address->country,
                'postal-zip-code' => '' === $address->postalCode ? null : $address->postalCode,
            ], static fn (?string $value): bool => null !== $value),
        ];
    }

    /**
     * @return list<mixed>
     */
    private static function list(mixed $answer, string $field): array
    {
        $value = \is_array($answer) ? ($answer[$field] ?? null) : null;

        if (!\is_array($value) || !array_is_list($value)) {
            throw CarrierRefused::unavailable(self::CARRIER, \sprintf('its answer has no list "%s".', $field));
        }

        return $value;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function map(mixed $answer, string $field): array
    {
        $value = \is_array($answer) ? ($answer[$field] ?? null) : null;

        if (!\is_array($value)) {
            throw CarrierRefused::unavailable(self::CARRIER, \sprintf('its answer has no object "%s".', $field));
        }

        return $value;
    }

    private static function text(mixed $answer, string $field): string
    {
        $value = \is_array($answer) ? ($answer[$field] ?? null) : null;

        if (!\is_string($value) || '' === $value || \strlen($value) > 2048) {
            throw CarrierRefused::unavailable(self::CARRIER, \sprintf('its answer has no text "%s".', $field));
        }

        return $value;
    }

    private static function code(mixed $answer, string $field): string
    {
        $value = self::text($answer, $field);

        if (1 !== preg_match('/^[\x21-\x7E]{1,32}$/D', $value)) {
            throw CarrierRefused::unavailable(self::CARRIER, \sprintf('its "%s" is not a code.', $field));
        }

        return $value;
    }

    private static function amount(mixed $answer, string $field): Money
    {
        $value = \is_array($answer) ? ($answer[$field] ?? null) : null;
        $decimal = match (true) {
            \is_int($value) => (string) $value,
            \is_float($value) => number_format($value, 2, '.', ''),
            \is_string($value) => $value,
            default => throw CarrierRefused::unavailable(self::CARRIER, \sprintf('its answer has no amount "%s".', $field)),
        };

        try {
            $amount = Money::ofDecimal($decimal, Currency::of('CAD'));
        } catch (InvalidAmount $invalidAmount) {
            throw CarrierRefused::unavailable(self::CARRIER, \sprintf('its "%s" is not an amount.', $field), $invalidAmount);
        }

        if ($amount->isNegative()) {
            throw CarrierRefused::unavailable(self::CARRIER, \sprintf('its "%s" is negative.', $field));
        }

        return $amount;
    }
}
