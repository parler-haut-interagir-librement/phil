<?php

declare(strict_types=1);

namespace Phil\SvgBlocksBundle\Infrastructure\Form;

use Override;
use Phil\SvgBlocks\BlockMarkup;
use Phil\Ujo\Document;
use Phil\Ujo\Ujo;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * The last step of a submitted document, once `phil/ujo` has checked it: its blocks drawn and their drawings signed,
 * any drawing the client sent dropped. The model keeps its form, canonical JSON or `Document`; a value left
 * unchanged is returned as it came.
 *
 * @implements DataTransformerInterface<Document|string|null, Document|string|null>
 */
final readonly class StoredMarkupTransformer implements DataTransformerInterface
{
    public function __construct(
        private BlockMarkup $markup,
        private Ujo $ujo,
    ) {
    }

    #[Override]
    public function transform(mixed $value): mixed
    {
        return $value;
    }

    #[Override]
    public function reverseTransform(mixed $value): mixed
    {
        if ($value instanceof Document) {
            return $this->markup->render($value)->document;
        }

        if (!\is_string($value) || '' === $value) {
            return $value;
        }

        $rendered = $this->markup->render($this->ujo->decode($value));

        return $rendered->changed ? $this->ujo->encode($rendered->document) : $value;
    }
}
