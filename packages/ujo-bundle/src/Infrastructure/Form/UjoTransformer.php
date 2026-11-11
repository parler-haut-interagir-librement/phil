<?php

declare(strict_types=1);

namespace Phil\UjoBundle\Infrastructure\Form;

use Override;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\UjoException;
use Phil\Ujo\Ujo;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Between the UJO JSON a block editor posts and the model: a `Document`, or its canonical JSON. JSON that is not a
 * valid document fails the transformation with the library's message, so the field shows where the document is wrong.
 *
 * @implements DataTransformerInterface<Document|string, string>
 */
final readonly class UjoTransformer implements DataTransformerInterface
{
    public function __construct(
        private Ujo $ujo,
        private bool $document,
    ) {
    }

    #[Override]
    public function transform(mixed $value): string
    {
        return $value instanceof Document ? $this->ujo->encode($value) : $value ?? '';
    }

    #[Override]
    public function reverseTransform(mixed $value): Document|string|null
    {
        if (null === $value || '' === $value) {
            return null;
        }

        try {
            $document = $this->ujo->decode($value);
        } catch (UjoException $ujoException) {
            throw new TransformationFailedException($ujoException->getMessage(), previous: $ujoException, invalidMessage: 'This value is not a valid UJO document: {{ error }}', invalidMessageParameters: ['{{ error }}' => $ujoException->getMessage()]);
        }

        return $this->document ? $document : $this->ujo->encode($document);
    }
}
