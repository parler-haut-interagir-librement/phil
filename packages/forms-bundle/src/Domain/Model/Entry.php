<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * What a visitor sent through a form: the values, one per field, when, in which language, and when it is deleted.
 * Nothing else about the visitor — no address, no browser — is kept, except what names the person the entry is
 * about, for an access or erasure request: their identifier when they were known, or the keyed digest of the
 * address in the form's designated email field. The values of sensitive fields are kept sealed when a `FieldSealer` is
 * installed: `$sealed` names them, and only the sealer opens them, bound to this entry and the field.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_form_entry')]
#[ORM\Index(columns: ['purge_after'])]
#[ORM\Index(columns: ['subject'])]
#[ORM\Index(columns: ['email_digest'])]
final class Entry
{
    private const string UTC = 'UTC';

    /**
     * @param array<string, string|bool|null> $values
     * @param list<string>                    $sealed
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\ManyToOne(targetEntity: Form::class)]
        #[ORM\JoinColumn(name: 'form', nullable: false, onDelete: 'CASCADE')]
        public readonly Form $form,
        #[ORM\Column(name: 'entry_values', type: 'json')]
        public readonly array $values,
        #[ORM\Column(name: 'submitted_at')]
        private DateTimeImmutable $submittedAt,
        #[ORM\Column(name: 'purge_after')]
        private DateTimeImmutable $purgeAfter,
        #[ORM\Column(name: 'locale', length: 12, nullable: true)]
        public readonly ?string $locale,
        #[ORM\Column(name: 'subject', length: 255, nullable: true)]
        public readonly ?string $subject,
        #[ORM\Column(name: 'email_digest', length: 64, nullable: true)]
        public readonly ?string $emailDigest,
        #[ORM\Column(name: 'sealed_fields', type: 'json')]
        public readonly array $sealed = [],
    ) {
    }

    /**
     * @param array<string, string|bool|null> $values
     * @param string|null                     $subject     the identifier of the person, when they were known; kept as
     *                                                     `subjectKey()` makes it
     * @param string|null                     $emailDigest the keyed digest of the address in the designated email field
     * @param string|null                     $id          the entry's UUID, given when values were sealed for it
     * @param list<string>                    $sealed      the fields whose values are sealed
     */
    public static function of(Form $form, array $values, DateTimeImmutable $at, DateTimeImmutable $purgeAfter, ?string $locale, ?string $subject = null, ?string $emailDigest = null, ?string $id = null, array $sealed = []): self
    {
        $utc = new DateTimeZone(self::UTC);

        return new self($id ?? Uuid::v7()->toRfc4122(), $form, $values, $at->setTimezone($utc), $purgeAfter->setTimezone($utc), $locale, null === $subject || '' === $subject ? null : self::subjectKey($subject), $emailDigest, $sealed);
    }

    /**
     * What the sealed value of a field is bound to: the entry and the field.
     */
    public static function context(string $id, string $field): string
    {
        return 'phil_forms.entry.'.$id.'.'.$field;
    }

    /**
     * How a person's identifier is kept, and looked for: as it is when it is 1 to 255 bytes without whitespace or
     * control characters; otherwise — `Jean Dupont`, a long identifier — as `sha256:` and its SHA-256 in hexadecimal,
     * which an access or erasure request made with the same identifier finds again.
     */
    public static function subjectKey(string $identifier): string
    {
        if (\strlen($identifier) <= 255 && 1 !== preg_match('/[\s\x00-\x1F\x7F]/', $identifier)) {
            return $identifier;
        }

        return 'sha256:'.hash('sha256', $identifier);
    }

    /**
     * When the entry came, in UTC.
     */
    public function submittedAt(): DateTimeImmutable
    {
        return $this->read($this->submittedAt);
    }

    /**
     * When the entry is deleted, in UTC.
     */
    public function purgeAfter(): DateTimeImmutable
    {
        return $this->read($this->purgeAfter);
    }

    /**
     * Doctrine reads a `datetime_immutable` in PHP's default time zone: the stored wall clock is UTC.
     */
    private function read(DateTimeImmutable $stored): DateTimeImmutable
    {
        return new DateTimeImmutable($stored->format('Y-m-d H:i:s.u'), new DateTimeZone(self::UTC));
    }
}
