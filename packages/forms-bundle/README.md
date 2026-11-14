# phil/forms-bundle

**Contact and registration forms defined in the database, with their entries kept only as long as needed.**

phil/forms-bundle gives a Symfony application forms that live in its database. A form is a key, a title and fields:
text, paragraphs, email, URL, date, birth date, a choice, a checkbox. A field may be asked under a condition on the
fields before it, or only for some tiers — a kind of ticket, a membership —; a choice may disqualify the entry with
a message, or hold a number of places. The bundle gives each form a page at `/forms/{key}`,
protected against spam by `phil/spam-protection-bundle` without a captcha or a third party. It keeps every entry no
longer than the form says, then `phil:forms:purge` deletes it. It tells whoever should hear of an entry by email from
the site's own address — to its recipients, and to other addresses under conditions —, by a webhook signed with
Standard Webhooks, or through the application's own notifiers. The values of sensitive fields never leave by email
or webhook, and are sealed under a master key of a KMS through Symfony's KeyManagement component; a form with a
sensitive field is refused while no sealer is configured. A birth date may take only some ages, counted on the day
the entry comes or on a date of the form's choosing. Its conditions read `==`, `!=` and `in` on one field; with
`phil/form-logic`, they also apply in the browser as the form is filled, and may read that library's whole subset of
JsonLogic, checked against the types of the fields.

It does not keep the visitor's IP address, browser or cookies: an entry holds the values, its language and its dates.
It never sends a copy to the visitor, which would make a form a relay for spam, and it never shows a captcha. To
answer an access or erasure request, an entry may also keep the signed-in person's identifier, or a keyed digest of an
email address the form designates. With `phil/privacy-bundle`, those entries are exported or erased by person.

It is for a site that collects registrations, questions or feedback, and whose forms are data the application creates
and changes at run time. Retention by form, from 1 to 3650 days, serves the purpose limits of Québec's Law 25 and the
GDPR. Symfony Mailer, the HTTP client, Console, Routing and Security are optional, each for the part that needs it.
`Form::accept()` is the one place values are checked, and every notifier implements `EntryNotifier`: the entry is
saved first, so a notifier that fails loses nothing.

## Status

| Part | State |
|---|---|
| `Form`, `Entry`, `Field`, `FieldType` | Implemented and tested |
| Conditions (`ConditionEvaluator`, `BasicConditions`), tiers, disqualifying choices, the birth date and its ages | Implemented and tested |
| Richer conditions through `phil/form-logic`'s adapter `JsonLogicConditions` | Implemented and tested, in that library |
| Places in choices (`ChoicePlaces`), other notifications under conditions (`Notification`) | Implemented and tested |
| Sensitive fields sealed through Symfony's KeyManagement component (`FieldSealer`, `KeyManagementFieldSealer`, `SealedValues`), refused without a sealer (`UnsealedSensitiveField`, `allow_unsealed_sensitive`) | Implemented and tested |
| Conditions applied in the browser by `phil/form-logic`'s Stimulus controller (`LogicSteps`), when that library is installed | Implemented and tested |
| `Submissions`, `Retention`, `EntryNotifier` | Implemented and tested |
| `EntryType`, `FormController` and `config/routes.php` | Implemented and tested |
| `EmailNotifier`, `WebhookNotifier` | Implemented and tested |
| `DoctrineFormRepository`, `phil:forms:purge` | Implemented and tested |
| Flex recipe: the bundle, its configuration, its routes | Implemented and tested |
| Templates and translations, English and French | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the recipe, registering, routes, tables, configuration. |
| [Forms](doc/forms.md) | Defining a form, its fields, what each accepts, conditions, tiers, places, sensitive fields. |
| [Entries and retention](doc/entries.md) | What is kept, for how long, and deleting it. |
| [Notifications](doc/notifications.md) | Email, the webhook, and notifiers of the application's own. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

```php
$form = Form::create('registration', 'Tournament registration', [
    Field::of('name', 'Name', FieldType::Text, required: true),
    Field::of('email', 'Email', FieldType::Email, required: true),
    Field::of('team', 'Team', FieldType::Choice, choices: ['Blue', 'Green'], capacities: ['Blue' => 20, 'Green' => 20]),
    Field::of('captain', 'I will captain my team', FieldType::Checkbox, shownWhen: ['in' => [['var' => 'team'], ['Blue', 'Green']]]),
    Field::of('consent', 'I accept the rules', FieldType::Checkbox, required: true),
], retentionDays: 90);
$form->notify(['games@example.com'], 'https://hooks.example.com/forms');
$form->notifyAlso([Notification::to(['blue@example.com'], 'Blue: {name}', ['==' => [['var' => 'team'], 'Blue']])]);
$repository->save($form);
// The page: /forms/registration
```

## Requirements

PHP 8.5 or later with `ext-filter` and `ext-mbstring`; Doctrine ORM 3.7; `phil/spam-protection-bundle` 0.1 and
`phil/standard-webhooks` 0.1; Symfony 8.2's form, HTTP foundation and kernel, UID and dependency injection;
PSR-20's clock; Twig 3. Symfony Mailer, HTTP client, Console and Routing are optional, as are
`phil/privacy-bundle` and Symfony Security's core; `symfony/key-management` seals the values of sensitive fields,
and a form with one is refused without a sealer; `phil/form-logic` applies the conditions in the browser and reads
richer ones.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
