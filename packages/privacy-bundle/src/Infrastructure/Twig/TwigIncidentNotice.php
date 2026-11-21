<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Twig;

use DateTimeImmutable;
use DateTimeZone;
use Phil\PrivacyBundle\Application\Incident\IncidentNoticeDraft;
use Phil\PrivacyBundle\Domain\Incident\NoticeRubric;
use Twig\Environment;

/**
 * Writes a draft of a notice as Markdown from `@PhilPrivacy/incident_notice/<recipient>.md.twig`, or
 * `follow_up.md.twig` for a follow-up to the Commission; an application overrides the templates, and the texts are in
 * the translation domain `phil_privacy`. Dates are written in the time zone given, without the time when it is
 * midnight.
 */
final readonly class TwigIncidentNotice
{
    /**
     * The languages the texts of the drafts are written in.
     */
    public const array LOCALES = ['fr', 'en'];

    public function __construct(
        private Environment $twig,
    ) {
    }

    public function render(IncidentNoticeDraft $draft, string $locale, DateTimeZone $timezone): string
    {
        $incident = $draft->incident;
        $rubrics = [];

        foreach (NoticeRubric::cases() as $rubric) {
            $rubrics[$rubric->value] = [
                'section' => $draft->recipient->section(),
                'paragraph' => $rubric->paragraph($draft->recipient),
                'shown' => $draft->shows($rubric),
                'missing' => $draft->lacks($rubric),
            ];
        }

        $markdown = $this->twig->render(\sprintf('@PhilPrivacy/incident_notice/%s.md.twig', $draft->isFollowUp() ? 'follow_up' : $draft->recipient->value), [
            'incident' => $incident,
            'organisation' => $draft->organisation,
            'locale' => $locale,
            'rubric' => $rubrics,
            'dates' => array_map(static fn (?DateTimeImmutable $moment): ?string => self::date($moment, $timezone), [
                'discovered' => $incident->discoveredAt,
                'discovered_until' => $incident->discoveredUntil,
                'occurred' => $incident->occurredAt,
                'occurred_until' => $incident->occurredUntil,
                'subjects_notified' => $incident->subjectsNotifiedAt,
                'public_notice' => $incident->publicNoticeAt,
                'since' => $draft->since,
            ]),
        ]);

        // The templates keep their blocks apart with blank lines; a block left out leaves more than one.
        return trim((string) preg_replace("/\n[ \t]*(?:\n[ \t]*)+\n/", "\n\n", str_replace("\r\n", "\n", $markdown)))."\n";
    }

    private static function date(?DateTimeImmutable $moment, DateTimeZone $timezone): ?string
    {
        if (!$moment instanceof DateTimeImmutable) {
            return null;
        }

        $local = $moment->setTimezone($timezone);

        return $local->format('00:00' === $local->format('H:i') ? 'Y-m-d' : 'Y-m-d H:i');
    }
}
