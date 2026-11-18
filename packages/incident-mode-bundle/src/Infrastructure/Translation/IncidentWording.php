<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Infrastructure\Translation;

use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use Phil\IncidentMode\Duration;
use Phil\IncidentMode\Preset;
use Phil\IncidentMode\Presets;
use Phil\IncidentMode\Restriction;
use Phil\IncidentModeBundle\Application\DurationInput;
use Phil\IncidentModeBundle\Application\Suspension;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The words of the incident mode in the visitor's language: what a suspension stops and until when, the label of a
 * preset and of a restriction, a duration. A moment is written in the locale of the translator — the request's — and
 * in the configured time zone, which it names, so that nobody reads an end in a zone they do not live in without
 * knowing it.
 *
 * The built-in restrictions and presets have their words in the domain `phil_incident_mode`; the application's own
 * keep their name, or the label of their preset.
 */
final readonly class IncidentWording
{
    public const string DOMAIN = 'phil_incident_mode';

    /** The restrictions with words of their own. */
    public const array RESTRICTIONS = [
        Restriction::SIGNUPS, Restriction::INVITATIONS, Restriction::NEW_MESSAGES, Restriction::NEW_POSTS, Restriction::NEW_COMMENTS,
        Restriction::REACTIONS, Restriction::UPLOADS, Restriction::PROFILE_CHANGES, Restriction::READ_ONLY,
    ];

    /** The presets with words of their own. */
    public const array PRESETS = [Presets::SUSPEND_SIGNUPS, Presets::SUSPEND_INVITATIONS, Presets::SUSPEND_NEW_MESSAGES, Presets::SUSPEND_CONTRIBUTIONS, Presets::READ_ONLY];

    public function __construct(
        private TranslatorInterface $translator,
        private ?string $timezone = null,
    ) {
    }

    /**
     * The sentence that says what the suspension stops and until when.
     */
    public function sentence(Suspension $suspension, ?string $locale = null): string
    {
        $known = \in_array($suspension->restriction, self::RESTRICTIONS, true);

        return $this->translator->trans(
            $known ? 'suspended.'.$suspension->restriction : 'suspended.other',
            ['%until%' => $this->moment($suspension->until, $locale), '%name%' => $suspension->restriction],
            self::DOMAIN,
            $locale,
        );
    }

    /**
     * The moment, with its date, its time and the name of its time zone.
     */
    public function moment(DateTimeImmutable $moment, ?string $locale = null): string
    {
        $locale ??= $this->translator->getLocale();
        $zone = new DateTimeZone($this->timeZone());
        $local = $moment->setTimezone($zone);
        $date = new IntlDateFormatter($locale, IntlDateFormatter::LONG, IntlDateFormatter::SHORT, $zone);
        $name = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $zone, null, 'zzzz');

        return \sprintf('%s (%s)', (string) $date->format($local), (string) $name->format($local));
    }

    public function timeZone(): string
    {
        return $this->timezone ?? date_default_timezone_get();
    }

    public function restriction(string $name, ?string $locale = null): string
    {
        return \in_array($name, self::RESTRICTIONS, true) ? $this->translator->trans('restriction.'.$name, [], self::DOMAIN, $locale) : $name;
    }

    public function preset(Preset|string $preset, ?string $locale = null): string
    {
        $name = $preset instanceof Preset ? $preset->name : $preset;

        if (\in_array($name, self::PRESETS, true)) {
            return $this->translator->trans('preset.'.$name, [], self::DOMAIN, $locale);
        }

        return $preset instanceof Preset ? $preset->label : $name;
    }

    /**
     * The duration in the largest unit that counts it whole: "2 days", "36 hours", "90 minutes".
     */
    public function duration(Duration $duration, ?string $locale = null): string
    {
        $split = DurationInput::split($duration);

        return $this->translator->trans('duration.'.$split['unit'], ['%count%' => $split['amount']], self::DOMAIN, $locale);
    }
}
