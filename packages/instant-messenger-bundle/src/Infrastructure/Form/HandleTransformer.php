<?php

declare(strict_types=1);

namespace Phil\InstantMessengerBundle\Infrastructure\Form;

use Override;
use Phil\InstantMessenger\Exception\InstantMessengerException;
use Phil\InstantMessenger\Handle;
use Phil\InstantMessenger\MessengerType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Between a `Handle` and the two fields of a `HandleType`.
 *
 * The text field shows the handle as people read it when its service reads that back to the same handle, and the
 * canonical handle otherwise: an edit form submitted unchanged gives the handle it was given, whatever an
 * application's service writes in `display()`.
 *
 * @implements DataTransformerInterface<Handle, array{type?: ?MessengerType, handle?: ?string}>
 */
final readonly class HandleTransformer implements DataTransformerInterface
{
    #[Override]
    public function transform(mixed $value): array
    {
        if (!$value instanceof Handle) {
            return ['type' => null, 'handle' => null];
        }

        $shown = $value->display();

        return ['type' => $value->type, 'handle' => $value->type->normalize($shown) === $value->value ? $shown : $value->value];
    }

    #[Override]
    public function reverseTransform(mixed $value): ?Handle
    {
        $type = $value['type'] ?? null;
        $handle = $value['handle'] ?? null;

        if (null === $handle || '' === $handle) {
            return null;
        }

        if (!$type instanceof MessengerType) {
            throw new TransformationFailedException('A handle is given with its messaging service.');
        }

        try {
            return Handle::of($type, $handle);
        } catch (InstantMessengerException $instantMessengerException) {
            throw new TransformationFailedException($instantMessengerException->getMessage(), 0, $instantMessengerException);
        }
    }
}
