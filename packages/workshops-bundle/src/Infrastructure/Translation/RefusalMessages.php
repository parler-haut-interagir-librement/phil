<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Infrastructure\Translation;

use Phil\WorkshopsBundle\Application\Exception\RegistrationRefused;
use Phil\WorkshopsBundle\Domain\Status\RegistrationStatus;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The words of the workshops in the reader's language: each `RefusalReason` is a message of the `phil_workshops`
 * domain, `refusal.<value>`, given the refusal's parameters; each `RegistrationStatus` is `status.<value>`.
 */
final readonly class RefusalMessages
{
    public const string DOMAIN = 'phil_workshops';

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function message(RegistrationRefused $refusal, ?string $locale = null): string
    {
        return $this->translator->trans('refusal.'.$refusal->reason->value, $refusal->parameters, self::DOMAIN, $locale);
    }

    public function status(RegistrationStatus $status, ?string $locale = null): string
    {
        return $this->translator->trans('status.'.$status->value, [], self::DOMAIN, $locale);
    }
}
