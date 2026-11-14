<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Incident;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;

/**
 * One line of the register of confidentiality incidents: what happened, when it was found, what it concerns, whether it
 * presents a risk of serious injury, what was done, and when the authority and the people concerned were told.
 *
 * The law asks a business to keep a line for **every** incident, serious or not, and to notify the authority and the
 * people concerned of one that presents a risk of serious injury: `pendingNotifications()` names those still owed. A
 * line is never removed by hand; a retention rule on its store removes it once its period has run.
 */
final readonly class Incident
{
    public const string ID_PATTERN = '/^[0-9a-f]{32}$/D';

    public const int MAX_TEXT = 4000;

    /**
     * @var list<DataCategory>
     */
    public array $categories;

    /**
     * @param list<DataCategory> $categories the kinds of personal information concerned
     * @param int|null           $affected   how many people are concerned, or null while it is not known
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
    ) {
        if (1 !== preg_match(self::ID_PATTERN, $id)) {
            throw InvalidPrivacyValue::because('An incident identifier is 32 lowercase hexadecimal characters.');
        }

        if ('' === trim($summary) || mb_strlen($summary) > self::MAX_TEXT || mb_strlen($measures) > self::MAX_TEXT) {
            throw InvalidPrivacyValue::because(\sprintf('An incident has a summary, and a summary and its measures are at most %d characters each.', self::MAX_TEXT));
        }

        if (null !== $affected && $affected < 0) {
            throw InvalidPrivacyValue::because('The number of people an incident concerns cannot be negative.');
        }

        if ($occurredAt instanceof DateTimeImmutable && $occurredAt > $discoveredAt) {
            throw InvalidPrivacyValue::because('An incident cannot have occurred after it was discovered.');
        }

        foreach (['authority notified' => $authorityNotifiedAt, 'people notified' => $subjectsNotifiedAt, 'closed' => $closedAt] as $what => $moment) {
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
     * The same incident with what changed since it was recorded; a value left null keeps the one it had.
     *
     * @param list<DataCategory>|null $categories
     */
    public function update(
        ?string $measures = null,
        ?bool $seriousRisk = null,
        ?int $affected = null,
        ?array $categories = null,
        ?DateTimeImmutable $authorityNotifiedAt = null,
        ?DateTimeImmutable $subjectsNotifiedAt = null,
        ?DateTimeImmutable $closedAt = null,
    ): self {
        return new self(
            $this->id,
            $this->summary,
            $this->discoveredAt,
            $this->occurredAt,
            $categories ?? $this->categories,
            $affected ?? $this->affected,
            $seriousRisk ?? $this->seriousRisk,
            $measures ?? $this->measures,
            $authorityNotifiedAt ?? $this->authorityNotifiedAt,
            $subjectsNotifiedAt ?? $this->subjectsNotifiedAt,
            $closedAt ?? $this->closedAt,
        );
    }

    public function isClosed(): bool
    {
        return $this->closedAt instanceof DateTimeImmutable;
    }

    /**
     * The notifications a risk of serious injury still asks for: `authority`, `subjects`, or none.
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

        if (!$this->subjectsNotifiedAt instanceof DateTimeImmutable) {
            $pending[] = 'subjects';
        }

        return $pending;
    }
}
