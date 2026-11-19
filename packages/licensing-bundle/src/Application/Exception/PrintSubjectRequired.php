<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Application\Exception;

use Override;
use Phil\LicensingBundle\Domain\Exception\LicensingException;
use RuntimeException;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A print refused by the strict printing: it names no work, so its licensing cannot be checked. A document without a
 * license is printed under a `PrintSubject` whose type the configuration declares unlicensed. The message is translated
 * as `print.subject_required` in the domain `phil_licensing`.
 */
final class PrintSubjectRequired extends RuntimeException implements LicensingException, TranslatableInterface
{
    public const string MESSAGE_KEY = 'print.subject_required';

    public function __construct()
    {
        parent::__construct('A print must name its work with a PrintSubject, so that its licensing is checked; a document without a license names a subject type declared in phil_licensing.printing.unlicensed_subjects.');
    }

    #[Override]
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans(self::MESSAGE_KEY, [], 'phil_licensing', $locale);
    }
}
