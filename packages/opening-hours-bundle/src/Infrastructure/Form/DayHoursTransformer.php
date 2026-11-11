<?php

declare(strict_types=1);

namespace Phil\OpeningHoursBundle\Infrastructure\Form;

use Override;
use Phil\OpeningHours\DayHours;
use Phil\OpeningHours\Exception\OpeningHoursException;
use Phil\OpeningHours\HoursText;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Between the hours of a day and the text of its field, `09:00-12:00, 13:00-17:00`; an empty field is a closed day.
 *
 * @implements DataTransformerInterface<DayHours, string>
 */
final class DayHoursTransformer implements DataTransformerInterface
{
    #[Override]
    public function transform(mixed $value): string
    {
        return $value?->toText() ?? '';
    }

    #[Override]
    public function reverseTransform(mixed $value): DayHours
    {
        try {
            return HoursText::day($value ?? '');
        } catch (OpeningHoursException $openingHoursException) {
            throw new TransformationFailedException($openingHoursException->getMessage(), $openingHoursException->getCode(), previous: $openingHoursException);
        }
    }
}
