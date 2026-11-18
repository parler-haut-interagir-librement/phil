# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `ext-filter`, `ext-mbstring` | any | Email addresses; lengths in characters |
| `doctrine/orm` | ^3.7 | Forms and entries |
| `phil/spam-protection-bundle` | ^0.1 | Each form's protection |
| `phil/standard-webhooks` | ^0.1 | Signing webhooks |
| `symfony/form`, `symfony/http-foundation`, `symfony/http-kernel` | ^8.2 | The page of a form |
| `symfony/dependency-injection`, `symfony/config`, `symfony/uid` | ^8.2 | The bundle; identifiers |
| `psr/clock` | ^1.0 | When an entry is received and expires, and the webhook's timestamp; the container's `clock` service, which FrameworkBundle registers once `symfony/clock` is installed |
| `twig/twig` | ^3.21 | The page and the email |
| `symfony/mailer`, `symfony/http-client`, `symfony/console` | optional | Email; the webhook; `phil:forms:purge` |
| `symfony/routing` | optional | Importing `config/routes.php` |
| `phil/privacy-bundle` | optional | Access and erasure requests find the entries about a person |
| `symfony/security-core` | optional | The person signed in is kept with their entries |
| `symfony/key-management` | for sensitive fields | The values of sensitive fields sealed under a master key of a KMS |
| `phil/form-logic` | optional | The conditions applied in the browser too, and read as its whole subset of JsonLogic |

```sh
composer require phil/forms-bundle
```

## The recipe

The Flex recipe of the bundle registers it in `config/bundles.php`, writes `config/packages/phil_forms.yaml` with the
defaults below — no email, no webhook, sensitive fields refused —, and `config/routes/phil_forms.yaml`, which imports
the page of each form under `/forms`. Flex registers `PhilSpamProtectionBundle` with its package. The recipe lives in
the recipes repository of PHIL, read once the application lists that repository in `extra.symfony.endpoint`; without
it, register the bundles and write the configuration by hand, as below.

## By hand

```php
// config/bundles.php
return [
    // ...
    Phil\SpamProtectionBundle\PhilSpamProtectionBundle::class => ['all' => true],
    Phil\FormsBundle\PhilFormsBundle::class => ['all' => true],
];
```

Without `PhilSpamProtectionBundle`, the container refuses to build.

```yaml
# config/packages/phil_forms.yaml
phil_forms:
    sender: site@example.com                    # null: no email
    subject_prefix: '[Site] '
    webhook_secret: '%env(FORMS_WEBHOOK_SECRET)%'  # whsec_…; null: no webhook
    template: '@PhilForms/form.html.twig'       # the page of a form
    digest_key: '%kernel.secret%'               # the key of the addresses' digests
    allow_unsealed_sensitive: false             # true keeps sensitive values as sent when no sealer is configured
    sealing:
        key: null                               # a master key of the KMS; null: no sealer, sensitive fields refused
        kms: null                               # a client such as key_management.vault; null: the default client
```

```yaml
# config/routes/phil_forms.yaml
phil_forms:
    resource: '@PhilFormsBundle/config/routes.php'
    prefix: /forms
```

## Tables

With DoctrineBundle, the bundle maps its entities itself. The application's migrations create three tables:

| Table | Holds |
|---|---|
| `phil_form` | A form: its key, title, fields (JSON), recipients (JSON), other notifications (`notifications`, JSON), webhook, retention, whether it is open, the email field that names the person (`subject_field`) |
| `phil_form_entry` | An entry: its form, values (JSON), when it came and when it is deleted, its language, the identifier of the person (`subject`) and the digest of their address (`email_digest`), each indexed, and the fields whose values are sealed (`sealed_fields`, JSON) |
| `phil_form_choice_count` | The places taken in a choice: its form, field (`field_name`) and choice, the key, and the count (`taken`) |

### Collation of the keys

**Keys compare byte by byte**, as PHP compares them. A form is found by its key, an entry by the
identifier of its person, a count of places by its choice, each as it was written: the form `Contact` is never found
for `contact`, the entries of `Ann` are never listed or erased for `ann`, and the places of the choice `Yes` are never
counted for `yes`.
`BinaryKeysListener` gives these fields the binary collation when the ORM loads their metadata on MySQL or MariaDB;
the mapping itself stays the same on every platform.

