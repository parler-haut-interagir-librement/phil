<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Infrastructure\Mailer;

use DateTimeImmutable;
use IntlDateFormatter;
use Phil\SchedulingPollBundle\Application\ChosenDateCalendar;
use Phil\SchedulingPollBundle\Domain\Model\Respondent;
use Phil\SchedulingPollBundle\Domain\Model\SchedulingPoll;
use Phil\SchedulingPollBundle\Domain\Model\Slot;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The mail that tells a respondent the date chosen: in their language, the date written in their time zone — the poll's
 * when they chose none —, and the iCalendar file attached, `event.ics`.
 */
final readonly class ChosenDateMail
{
    public const string DOMAIN = 'phil_scheduling_poll';

    public function __construct(
        private TranslatorInterface $translator,
        private ?Address $from = null,
        private string $defaultLocale = 'en',
    ) {
    }

    public function build(SchedulingPoll $poll, Slot $slot, Respondent $respondent, Address $to, DateTimeImmutable $now): Email
    {
        $locale = $respondent->locale ?? $this->defaultLocale;
        $zone = null === $respondent->timezone() ? $poll->timezone() : SchedulingPoll::zone($respondent->timezone());
        $date = (string) new IntlDateFormatter($locale, IntlDateFormatter::FULL, IntlDateFormatter::SHORT, $zone)->format($slot->startsAt());
        $parameters = ['%title%' => $poll->title(), '%date%' => $date, '%timezone%' => $zone->getName(), '%location%' => (string) $poll->location()];
        $text = $this->translator->trans('mail.body', $parameters, self::DOMAIN, $locale);

        if (null !== $poll->location()) {
            $text .= "\n\n".$this->translator->trans('mail.location', $parameters, self::DOMAIN, $locale);
        }

        $email = new Email()
            ->to($to)
            ->subject($this->translator->trans('mail.subject', $parameters, self::DOMAIN, $locale))
            ->text($text."\n\n".$this->translator->trans('mail.attachment', $parameters, self::DOMAIN, $locale)."\n")
            ->attach(ChosenDateCalendar::file($poll, $slot, $now), 'event.ics', 'text/calendar; charset=utf-8');

        return $this->from instanceof Address ? $email->from($this->from) : $email;
    }
}
