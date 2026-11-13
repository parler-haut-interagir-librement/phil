<?php

declare(strict_types=1);

namespace Phil\AccessLinks;

use DateTimeImmutable;
use Phil\AccessLinks\Exception\InvalidConfiguration;

/**
 * One recipient invited to one campaign, and what they answered.
 *
 * Its moments, in order: the link opens from `validFrom` (its creation by default), the answer can change until
 * `closesAt`, and the link opens, to show the answer, until `expiresAt`, never before the closure. The recipient is a
 * stable identifier of the application, never an email address: the library sends nothing and knows no address.
 */
final readonly class Invitation
{
    /** The identifier of a campaign: what the invitation is to, as the application names it. */
    public const string CAMPAIGN_PATTERN = '/^[A-Za-z0-9:._-]{1,100}$/D';

    public const int MAX_RECIPIENT_LENGTH = 191;

    public const int MAX_ANSWER_LENGTH = 4096;

    public DateTimeImmutable $validFrom;

    public DateTimeImmutable $expiresAt;

    /**
     * @param string      $id     32 hexadecimal digits
     * @param string|null $answer what the recipient answered, as the application encodes it
     *
     * @throws InvalidConfiguration when a value is out of bounds or the moments are out of order
     */
    public function __construct(
        public string $id,
        public string $campaign,
        public string $recipient,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $closesAt,
        ?DateTimeImmutable $expiresAt = null,
        ?DateTimeImmutable $validFrom = null,
        public ?string $answer = null,
        public ?DateTimeImmutable $respondedAt = null,
        public ?DateTimeImmutable $answeredAt = null,
        public int $remindersSent = 0,
        public ?DateTimeImmutable $nextReminderAt = null,
        public ?DateTimeImmutable $revokedAt = null,
    ) {
        if (1 !== preg_match('/^[0-9a-f]{32}$/D', $id)) {
            throw InvalidConfiguration::because('An invitation identifier is 32 lowercase hexadecimal digits.');
        }

        if (1 !== preg_match(self::CAMPAIGN_PATTERN, $campaign)) {
            throw InvalidConfiguration::because('A campaign is 1 to 100 letters, digits, colons, dots, hyphens and underscores.');
        }

        self::checkRecipient($recipient);

        if (null !== $answer) {
            self::checkAnswer($answer);
        }

        $this->validFrom = $validFrom ?? $createdAt;
        $this->expiresAt = $expiresAt ?? $closesAt;

        if ($closesAt <= $this->validFrom) {
            throw InvalidConfiguration::because('An invitation closes after its link opens.');
        }

        if ($this->expiresAt < $closesAt) {
            throw InvalidConfiguration::because('The link of an invitation expires at its closure or after.');
        }

        if ($remindersSent < 0) {
            throw InvalidConfiguration::because('The reminders sent are a whole number from zero.');
        }
    }

    /**
     * @throws InvalidConfiguration when the recipient is empty, too long, or holds an `@`
     */
    public static function checkRecipient(string $recipient): void
    {
        if ('' === trim($recipient) || \strlen($recipient) > self::MAX_RECIPIENT_LENGTH || str_contains($recipient, '@')) {
            throw InvalidConfiguration::because(\sprintf('A recipient is a stable identifier of 1 to %d bytes, never an email address.', self::MAX_RECIPIENT_LENGTH));
        }
    }

    /**
     * @throws InvalidConfiguration when the answer is empty, too long, or not UTF-8
     */
    public static function checkAnswer(string $answer): void
    {
        if ('' === $answer || \strlen($answer) > self::MAX_ANSWER_LENGTH || !mb_check_encoding($answer, 'UTF-8')) {
            throw InvalidConfiguration::because(\sprintf('An answer is 1 to %d bytes of UTF-8.', self::MAX_ANSWER_LENGTH));
        }
    }

    public function hasResponded(): bool
    {
        return $this->respondedAt instanceof DateTimeImmutable;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt instanceof DateTimeImmutable;
    }

    /**
     * Whether the link opens at this moment: not revoked, valid, not expired.
     */
    public function opensAt(DateTimeImmutable $now): bool
    {
        return !$this->isRevoked() && $this->validFrom <= $now && $now < $this->expiresAt;
    }

    /**
     * Whether the answer can change at this moment: the link opens and the invitation is not closed.
     */
    public function isEditableAt(DateTimeImmutable $now): bool
    {
        return $this->opensAt($now) && $now < $this->closesAt;
    }
}
