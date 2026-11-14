<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Domain\ValueObject;

use Phil\FormsBundle\Domain\Exception\InvalidForm;

/**
 * An email a form sends of each entry, beside the one to its recipients: to other addresses, with its own subject,
 * under a condition on the values — the address of a department chosen in a field, say. The addresses are the form's,
 * never one the visitor typed, which would make the form a relay for spam. The subject may name values,
 * `New answer from {name}`, each `{field}` replaced by the value of that field on one line.
 *
 * @phpstan-type NotificationArray array{recipients: list<string>, subject: ?string, when: ?array<array-key, mixed>}
 */
final readonly class Notification
{
    /** The most addresses a notification goes to. */
    public const int MAX_RECIPIENTS = 50;

    /**
     * @param list<string>                 $recipients
     * @param array<array-key, mixed>|null $when
     */
    private function __construct(
        public array $recipients,
        public ?string $subject,
        public ?array $when,
    ) {
    }

    /**
     * @param list<string>                 $recipients 1 to 50 email addresses
     * @param string|null                  $subject    1 to 255 characters, `{field}` naming a value; null: the form's title
     * @param array<array-key, mixed>|null $when       the condition the values meet for the email to be sent, read by
     *                                                 the form's `ConditionEvaluator`; null: every entry
     */
    public static function to(array $recipients, ?string $subject = null, ?array $when = null): self
    {
        $recipients = array_values(array_unique($recipients));

        if ([] === $recipients || \count($recipients) > self::MAX_RECIPIENTS) {
            throw InvalidForm::because(\sprintf('A notification goes to 1 to %d addresses.', self::MAX_RECIPIENTS));
        }

        foreach ($recipients as $recipient) {
            if (false === filter_var($recipient, \FILTER_VALIDATE_EMAIL)) {
                throw InvalidForm::because(\sprintf('"%s" is not an email address.', $recipient));
            }
        }

        if (null !== $subject && ('' === trim($subject) || mb_strlen($subject) > 255 || 1 === preg_match('/[\r\n]/', $subject))) {
            throw InvalidForm::because('The subject of a notification is one line of 1 to 255 characters.');
        }

        return new self($recipients, null === $subject ? null : trim($subject), $when);
    }

    /**
     * @param NotificationArray $notification
     */
    public static function fromArray(array $notification): self
    {
        return self::to($notification['recipients'], $notification['subject'], $notification['when']);
    }

    /**
     * @return NotificationArray
     */
    public function toArray(): array
    {
        return ['recipients' => $this->recipients, 'subject' => $this->subject, 'when' => $this->when];
    }

    /**
     * The names of the fields the subject names.
     *
     * @return list<string>
     */
    public function placeholders(): array
    {
        preg_match_all('/\{([a-z][a-z0-9_]{0,63})\}/', $this->subject ?? '', $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * The subject, each `{field}` replaced by its value on one line — nothing when it has none —, then cut at 255
     * characters; the form's title when the notification has no subject of its own. The form lets a subject name text
     * fields only, never a checkbox or a sensitive field.
     *
     * @param array<string, string|bool|null> $values
     */
    public function subjectFor(string $title, array $values): string
    {
        if (null === $this->subject) {
            return $title;
        }

        $subject = preg_replace_callback('/\{([a-z][a-z0-9_]{0,63})\}/', static function (array $match) use ($values): string {
            $value = $values[$match[1]] ?? null;

            return \is_string($value) ? (string) preg_replace('/\s+/u', ' ', $value) : '';
        }, $this->subject);

        return mb_substr(trim((string) $subject), 0, 255);
    }
}
