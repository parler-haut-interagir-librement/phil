<?php

declare(strict_types=1);

namespace Phil\UjoBundle\Infrastructure\Validator;

use Override;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\UjoException;
use Phil\Ujo\Ujo;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class ValidUjoValidator extends ConstraintValidator
{
    public function __construct(
        private readonly Ujo $ujo,
    ) {
    }

    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidUjo) {
            throw new UnexpectedTypeException($constraint, ValidUjo::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        try {
            $document = match (true) {
                $value instanceof Document => $this->ujo->normalize($value, $constraint->level),
                \is_string($value) => $this->ujo->decode($value, $constraint->level),
                default => throw new UnexpectedValueException($value, 'string|Phil\\Ujo\\Document'),
            };
        } catch (UjoException $ujoException) {
            $this->context->buildViolation($constraint->message, ['{{ error }}' => $ujoException->getMessage()])
                ->setCode(ValidUjo::INVALID_ERROR)
                ->addViolation();

            return;
        }

        $refused = [];

        foreach ($document->walk() as $block) {
            if (null !== $constraint->types && !\in_array($block->type, $constraint->types, true)) {
                $refused[] = $block->type;
            }
        }

        foreach (array_unique($refused) as $type) {
            $this->context->buildViolation($constraint->typeMessage, ['{{ type }}' => $this->formatValue($type)])
                ->setCode(ValidUjo::TYPE_ERROR)
                ->addViolation();
        }
    }
}
