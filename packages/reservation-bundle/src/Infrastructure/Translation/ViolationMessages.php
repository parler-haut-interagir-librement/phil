<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Infrastructure\Translation;

use Phil\Reservation\Decision;
use Phil\Reservation\Violation;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The reasons a reservation is refused, in the reader's language: each `ViolationCode` is a message of the
 * `phil_reservation` domain, `violation.<value>`, given the violation's parameters.
 */
final readonly class ViolationMessages
{
    public const string DOMAIN = 'phil_reservation';

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function message(Violation $violation, ?string $locale = null): string
    {
        return $this->translator->trans('violation.'.$violation->code->value, $violation->parameters, self::DOMAIN, $locale);
    }

    /**
     * @return list<string> one message per reason, in the decision's order
     */
    public function messages(Decision $decision, ?string $locale = null): array
    {
        return array_map(fn (Violation $violation): string => $this->message($violation, $locale), $decision->violations);
    }
}
