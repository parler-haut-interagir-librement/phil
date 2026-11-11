<?php

declare(strict_types=1);

namespace Phil\PhoneBundle\Infrastructure\Form;

use Override;
use Phil\Phone\Exception\PhoneException;
use Phil\Phone\PhoneFormat;
use Phil\Phone\PhoneNumber;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Between a `PhoneNumber` and the text of its field: a number of the field's country is shown nationally, any other
 * internationally; a number that is in no numbering plan is refused.
 *
 * @implements DataTransformerInterface<PhoneNumber, string>
 */
final readonly class PhoneNumberTransformer implements DataTransformerInterface
{
    public function __construct(
        private ?string $region,
    ) {
    }

    #[Override]
    public function transform(mixed $value): string
    {
        if (!$value instanceof PhoneNumber) {
            return '';
        }

        return $value->format(null !== $this->region && $value->region() === strtoupper($this->region) ? PhoneFormat::National : PhoneFormat::International);
    }

    #[Override]
    public function reverseTransform(mixed $value): ?PhoneNumber
    {
        if (null === $value || '' === $value) {
            return null;
        }

        try {
            $number = PhoneNumber::parse($value, $this->region);
        } catch (PhoneException $phoneException) {
            throw new TransformationFailedException($phoneException->getMessage(), $phoneException->getCode(), previous: $phoneException);
        }

        if (!$number->isValid()) {
            throw new TransformationFailedException(\sprintf('"%s" is in no numbering plan.', $number));
        }

        return $number;
    }
}
