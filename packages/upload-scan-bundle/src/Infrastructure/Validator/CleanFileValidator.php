<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Infrastructure\Validator;

use Override;
use Phil\UploadScanBundle\Application\UploadScanner;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Checks a `CleanFile` through the `UploadScanner`: the file is scanned once, quarantined when infected.
 */
final class CleanFileValidator extends ConstraintValidator
{
    public function __construct(
        private readonly UploadScanner $scanner,
    ) {
    }

    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CleanFile) {
            throw new UnexpectedTypeException($constraint, CleanFile::class);
        }

        if (null === $value || '' === $value || ($value instanceof UploadedFile && !$value->isValid())) {
            return;
        }

        [$path, $name] = match (true) {
            $value instanceof UploadedFile => [$value->getPathname(), $value->getClientOriginalName()],
            $value instanceof File => [$value->getPathname(), $value->getFilename()],
            \is_string($value) => [$value, basename($value)],
            default => throw new UnexpectedValueException($value, File::class.'|string'),
        };

        $inspection = $this->scanner->inspectPath($path, $name, $constraint->context);

        if ($inspection->infected()) {
            $this->context->buildViolation($constraint->infectedMessage)->setCode(CleanFile::INFECTED_ERROR)->addViolation();
        } elseif (!$inspection->accepted()) {
            $this->context->buildViolation($constraint->notScannedMessage)->setCode(CleanFile::NOT_SCANNED_ERROR)->addViolation();
        }
    }
}
