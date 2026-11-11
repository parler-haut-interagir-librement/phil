<?php

declare(strict_types=1);

namespace Phil\UrlBundle\Infrastructure\Validator;

use Override;
use Phil\Enums\Collection\EnumSet;
use Phil\Url\Exception\UrlException;
use Phil\Url\TypedUrl;
use Phil\Url\Url;
use Phil\Url\UrlType;
use Phil\Url\UrlTypes;
use Phil\UrlBundle\Application\AcceptedUrlTypes;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class UrlOfTypeValidator extends ConstraintValidator
{
    /**
     * @param bool $allowLocalhost whether `localhost` and the names under `.localhost` are read as addresses, in
     *                             development and tests: `phil_url.allow_localhost`
     */
    public function __construct(
        private readonly bool $allowLocalhost = false,
    ) {
    }

    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UrlOfType) {
            throw new UnexpectedTypeException($constraint, UrlOfType::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $sets = AcceptedUrlTypes::sets($constraint->types);

        if ($value instanceof TypedUrl) {
            if (!array_any($sets, static fn (EnumSet $set): bool => $value->type::class === $set->enum() && $set->contains($value->type))) {
                $this->notAccepted($constraint, $sets);
            }

            return;
        }

        try {
            $url = match (true) {
                $value instanceof Url => $value,
                \is_string($value) => Url::fromString($value, allowLocalhost: $this->allowLocalhost),
                default => throw new UnexpectedValueException($value, 'string|Phil\\Url\\Url|Phil\\Url\\TypedUrl'),
            };
        } catch (UrlException) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(UrlOfType::INVALID_ERROR)
                ->addViolation();

            return;
        }

        if (!UrlTypes::detect($url, ...$sets) instanceof UrlType) {
            $this->notAccepted($constraint, $sets);
        }
    }

    /**
     * @param list<EnumSet<covariant UrlType>> $sets
     */
    private function notAccepted(UrlOfType $constraint, array $sets): void
    {
        $this->context->buildViolation($constraint->typeMessage)
            ->setParameter('{{ types }}', implode(', ', array_merge(...array_map(static fn (EnumSet $set): array => $set->names(), $sets))))
            ->setCode(UrlOfType::NOT_ACCEPTED_ERROR)
            ->addViolation();
    }
}
