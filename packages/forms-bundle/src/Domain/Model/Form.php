<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\FormsBundle\Domain\Contract\ConditionEvaluator;
use Phil\FormsBundle\Domain\Contract\FieldSealer;
use Phil\FormsBundle\Domain\Exception\InvalidEntry;
use Phil\FormsBundle\Domain\Exception\InvalidForm;
use Phil\FormsBundle\Domain\Exception\UnsealedSensitiveField;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\Logic\BasicConditions;
use Phil\FormsBundle\Domain\Subject\EmailDigester;
use Phil\FormsBundle\Domain\ValueObject\Field;
use Phil\FormsBundle\Domain\ValueObject\Notification;
use Symfony\Component\Uid\Uuid;

/**
 * A form defined in the database: its key in URLs, its title, its fields, who hears of each entry — email
 * recipients, other emails under conditions, a webhook —, how many days its entries are kept, and whether it takes
 * entries. A field may be asked under a condition on the fields before it, or for some tiers only: a field not asked
 * keeps no value.
 *
 * @phpstan-import-type FieldArray from Field
 * @phpstan-import-type NotificationArray from Notification
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_form')]
#[ORM\UniqueConstraint(columns: ['form_key'])]
final class Form
{
    /** The most notifications a form sends beside the one to its recipients. */
    public const int MAX_NOTIFICATIONS = 20;

    /**
     * The fields built from their definitions, and those definitions: built again when the definitions change, by
     * `define()` or by Doctrine. Not mapped.
     *
     * @var array{0: list<FieldArray>, 1: list<Field>}|null
     */
    private ?array $built = null;

    /**
     * @param list<FieldArray>        $fields
     * @param list<string>            $recipients
     * @param list<NotificationArray> $notifications
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'form_key', length: 64)]
        public readonly string $key,
        #[ORM\Column(name: 'title', length: 255)]
        private string $title,
        #[ORM\Column(name: 'fields', type: 'json')]
        private array $fields,
        #[ORM\Column(name: 'recipients', type: 'json')]
        private array $recipients,
        #[ORM\Column(name: 'webhook_url', length: 2000, nullable: true)]
        private ?string $webhookUrl,
        #[ORM\Column(name: 'retention_days')]
        private int $retentionDays,
        #[ORM\Column(name: 'is_open')]
        private bool $open,
        #[ORM\Column(name: 'subject_field', length: 64, nullable: true)]
        private ?string $subjectField = null,
        #[ORM\Column(name: 'notifications', type: 'json', options: ['default' => '[]'])]
        private array $notifications = [],
    ) {
    }

    /**
     * @param list<Field> $fields
     */
    public static function create(string $key, string $title, array $fields, int $retentionDays = 365): self
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/D', $key)) {
            throw InvalidForm::because(\sprintf('A form key is 1 to 64 lower-case letters, digits or "-"; "%s" is not.', $key));
        }

        $form = new self(Uuid::v7()->toRfc4122(), $key, '', [], [], null, self::days($retentionDays), true);
        $form->rename($title);
        $form->define($fields);

        return $form;
    }

    public function rename(string $title): void
    {
        $title = trim($title);

        if ('' === $title || mb_strlen($title) > 255) {
            throw InvalidForm::because('A form title is 1 to 255 characters.');
        }

        $this->title = $title;
    }

    /**
     * The fields, replaced. The condition of a field reads only fields before it, checked by `$logic`; the
     * notifications must still read fields of the form.
     *
     * @param list<Field> $fields
     */
    public function define(array $fields, ?ConditionEvaluator $logic = null): void
    {
        $logic ??= new BasicConditions();
        $earlier = [];

        foreach ($fields as $field) {
            if (\array_key_exists($field->name, $earlier)) {
                throw InvalidForm::because(\sprintf('A form has one field named "%s".', $field->name));
            }

            if (null !== $field->shownWhen) {
                $logic->check($field->shownWhen, $earlier);
            }

            $earlier[$field->name] = $field;
        }

        if ([] === $earlier) {
            throw InvalidForm::because('A form has at least one field.');
        }

        if (null !== $this->subjectField) {
            $this->checkSubjectField($fields, $this->subjectField);
        }

        $this->checkNotifications(array_map(Notification::fromArray(...), $this->notifications), $earlier, $logic);
        $this->fields = array_values(array_map(static fn (Field $field): array => $field->toArray(), $earlier));
    }

    /**
     * The email field whose address names the person an entry is about, when the visitor is not known: the entry
     * keeps the address's keyed digest, which an access or erasure request finds. Null designates none.
     *
     * @throws InvalidForm when the form has no email field of that name
     */
    public function identifyBy(?string $field): void
    {
        if (null !== $field) {
            $this->checkSubjectField($this->fields(), $field);
        }

        $this->subjectField = $field;
    }

    public function subjectField(): ?string
    {
        return $this->subjectField;
    }

    /**
     * @param list<string> $recipients email addresses
     * @param string|null  $webhookUrl an `https` URL, whose host is a name or a public address: entries carry personal
     *                                 data, never sent in clear nor to the server's own network
     */
    public function notify(array $recipients, ?string $webhookUrl = null): void
    {
        foreach ($recipients as $recipient) {
            if (false === filter_var($recipient, \FILTER_VALIDATE_EMAIL)) {
                throw InvalidForm::because(\sprintf('"%s" is not an email address.', $recipient));
            }
        }

        if (null !== $webhookUrl) {
            $this->checkWebhook($webhookUrl);
        }

        $this->recipients = array_values(array_unique($recipients));
        $this->webhookUrl = $webhookUrl;
    }

    /**
     * The emails sent of each entry beside the one to the recipients, replaced: at most 20, their conditions and
     * subjects reading fields of the form, the subjects text fields only.
     *
     * @param list<Notification> $notifications
     */
    public function notifyAlso(array $notifications, ?ConditionEvaluator $logic = null): void
    {
        if (\count($notifications) > self::MAX_NOTIFICATIONS) {
            throw InvalidForm::because(\sprintf('A form sends at most %d other notifications.', self::MAX_NOTIFICATIONS));
        }

        $fields = [];

        foreach ($this->fields() as $field) {
            $fields[$field->name] = $field;
        }

        $this->checkNotifications($notifications, $fields, $logic ?? new BasicConditions());
        $this->notifications = array_map(static fn (Notification $notification): array => $notification->toArray(), $notifications);
    }

    /**
     * @return list<Notification>
     */
    public function notifications(): array
    {
        return array_map(Notification::fromArray(...), $this->notifications);
    }

    /**
     * @throws InvalidForm when the URL is not `https`, or its host is the server's own or an address of a private,
     *                     loopback, link-local or reserved network
     */
    private function checkWebhook(string $url): void
    {
        if (mb_strlen($url) > 2000 || 1 !== preg_match('{^https://([^\s/?#@]+@)?(?<host>\[[0-9A-Fa-f:.]+\]|[^\s/?#:@\[\]]+)(:\d{1,5})?([/?#]\S*)?$}iD', $url, $match)) {
            throw InvalidForm::because(\sprintf('"%s" is not an https URL.', $url));
        }

        $host = strtolower(rtrim(trim($match['host'], '[]'), '.'));
        $address = filter_var($host, \FILTER_VALIDATE_IP);
        $public = false === $address || false !== filter_var($host, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE);

        if ('localhost' === $host || str_ends_with($host, '.localhost') || !$public || (false === $address && 1 === preg_match('/^[0-9.]+$/', $host))) {
            throw InvalidForm::because(\sprintf('"%s" points at the server\'s own network: a webhook goes to a public host.', $url));
        }
    }

    /**
     * How many days an entry is kept, from 1 to 3650: no longer than its purpose needs.
     */
    public function keepFor(int $days): void
    {
        $this->retentionDays = self::days($days);
    }

    public function open(): void
    {
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    /**
     * The values kept for an entry, one per field asked, in the form's order; refused with every reason at once. A
     * field is asked when it is for one of `$tiers` — or for every entry —, and when its condition holds for the
     * values before it, every field it reads having been asked: a field not asked keeps no value, whatever was sent.
     *
     * @param array<array-key, mixed> $submitted
     * @param DateTimeImmutable|null  $at        when the entry comes, which bounds a birth date
     * @param list<string>            $tiers     the tiers of the entry
     *
     * @return array<string, string|bool|null>
     */
    public function accept(array $submitted, ?DateTimeImmutable $at = null, array $tiers = [], ?ConditionEvaluator $logic = null): array
    {
        if (!$this->open) {
            throw InvalidEntry::with(['form' => 'closed']);
        }

        $logic ??= new BasicConditions();
        $values = [];
        $errors = [];

        foreach ($this->fields() as $field) {
            if (!$field->isFor($tiers) || !$this->holds($field->shownWhen, $values, $logic)) {
                continue;
            }

            $accepted = $field->accept($submitted[$field->name] ?? null, $at);
            $values[$field->name] = $accepted['value'];

            if (null !== $accepted['error']) {
                $errors[$field->name] = $accepted['error'];
            }
        }

        if ([] !== $errors) {
            throw InvalidEntry::with($errors);
        }

        return $values;
    }

    /**
     * Whether a condition holds for the values of the fields asked: a condition that reads a field not asked does
     * not. No condition always holds.
     *
     * @param array<array-key, mixed>|null    $condition
     * @param array<string, string|bool|null> $values
     */
    public function holds(?array $condition, array $values, ?ConditionEvaluator $logic = null): bool
    {
        if (null === $condition) {
            return true;
        }

        $logic ??= new BasicConditions();

        foreach ($logic->fields($condition) as $name) {
            if (!\array_key_exists($name, $values)) {
                return false;
            }
        }

        return $logic->holds($condition, $values);
    }

    /**
     * An entry of these values, kept for the form's retention, about the person `$subject` names when they are
     * known; with a digester, the address of the designated email field is kept as its digest; the values of sensitive
     * fields are kept sealed by `$sealer`, bound to the entry and the field.
     *
     * @param array<array-key, mixed> $submitted
     * @param list<string>            $tiers
     * @param bool                    $allowUnsealed whether a form with a sensitive field takes entries without a
     *                                               sealer, its sensitive values kept as sent
     *
     * @throws InvalidEntry           when a value is refused
     * @throws UnsealedSensitiveField when the form has a sensitive field, no sealer is given and `$allowUnsealed` is
     *                                false
     */
    public function submit(array $submitted, DateTimeImmutable $at, ?string $locale = null, ?string $subject = null, ?EmailDigester $digester = null, array $tiers = [], ?ConditionEvaluator $logic = null, ?FieldSealer $sealer = null, bool $allowUnsealed = false): Entry
    {
        $this->checkSealing($sealer, $allowUnsealed);
        $values = $this->accept($submitted, $at, $tiers, $logic);
        $email = null === $this->subjectField ? null : $values[$this->subjectField] ?? null;
        $digest = \is_string($email) && '' !== $email ? $digester?->digest($email) : null;
        $id = Uuid::v7()->toRfc4122();
        $sealed = [];

        foreach ($this->fields() as $field) {
            $value = $values[$field->name] ?? null;

            if ($sealer instanceof FieldSealer && $field->sensitive && \is_string($value)) {
                $values[$field->name] = $sealer->seal($value, Entry::context($id, $field->name));
                $sealed[] = $field->name;
            }
        }

        return Entry::of($this, $values, $at, $at->modify(\sprintf('+%d days', $this->retentionDays)), $locale, $subject, $digest, $id, $sealed);
    }

    /**
     * Refuses a form with a sensitive field when no sealer is given, unless the application chose to keep sensitive
     * values as sent.
     *
     * @throws UnsealedSensitiveField
     */
    public function checkSealing(?FieldSealer $sealer, bool $allowUnsealed = false): void
    {
        if ($sealer instanceof FieldSealer || $allowUnsealed) {
            return;
        }

        foreach ($this->fields() as $field) {
            if ($field->sensitive) {
                throw UnsealedSensitiveField::in($this->key, $field->name);
            }
        }
    }

    public function title(): string
    {
        return $this->title;
    }

    /**
     * The fields, built once from their definitions while those do not change.
     *
     * @return list<Field>
     */
    public function fields(): array
    {
        // The same definitions are the same array: comparing them costs nothing while they did not change.
        if (($this->built[0] ?? null) !== $this->fields) {
            $this->built = [$this->fields, array_map(Field::fromArray(...), $this->fields)];
        }

        return $this->built[1];
    }

    /**
     * The fields an entry for these tiers may ask, before their conditions.
     *
     * @param list<string> $tiers
     *
     * @return list<Field>
     */
    public function fieldsFor(array $tiers): array
    {
        return array_values(array_filter($this->fields(), static fn (Field $field): bool => $field->isFor($tiers)));
    }

    /**
     * @return list<string>
     */
    public function recipients(): array
    {
        return $this->recipients;
    }

    public function webhookUrl(): ?string
    {
        return $this->webhookUrl;
    }

    public function retentionDays(): int
    {
        return $this->retentionDays;
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    /**
     * @param list<Field> $fields
     */
    private function checkSubjectField(array $fields, string $name): void
    {
        foreach ($fields as $field) {
            if ($name === $field->name && FieldType::Email === $field->type) {
                return;
            }
        }

        throw InvalidForm::because(\sprintf('The field "%s" that names the person must be an email field of the form.', $name));
    }

    /**
     * @param list<Notification>   $notifications
     * @param array<string, Field> $fields        by name
     */
    private function checkNotifications(array $notifications, array $fields, ConditionEvaluator $logic): void
    {
        foreach ($notifications as $notification) {
            if (null !== $notification->when) {
                $logic->check($notification->when, $fields);
            }

            foreach ($notification->placeholders() as $name) {
                $field = $fields[$name] ?? null;

                if (null === $field || $field->sensitive || FieldType::Checkbox === $field->type) {
                    throw InvalidForm::because(\sprintf('The subject of a notification names "%s", which is not a text field of the form that may be shown.', $name));
                }
            }
        }
    }

    private static function days(int $days): int
    {
        if ($days < 1 || $days > 3650) {
            throw InvalidForm::because('Entries are kept 1 to 3650 days.');
        }

        return $days;
    }
}
