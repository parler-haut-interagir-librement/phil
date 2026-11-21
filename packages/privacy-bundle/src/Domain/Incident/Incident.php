<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Incident;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;

/**
 * One line of the register of confidentiality incidents, with the eight paragraphs of section 7 of the Québec
 * *Regulation respecting confidentiality incidents* (chapter A-2.1, r. 3.1):
 *
 * 1. the personal information concerned (`categories`, `information`), or why it cannot be described
 *    (`undescribedReason`);
 * 2. a brief description of the circumstances (`summary`);
 * 3. the date or period of the incident (`occurredAt`, `occurredUntil`), or an approximation (`occurrenceApproximate`);
 * 4. the date or period the organisation became aware of it (`discoveredAt`, `discoveredUntil`);
 * 5. the number of people concerned (`affected`), or an approximation (`affectedApproximate`);
 * 6. what leads to conclude that there is, or is not, a risk of serious injury (`seriousRisk`, `riskAssessment`);
 * 7. for a risk of serious injury, the dates the Commission and the people concerned were notified
 *    (`authorityNotifiedAt`, `subjectsNotifiedAt`), and a public notice with its reason (`publicNoticeAt`,
 *    `publicNoticeReason`);
 * 8. a brief description of the measures taken to reduce the risk (`measures`).
 *
 * The law asks a business to keep a line for **every** incident, serious or not, and to keep the register up to date:
 * a line is recorded with what is known and completed or corrected later, and `missingParagraphs()` names what it still
 * lacks. A line is never removed by hand; a retention rule on its store removes it once its period has run, and never
 * before `MINIMUM_RETENTION` from the end of the discovery (section 8).
 */
