<?php

declare(strict_types=1);

namespace Phil\ContactBundle\Infrastructure\Form;

use Override;
use Phil\Contact\Exception\ContactException;
use Phil\Contact\NameOrder;
use Phil\Contact\PersonName;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Between a `PersonName` and the fields of its form; additional names are one field, separated by spaces. The culture
 * given to the transformer wins over the culture field. A name without given nor family name is none.
 *
 * @phpstan-type Fields array{honorific: ?string, given: ?string, additional: ?string, particle: ?string, family: ?string, secondFamily: ?string, generation: ?string, credential: ?string, order: ?NameOrder, culture?: ?string}
 *
 * @implements DataTransformerInterface<PersonName, Fields>
 */
final readonly class PersonNameTransformer implements DataTransformerInterface
{
    public function __construct(
        private ?string $culture = null,
    ) {
    }

    #[Override]
    public function transform(mixed $value): array
    {
        return [
            'honorific' => $value?->honorific,
            'given' => $value?->given,
            'additional' => null === $value || [] === $value->additional ? null : implode(' ', $value->additional),
            'particle' => $value?->particle,
            'family' => $value?->family,
            'secondFamily' => $value?->secondFamily,
            'generation' => $value?->generation,
            'credential' => $value?->credential,
            'order' => $value?->order,
            'culture' => $value?->culture,
        ];
    }

    /**
     * @param ?Fields $value
     */
    #[Override]
    public function reverseTransform(mixed $value): ?PersonName
    {
        if (null === ($value['given'] ?? null) && null === ($value['family'] ?? null)) {
            return null;
        }

        try {
            return PersonName::of(
                $value['given'] ?? null,
                $value['family'] ?? null,
                $value['particle'] ?? null,
                // Empty names, between two spaces, are dropped by the name.
                explode(' ', strtr($value['additional'] ?? '', "\t\n\r", '   ')),
                $value['secondFamily'] ?? null,
                $value['honorific'] ?? null,
                $value['generation'] ?? null,
                $value['credential'] ?? null,
                $this->culture ?? $value['culture'] ?? null,
                $value['order'] ?? null,
            );
        } catch (ContactException $contactException) {
            throw new TransformationFailedException($contactException->getMessage(), $contactException->getCode(), previous: $contactException);
        }
    }
}