| Table | Columns in `utf8mb4_bin` on MySQL and MariaDB |
|---|---|
| `phil_form` | `id`, `form_key` |
| `phil_form_entry` | `id`, `form`, `locale`, `subject`, `email_digest` |
| `phil_form_choice_count` | `form`, `field_name`, `choice` |

On MySQL and MariaDB, these columns take the `utf8mb4` character set and the `utf8mb4_bin` collation, given the
connection's platform: the default collation of MySQL 8, `utf8mb4_0900_ai_ci`, folds case and accents. PostgreSQL's
default collations are deterministic, equal only when the bytes are, and SQLite compares bytes: their columns keep the
default.

#### Upgrading from 0.1

The tables of a 0.1 install on MySQL or MariaDB kept the connection's collation. `doctrine:migrations:diff` writes
the migration that brings the columns above to `utf8mb4_bin`; run it with `doctrine:migrations:migrate`. On PostgreSQL
and SQLite the diff holds only the change below.

The diff also drops the default `'[]'` of `phil_form.notifications` and `phil_form_entry.sealed_fields`, on every
platform: MySQL refuses a literal default on a JSON column, and the bundle writes both columns on every row it
creates.

The join columns `form` are converted with the identifiers they reference, and MySQL refuses to change one side of
a foreign key before the other. Put `SET FOREIGN_KEY_CHECKS = 0` at the start of the migration's `up()` and
`SET FOREIGN_KEY_CHECKS = 1` at its end; the foreign keys stay, and are checked again once both sides are converted:

```php
public function up(Schema $schema): void
{
    $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
    // The statements doctrine:migrations:diff wrote.
    $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
}
```

The change only makes values more distinct, so no existing row breaks a unique key. It parts what the
case-insensitive collation joined: rows whose keys differ only by case, which a lookup by either spelling returned
together, are each found by their own spelling from the migration on, and a key the application looks up in
another case than it wrote stops matching. Before migrating, list the keys written in more than one case:

```sql
SELECT subject, COUNT(*) AS row_count FROM phil_form_entry
GROUP BY subject HAVING COUNT(DISTINCT CAST(subject AS BINARY)) > 1;
```

Each line is one key under several spellings. Keep them apart, or bring the rows to the spelling the application
uses with an `UPDATE` before the migration. A unique key cannot hold two such spellings under the old collation, so
the columns of a unique key need no check.

## Configuration

| Key | Default | Role |
|---|---|---|
| `sender` | `null` | The address entries are emailed from; null sends no email |
| `subject_prefix` | `''` | Put before the form's title in the subject |
| `webhook_secret` | `null` | The Standard Webhooks secret, `whsec_…`; null posts to no webhook |
| `template` | `@PhilForms/form.html.twig` | The page of a form |
| `digest_key` | `%kernel.secret%` | The key of the digests of addresses that name the person an entry is about |
| `allow_unsealed_sensitive` | `false` | Keeps sensitive values as sent when no sealer is configured, instead of refusing a form with a sensitive field; see the warning in [Entries and retention](entries.md#keeping-sensitive-values-as-sent) |
| `sealing.key` | `null` | The master key of the KMS that seals sensitive values; null configures no sealer |
| `sealing.kms` | `null` | The service of a client of the KeyManagement component; null for its default client |

## With phil/form-logic

With `phil/form-logic` installed, the page writes the library's Stimulus controller on a form whose fields have
conditions, and the browser shows and hides them as the form is filled; the controller is served with AssetMapper and
StimulusBundle, as the library describes in its `doc/installation.md`, and the application's page loads the import
map — see [Forms](forms.md#in-the-browser). An alias of the port `ConditionEvaluator` to its `JsonLogicConditions`
also reads the conditions as the library's whole subset of JsonLogic; see
[Forms](forms.md#richer-conditions-with-philform-logic).

## Sensitive fields

A form with a sensitive field needs a sealer: install `symfony/key-management`, register its `KeyManagementBundle`
with a client, and name a master key of that client in `sealing.key`, as
[Entries and retention](entries.md#sensitive-values) shows. Without one, such a form is refused, unless
`allow_unsealed_sensitive` is true.
