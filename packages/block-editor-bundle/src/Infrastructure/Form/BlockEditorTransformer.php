<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Infrastructure\Form;

use Override;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\UjoException;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Ujo;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Between the UJO JSON the editor writes and the model. The JSON is read and checked by the application's `Ujo`, then
 * narrowed to the editor's toolset: a block type it leaves out fails the transformation, an inline element it leaves
 * out is unwrapped. A document without blocks is no value, so that `NotBlank` applies.
 *
 * @implements DataTransformerInterface<Document|string, string>
 */
final readonly class BlockEditorTransformer implements DataTransformerInterface
{
    public function __construct(
        private Ujo $ujo,
        private Normalizer $toolset,
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
            $document = $this->toolset->normalize($this->ujo->decode($value));
        } catch (UjoException $ujoException) {
            throw new TransformationFailedException($ujoException->getMessage(), previous: $ujoException, invalidMessage: 'This value is not a valid UJO document: {{ error }}', invalidMessageParameters: ['{{ error }}' => $ujoException->getMessage()]);
        }

        if ($document->isEmpty()) {
            return null;
        }

        return $this->document ? $document : $this->ujo->encode($document);
    }
}
