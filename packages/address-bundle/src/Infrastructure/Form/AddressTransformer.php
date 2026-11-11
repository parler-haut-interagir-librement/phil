<?php

declare(strict_types=1);

namespace Phil\AddressBundle\Infrastructure\Form;

use Override;
use Phil\Address\Address;
use Phil\Address\Exception\AddressException;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Between an `Address` and the fields of its form; no country and no field is no address.
 *
 * @implements DataTransformerInterface<Address, array<string, ?string>>
 */
final class AddressTransformer implements DataTransformerInterface
{
    #[Override]
    public function transform(mixed $value): array
    {
        return null === $value ? [] : ['country' => $value->country, ...$value->fields()];
    }

    #[Override]
    public function reverseTransform(mixed $value): ?Address
    {
        $value ??= [];
        $country = $value['country'] ?? null;
        unset($value['country']);
        $fields = array_filter($value, static fn (?string $field): bool => null !== $field && '' !== trim($field));

        if (null === $country || '' === $country) {
            return [] === $fields ? null : throw new TransformationFailedException('An address is given with its country.');
        }

        try {
            return Address::of($country, $fields);
        } catch (AddressException $addressException) {
            throw new TransformationFailedException($addressException->getMessage(), $addressException->getCode(), previous: $addressException);
        }
    }
}
