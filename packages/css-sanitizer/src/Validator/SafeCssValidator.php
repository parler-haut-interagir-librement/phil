<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Validator;

use Override;
use Phil\CssSanitizer\CssSanitizer;
use Stringable;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates `SafeCss`: sanitizes the value and turns each removal it lists into a violation, and those it only counts
 * into one more.
 */
final class SafeCssValidator extends ConstraintValidator
{
    public function __construct(
        private readonly CssSanitizer $sanitizer = new CssSanitizer(),
    ) {
    }

    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof SafeCss) {
            throw new UnexpectedTypeException($constraint, SafeCss::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value) && !$value instanceof Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $css = (string) $value;
        $sanitized = $constraint->declarations ? $this->sanitizer->sanitizeDeclarations($css) : $this->sanitizer->sanitize($css);

        foreach ($sanitized->removals as $removal) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ removed }}', $removal->reason->describe())
                ->setParameter('{{ subject }}', $removal->subject)
                ->setParameter('{{ line }}', (string) $removal->line)
                ->setParameter('{{ reason }}', $removal->reason->value)
                ->setCode(SafeCss::NOT_ALLOWED_ERROR)
                ->addViolation();
        }

        if ($sanitized->omittedRemovals > 0) {
            $this->context->buildViolation($constraint->moreMessage)
                ->setParameter('{{ count }}', (string) $sanitized->omittedRemovals)
                ->setPlural($sanitized->omittedRemovals)
                ->setCode(SafeCss::NOT_ALLOWED_ERROR)
                ->addViolation();
        }
    }
}
