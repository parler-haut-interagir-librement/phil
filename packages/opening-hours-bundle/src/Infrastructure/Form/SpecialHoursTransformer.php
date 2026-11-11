<?php

declare(strict_types=1);

namespace Phil\OpeningHoursBundle\Infrastructure\Form;

use Override;
use Phil\OpeningHours\Exception\OpeningHoursException;
use Phil\OpeningHours\HoursText;
use Phil\OpeningHours\SpecialHours;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Between special hours and the text of their field, one period a line.
 *
 * @implements DataTransformerInterface<list<SpecialHours>, string>
 */
final class SpecialHoursTransformer implements DataTransformerInterface
{
    #[Override]
    public function transform(mixed $value): string
    {
        return HoursText::specialsToText($value ?? []);
    }

    /**
     * @return list<SpecialHours>
     */
    #[Override]
    public function reverseTransform(mixed $value): array
    {
        try {
            return HoursText::specials($value ?? '');
        } catch (OpeningHoursException $openingHoursException) {
            throw new TransformationFailedException($openingHoursException->getMessage(), $openingHoursException->getCode(), previous: $openingHoursException);
        }
    }
}
