# Extension points

What an application implements, tags, configures or overrides. Spam protection comes from
`phil/spam-protection-bundle`, signed webhooks from `phil/standard-webhooks`.

## Ports

### `EntryNotifier`

`Phil\FormsBundle\Application\EntryNotifier` — told of every entry once it is kept. A service implementing it is
tagged `phil_forms.notifier` by autoconfiguration; `EmailNotifier` and `WebhookNotifier` are shipped. See
[Notifications](notifications.md).

| Method | Contract |
|---|---|
| `notify(Entry $entry): void` | Sends the entry where it goes — a CRM, a newsletter, a ticket; a failure is logged without the values and the other notifiers still run, the entry kept |

### `CurrentSubject`

`Phil\FormsBundle\Application\CurrentSubject` — who an entry is about, when they are known as they send it.
`TokenCurrentSubject` names the user signed in through Symfony Security; an application whose access and erasure
requests name people otherwise aliases the port to its own service. See [Entries and retention](entries.md).

| Method | Contract |
|---|---|
| `current(): ?string` | The person's identifier, or `null` for a visitor |

### `ConditionEvaluator`

`Phil\FormsBundle\Domain\Contract\ConditionEvaluator` — reads the conditions of fields and notifications.
`BasicConditions` is the default, aliased as the port; an evaluator of richer logic takes its place by an alias of the
application, the conditions staying data whose JSON is a subset of JsonLogic. `JsonLogicConditions`, from
`phil/form-logic`, reads its whole subset — `and`, `or`, `!`, orderings, `missing`, arithmetic —; under both, `!=` holds
for a field left empty, as in JsonLogic. See [Forms](forms.md#richer-conditions-with-philform-logic).

| Method | Contract |
|---|---|
| `fields(array $condition): list<string>` | The fields the condition reads, each once; a condition that reads a field not asked never holds |
| `check(array $condition, array $fields): void` | Throws `InvalidForm` for a condition it does not read, or that reads a field missing from `$fields` — the fields before it — or compares one with values it never takes |
| `holds(array $condition, array $values): bool` | Whether the condition holds for the values of the fields asked; bounded work, no I/O |

### `FieldSealer`

`Phil\FormsBundle\Domain\Contract\FieldSealer` — seals the values of sensitive fields. `KeyManagementFieldSealer` is
wired when `sealing.key` names a master key, on the client of Symfony's KeyManagement component `sealing.kms` names;
an application aliases the port to its own service to seal otherwise. Without either, a form with a sensitive field is
refused with `UnsealedSensitiveField`, unless `allow_unsealed_sensitive` is true. See
[Entries and retention](entries.md#sensitive-values).

| Method | Contract |
|---|---|
| `seal(string $value, string $context): string` | Printable ASCII, the context — `phil_forms.entry.{id}.{field}` — authenticated with the value |
| `open(string $sealed, string $context): string` | Throws `UnreadableValue` when the value was altered, moved, or sealed under a key no longer known |

### `ChoicePlaces`

`Phil\FormsBundle\Domain\Contract\ChoicePlaces` — the places taken in choices that have a capacity, a public alias of
`DoctrineFormRepository`. A store of the application's own takes a place atomically, never above the capacity, and
gives places back when entries are removed.

| Method | Contract |
|---|---|
| `take(Form $form, string $field, string $choice, int $capacity): bool` | Whether a place was taken |
| `release(Form $form, string $field, string $choice): void` | Gives a place back, never below none |
| `taken(Form $form, string $field, string $choice): int` | The places taken |

### `FormRepository`

`Phil\FormsBundle\Domain\Repository\FormRepository` — forms and entries, a public alias of `DoctrineFormRepository`.
A store of the application's own keeps `expired()` bounded by its limit and `about()` keyed on the subject and the
address digest, so that retention and privacy requests never read every entry.

### `EmailDigester`

`Phil\FormsBundle\Domain\Subject\EmailDigester` — the digest an entry keeps of an address. `HmacEmailDigester` is the
HMAC-SHA256 of the address trimmed and lower-cased, under `digest_key`.

## Configuration

| Option | What it extends |
|---|---|
| `sender`, `subject_prefix` | The email of an entry; no sender, no email |
| `webhook_secret` | The signing key of the webhook; none, no webhook |
| `template` | The page of a form |
| `digest_key` | The key of the addresses' digests; changing it loses the entries already kept to requests by address |
| `sealing.key`, `sealing.kms` | The master key and the client of Symfony's KeyManagement component that seal sensitive values; no key, no sealer |
| `allow_unsealed_sensitive` | Keeps sensitive values as sent when no sealer is configured, instead of refusing the form |

## Templates

| Template | How an application overrides it |
|---|---|
| `@PhilForms/form.html.twig` | `template`, or a page of its own that extends its layout and includes `@PhilForms/_form.html.twig` with `form`, `definition` and `sent` |
| `@PhilForms/email/entry.html.twig`, `.txt.twig` | Overridden as any bundle template |

## Services

| Service | What an application does with it |
|---|---|
| `Submissions` | `submit($form, $values, $locale, $subject, $tiers)` takes an entry from code, as an order of tickets does for each person; `fullChoices($form)` |
| `SealedValues` | `reveal($entry)` gives the values of an entry as they were sent, for a screen of the application |

## Privacy

With `phil/privacy-bundle`, `FormEntriesPrivacy` answers access and erasure requests as its module `forms`.
