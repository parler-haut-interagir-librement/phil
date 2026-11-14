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
