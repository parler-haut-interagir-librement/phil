<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Validator;

use Override;
use Phil\AccessibilityCheck\AccessibilityChecker;
use Phil\AccessibilityCheck\Exception\AccessibilityCheckException;
use Phil\Ujo\Document;
use Stringable;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates `AccessibleContent`: checks the value and turns each finding at or above the constraint's severity into a
 * violation, or the refusal of the content into one.
 */
final class AccessibleContentValidator extends ConstraintValidator
{
    /**
     * @param string $locale the locale of the messages when the constraint does not give one
     */
    public function __construct(
        private readonly AccessibilityChecker $checker = new AccessibilityChecker(),
        private readonly string $locale = 'en',
    ) {
    }

    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof AccessibleContent) {
            throw new UnexpectedTypeException($constraint, AccessibleContent::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value) && !$value instanceof Stringable && !$value instanceof Document) {
            throw new UnexpectedValueException($value, 'string|'.Document::class);
        }

        try {
            $report = $value instanceof Document
                ? $this->checker->checkUjo($value, $constraint->language)
                : $this->checker->checkHtml((string) $value, $constraint->language);
        } catch (AccessibilityCheckException $accessibilityCheckException) {
            $this->context->buildViolation($constraint->refusedMessage)
                ->setParameter('{{ reason }}', $accessibilityCheckException->getMessage())
                ->setCode(AccessibleContent::REFUSED_ERROR)
                ->addViolation();

            return;
        }

        $report = $report->inLocale($constraint->locale ?? $this->locale);

        foreach ($report->findings($constraint->minSeverity) as $finding) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ message }}', $report->message($finding))
                ->setParameter('{{ rule }}', $finding->rule)
                ->setParameter('{{ criterion }}', $finding->criterion->value)
                ->setParameter('{{ severity }}', $finding->severity->value)
                ->setParameter('{{ location }}', $finding->location->describe())
                ->setCode(AccessibleContent::INACCESSIBLE_ERROR)
                ->addViolation();
        }
    }
}
