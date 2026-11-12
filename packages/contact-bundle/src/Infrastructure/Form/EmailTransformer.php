<?php

declare(strict_types=1);

namespace Phil\ContactBundle\Infrastructure\Form;

use Override;
use Phil\Contact\Email;
use Phil\Contact\Exception\ContactException;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Between an `Email` and the text of its field.
 *
 * @implements DataTransformerInterface<Email, string>
 */
final class EmailTransformer implements DataTransformerInterface
{
    #[Override]
    public function transform(mixed $value): string
    {
        return $value instanceof Email ? $value->value : '';
    }

    #[Override]
    public function reverseTransform(mixed $value): ?Email
    {
        if (null === $value || '' === $value) {
            return null;
        }

        try {
            return Email::of($value);
        } catch (ContactException $contactException) {
            throw new TransformationFailedException($contactException->getMessage(), $contactException->getCode(), previous: $contactException);
        }
    }
}
