<?php

declare(strict_types=1);

namespace Phil\PhoneBundle\Infrastructure\Validator;

use Override;
use Phil\Phone\Exception\PhoneException;
use Phil\Phone\PhoneNumber;
use Phil\Phone\PhoneNumberType;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class ValidPhoneNumberValidator extends ConstraintValidator
{
    /** @var ?list<string> the regions of the constraint validated last, as written */
    private ?array $regions = null;

    /** @var array<string, true> those regions in capitals */
    private array $accepted = [];

    public function __construct(
        private readonly ?string $defaultRegion = null,
    ) {
    }

    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidPhoneNumber) {
            throw new UnexpectedTypeException($constraint, ValidPhoneNumber::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        try {
            $number = match (true) {
                $value instanceof PhoneNumber => $value,
                \is_string($value) => PhoneNumber::parse($value, $constraint->region ?? $this->defaultRegion),
                default => throw new UnexpectedValueException($value, 'string|Phil\\Phone\\PhoneNumber'),
            };
        } catch (PhoneException) {
            $number = null;
        }

        // The type is what isValid() reads: asked once, it settles validity and the accepted types together.
        $type = $number instanceof PhoneNumber ? $number->type() : PhoneNumberType::Unknown;

        if (!$number instanceof PhoneNumber || PhoneNumberType::Unknown === $type) {
            $this->violation($constraint->message, ValidPhoneNumber::INVALID_ERROR, ['{{ value }}' => $this->formatValue($value)]);

            return;
        }

        if (null !== $constraint->types && !\in_array($type, $constraint->types, true)) {
            $this->violation($constraint->typeMessage, ValidPhoneNumber::TYPE_ERROR);
        }

        if (null !== $constraint->regions && !isset($this->accepted($constraint->regions)[$number->region() ?? ''])) {
            $this->violation($constraint->regionMessage, ValidPhoneNumber::REGION_ERROR, ['{{ regions }}' => implode(', ', $constraint->regions)]);
        }
    }

    /**
     * The accepted regions in capitals, put so once for the regions of a constraint however many values it validates.
     *
     * @param list<string> $regions
     *
     * @return array<string, true>
     */
    private function accepted(array $regions): array
    {
        if ($regions !== $this->regions) {
            $this->regions = $regions;
            $this->accepted = array_fill_keys(array_map(strtoupper(...), $regions), true);
        }

        return $this->accepted;
    }

    /**
     * @param array<string, string> $parameters
     */
    private function violation(string $message, string $code, array $parameters = []): void
    {
        $this->context->buildViolation($message, $parameters)->setCode($code)->addViolation();
    }
}
