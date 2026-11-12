<?php

declare(strict_types=1);

namespace Phil\ContactBundle\Infrastructure\Form;

use Override;
use Phil\Contact\PhoneLine;
use Phil\Contact\PhoneRole;
use Phil\Phone\PhoneNumber;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * Between a `PhoneLine` and its two fields; a line without number is none.
 *
 * @implements DataTransformerInterface<PhoneLine, array{role: ?PhoneRole, number: ?PhoneNumber}>
 */
final class PhoneLineTransformer implements DataTransformerInterface
{
    #[Override]
    public function transform(mixed $value): array
    {
        return $value instanceof PhoneLine ? ['role' => $value->role, 'number' => $value->number] : ['role' => PhoneRole::Voice, 'number' => null];
    }

    #[Override]
    public function reverseTransform(mixed $value): ?PhoneLine
    {
        $number = $value['number'] ?? null;

        return $number instanceof PhoneNumber ? new PhoneLine($value['role'] ?? PhoneRole::Voice, $number) : null;
    }
}
