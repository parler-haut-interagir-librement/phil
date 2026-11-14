<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Anonymization\Anonymizer;

use Override;
use Phil\PrivacyBundle\Domain\Anonymization\AddressPart;
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizationContext;
use Phil\PrivacyBundle\Domain\Contract\Anonymizer;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * `address` — one part of a postal address, or the whole of it, from the locale pack.
 *
 * Option: `part`, one of `line` (default, the street line), `city`, `region`, `postal_code`, `country` (the country's
 * name) and `full` (the pack's address format). Each part is drawn on its own: the city does not match the postal
 * code, which is one more thing the copy does not tell.
 */
final readonly class AddressAnonymizer implements Anonymizer
{
    #[Override]
    public function anonymize(string|int|float|bool $value, AnonymizationContext $context): string
    {
        $name = $context->options->string('part', AddressPart::Line->value);
        $part = AddressPart::tryFrom($name) ?? throw InvalidPrivacyValue::because(\sprintf('"%s" is not an address part; the parts are line, city, region, postal_code, country and full.', $name));

        return $this->part($part, $context);
    }

    private function part(AddressPart $part, AnonymizationContext $context): string
    {
        $locale = $context->locale;

        return match ($part) {
            AddressPart::Line => strtr($context->pick($locale->streetFormats), ['{number}' => \sprintf('%d', $context->random->getInt(1, 9999)), '{street}' => $context->pick($locale->streets)]),
            AddressPart::City => $context->pick($locale->cities),
            AddressPart::Region => $context->pick($locale->regions),
            AddressPart::PostalCode => $context->fill($context->pick($locale->postalCodes)),
            AddressPart::Country => $locale->countryName,
            AddressPart::Full => strtr($locale->addressFormat, [
                '{line}' => $this->part(AddressPart::Line, $context),
                '{city}' => $this->part(AddressPart::City, $context),
                '{region}' => $this->part(AddressPart::Region, $context),
                '{postal_code}' => $this->part(AddressPart::PostalCode, $context),
                '{country}' => $locale->countryName,
            ]),
        };
    }
}