final readonly class Incident
{
    public const string ID_PATTERN = '/^[0-9a-f]{32}$/D';

    public const int MAX_TEXT = 4000;

    /**
     * Section 8 of the regulation: the register is kept at least five years after the date or period of the discovery.
     */
    public const string MINIMUM_RETENTION = 'P5Y';

    /**
     * The fields `update()` can empty by name.
     */
    public const array CLEARABLE = ['occurred', 'occurred_until', 'discovered_until', 'affected', 'authority_notified', 'subjects_notified', 'public_notice', 'closed'];

    /**
     * @var list<DataCategory>
     */
    public array $categories;

    /**
     * @param list<DataCategory>     $categories        the kinds of personal information concerned
     * @param int|null               $affected          how many people are concerned, or null while it is not known
     * @param string                 $information       a description of the personal information concerned
     * @param string                 $undescribedReason why the personal information cannot be described, instead of a description
     * @param DateTimeImmutable|null $occurredUntil     the end of the period of the incident, after `occurredAt`
     * @param DateTimeImmutable|null $discoveredUntil   the end of the period of the discovery, after `discoveredAt`
     * @param string                 $riskAssessment    what leads to conclude that there is, or is not, a risk of serious injury
     */
    public function __construct(
        public string $id,
        public string $summary,
        public DateTimeImmutable $discoveredAt,
        public ?DateTimeImmutable $occurredAt,
        array $categories,
        public ?int $affected,
        public bool $seriousRisk,
        public string $measures = '',
        public ?DateTimeImmutable $authorityNotifiedAt = null,
        public ?DateTimeImmutable $subjectsNotifiedAt = null,
        public ?DateTimeImmutable $closedAt = null,
        public string $information = '',
        public string $undescribedReason = '',
        public ?DateTimeImmutable $occurredUntil = null,
        public bool $occurrenceApproximate = false,
        public ?DateTimeImmutable $discoveredUntil = null,
        public bool $affectedApproximate = false,
        public string $riskAssessment = '',
        public ?DateTimeImmutable $publicNoticeAt = null,
        public ?PublicNoticeReason $publicNoticeReason = null,
    ) {
        if (1 !== preg_match(self::ID_PATTERN, $id)) {
            throw InvalidPrivacyValue::because('An incident identifier is 32 lowercase hexadecimal characters.');
        }

        if ('' === trim($summary)) {
            throw InvalidPrivacyValue::because(\sprintf('An incident has a summary, and a summary and its measures are at most %d characters each.', self::MAX_TEXT));
        }

        foreach (['summary' => $summary, 'measures' => $measures, 'information' => $information, 'undescribed reason' => $undescribedReason, 'risk assessment' => $riskAssessment] as $what => $text) {
            if (mb_strlen($text) > self::MAX_TEXT) {
                throw InvalidPrivacyValue::because(\sprintf('An incident has a summary, and a summary and its measures are at most %d characters each, as is its %s.', self::MAX_TEXT, $what));
            }
        }

        if ('' !== trim($information) && '' !== trim($undescribedReason)) {
            throw InvalidPrivacyValue::because('An incident describes the personal information concerned, or says why it cannot be described, not both.');
        }

        if (null !== $affected && $affected < 0) {
            throw InvalidPrivacyValue::because('The number of people an incident concerns cannot be negative.');
        }

        if ($affectedApproximate && null === $affected) {
            throw InvalidPrivacyValue::because('An approximate number of people needs the number.');
        }

        $this->period($discoveredAt, $discoveredUntil, 'discovery');

        if ($occurredAt instanceof DateTimeImmutable) {
            $this->period($occurredAt, $occurredUntil, 'incident');

            if ($occurredAt > ($discoveredUntil ?? $discoveredAt)) {
                throw InvalidPrivacyValue::because('An incident cannot have occurred after it was discovered.');
            }
        } elseif ($occurredUntil instanceof DateTimeImmutable || $occurrenceApproximate) {
            throw InvalidPrivacyValue::because('The end of the incident, or its approximation, needs the date it occurred.');
        }

        if ($publicNoticeAt instanceof DateTimeImmutable !== $publicNoticeReason instanceof PublicNoticeReason) {
            throw InvalidPrivacyValue::because('A public notice is recorded with its date and its reason.');
        }

        foreach (['authority notified' => $authorityNotifiedAt, 'people notified' => $subjectsNotifiedAt, 'public notice' => $publicNoticeAt, 'closed' => $closedAt] as $what => $moment) {
            if ($moment instanceof DateTimeImmutable && $moment < $discoveredAt) {
                throw InvalidPrivacyValue::because(\sprintf('An incident cannot be marked "%s" before it was discovered.', $what));
            }
        }

        $seen = [];

        foreach ($categories as $category) {
            if (isset($seen[$category->value])) {
                throw InvalidPrivacyValue::because(\sprintf('The incident lists the category "%s" twice.', $category->value));
            }

            $seen[$category->value] = $category;
        }

        $this->categories = $categories;
    }

    /**
     * @param list<DataCategory> $categories
     */
    public static function record(string $summary, DateTimeImmutable $discoveredAt, ?DateTimeImmutable $occurredAt, array $categories, ?int $affected, bool $seriousRisk, string $measures = ''): self
    {
        return new self(sodium_bin2hex(random_bytes(16)), $summary, $discoveredAt, $occurredAt, $categories, $affected, $seriousRisk, $measures);
    }

    /**
     * The same incident, completed or corrected: a value left null keeps the one it had, and a field named in `$clear`
     * (one of `CLEARABLE`) is emptied, such as a date recorded by mistake; emptying `occurred` empties the end of the
     * incident and its approximation, emptying `affected` its approximation. A text is emptied by an empty string.
     *
     * @param list<DataCategory>|null $categories
     * @param list<string>            $clear
     */
    public function update(
        ?string $measures = null,
        ?bool $seriousRisk = null,
        ?int $affected = null,
        ?array $categories = null,
        ?DateTimeImmutable $authorityNotifiedAt = null,
        ?DateTimeImmutable $subjectsNotifiedAt = null,
        ?DateTimeImmutable $closedAt = null,
        ?string $summary = null,
        ?DateTimeImmutable $discoveredAt = null,
        ?DateTimeImmutable $discoveredUntil = null,
        ?DateTimeImmutable $occurredAt = null,
        ?DateTimeImmutable $occurredUntil = null,
        ?bool $occurrenceApproximate = null,
        ?bool $affectedApproximate = null,
        ?string $information = null,
        ?string $undescribedReason = null,
        ?string $riskAssessment = null,
        ?DateTimeImmutable $publicNoticeAt = null,
        ?PublicNoticeReason $publicNoticeReason = null,
        array $clear = [],
    ): self {
        foreach ($clear as $field) {
            if (!\in_array($field, self::CLEARABLE, true)) {
                throw InvalidPrivacyValue::because(\sprintf('"%s" cannot be emptied; the fields that can are %s.', $field, implode(', ', self::CLEARABLE)));
            }
        }

        $cleared = array_flip($clear);
        $noOccurrence = isset($cleared['occurred']);

        return new self(
            $this->id,
            $summary ?? $this->summary,
            $discoveredAt ?? $this->discoveredAt,
            $noOccurrence ? null : $occurredAt ?? $this->occurredAt,
            $categories ?? $this->categories,
            isset($cleared['affected']) ? null : $affected ?? $this->affected,
            $seriousRisk ?? $this->seriousRisk,
            $measures ?? $this->measures,
            isset($cleared['authority_notified']) ? null : $authorityNotifiedAt ?? $this->authorityNotifiedAt,
            isset($cleared['subjects_notified']) ? null : $subjectsNotifiedAt ?? $this->subjectsNotifiedAt,
            isset($cleared['closed']) ? null : $closedAt ?? $this->closedAt,
            $information ?? $this->information,
            $undescribedReason ?? $this->undescribedReason,
            $noOccurrence || isset($cleared['occurred_until']) ? null : $occurredUntil ?? $this->occurredUntil,
            !$noOccurrence && ($occurrenceApproximate ?? $this->occurrenceApproximate),
            isset($cleared['discovered_until']) ? null : $discoveredUntil ?? $this->discoveredUntil,
            !isset($cleared['affected']) && ($affectedApproximate ?? $this->affectedApproximate),
            $riskAssessment ?? $this->riskAssessment,
            isset($cleared['public_notice']) ? null : $publicNoticeAt ?? $this->publicNoticeAt,
            isset($cleared['public_notice']) ? null : $publicNoticeReason ?? $this->publicNoticeReason,
        );
    }

    public function isClosed(): bool
    {
        return $this->closedAt instanceof DateTimeImmutable;
    }

    /**
     * The notifications a risk of serious injury still asks for: `authority`, `subjects`, or none. A public notice given
     * for one of the reasons that replace the notice to each person stands for it.
     *
     * @return list<'authority'|'subjects'>
     */
    public function pendingNotifications(): array
    {
        if (!$this->seriousRisk) {
            return [];
        }

        $pending = [];

        if (!$this->authorityNotifiedAt instanceof DateTimeImmutable) {
            $pending[] = 'authority';
        }

        if (!$this->subjectsNotifiedAt instanceof DateTimeImmutable && true !== $this->publicNoticeReason?->replacesIndividualNotice()) {
            $pending[] = 'subjects';
        }

        return $pending;
    }

    /**
     * The paragraphs of section 7 of the regulation this line does not answer yet, in their order: 1 without a
     * description of the information nor the reason it cannot be given, 3 without a date of the incident, 5 without a
     * number of people, 6 without a risk assessment, 7 while a notification is still owed, 8 without measures.
     * Paragraphs 2 and 4, the circumstances and the discovery, are required to record a line.
     *
     * @return list<int<1, 8>>
     */
    public function missingParagraphs(): array
    {
        $missing = [];

        if ([] === $this->categories && '' === trim($this->information) && '' === trim($this->undescribedReason)) {
            $missing[] = 1;
        }

        if (!$this->occurredAt instanceof DateTimeImmutable) {
            $missing[] = 3;
        }

        if (null === $this->affected) {
            $missing[] = 5;
        }

        if ('' === trim($this->riskAssessment)) {
            $missing[] = 6;
        }

        if ([] !== $this->pendingNotifications()) {
            $missing[] = 7;
        }

        if ('' === trim($this->measures)) {
            $missing[] = 8;
        }

        return $missing;
    }

    /**
     * The instant the five years of section 8 run from: the end of the discovery, or its date.
     */
    public function knownSince(): DateTimeImmutable
    {
        return $this->discoveredUntil ?? $this->discoveredAt;
    }

    private function period(DateTimeImmutable $start, ?DateTimeImmutable $end, string $what): void
    {
        if ($end instanceof DateTimeImmutable && $end < $start) {
            throw InvalidPrivacyValue::because(\sprintf('The period of the %s cannot end before it starts.', $what));
        }
    }
}
