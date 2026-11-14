# Settings

## Declaring a setting

A setting has a key of dotted lowercase words, a type, a default, rules its values keep, a scope, a section and the
translation keys of its label and help. The application declares its own in the configuration, or with attributes on
a class that holds nothing else, which the container then autoconfigures:

```php
use Phil\SettingsBundle\Domain\Attribute\AsSetting;
use Phil\SettingsBundle\Domain\Attribute\AsSettingSection;
use Phil\SettingsBundle\Domain\Setting\Scope;
use Phil\SettingsBundle\Domain\Setting\SettingType;

#[AsSettingSection('author', before: ['notifications'])]
#[AsSetting('author.signature', SettingType::Text, section: 'author', maxLength: 120)]
#[AsSetting('author.columns', SettingType::Integer, 2, section: 'author', min: 1, max: 4)]
#[AsSetting('site.registrations_open', SettingType::Boolean, true, Scope::Site, 'general')]
final class AuthorSettings
{
}
```

A package declares its settings by prepending the configuration of `phil_settings` in its own bundle:

```php
public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
{
    $builder->prependExtensionConfig('phil_settings', ['settings' => [
        'gallery.columns' => ['type' => 'integer', 'default' => 3, 'min' => 1, 'max' => 6, 'translation_domain' => 'gallery'],
    ]]);
}
```

## Types and rules

| Type | Value | Rules | Field |
|---|---|---|---|
| `boolean` | `true` or `false` | | A checkbox |
| `integer` | an integer | `min`, `max` | A number field |
| `text` | one line of UTF-8 text | `max_length` (255, up to 2 000), `pattern` | A text field |
| `choice` | one of `choices` | at most 100 choices of lowercase letters, digits, `_` and `-` | Radio buttons |
| `choices` | a list of `choices`, in their order, each once | the same | Checkboxes |

A text refuses control characters, a new line included. The default must keep the setting's own rules. A setting of
the site may name the `role` that changes it; a user's setting may be kept for visitors, `anonymous`, when it is a
boolean or a choice; a choice may name an `html_attribute`, see [Display preferences](display.md).

## Order

Sections and settings come in the order of their declaration — the bundle's sections first, then the configuration's,
then the attributes' —, unless they ask to come `before` or `after` others: such an item comes right before the first
that waits for it. A name that is not declared is passed over, so a package can place itself beside one that may not
be installed; a circle stops the container.

## Reading

`Settings` reads a value typed, and falls back on the default when nothing is stored, or when what is stored no longer
keeps the rules — a choice removed, a range narrowed:

```php
$settings->get('author.columns');           // the current user's, or a visitor's default
$settings->int('author.columns', 'u-42');   // a user's, by identifier
$settings->bool('site.registrations_open'); // the site's, the same for everybody
$settings->string('display.motion');        // a text or a choice
$settings->list('author.genres');           // a list of choices
```

A typed method refuses a setting of another type with `InvalidSetting`, an unknown key with `UnknownSetting`. A visitor
has the defaults, and the value of their cookie for a setting kept for visitors. In Twig:

```twig
{% if phil_setting('site.registrations_open') %}…{% endif %}
```

## Writing

```php
use Phil\SettingsBundle\Domain\ValueObject\Subject;

$settings->save(Subject::user('u-42'), ['author.columns' => 3, 'author.signature' => 'Ada']);
$settings->save(Subject::site(), ['site.registrations_open' => false]);
$settings->restoreDefaults(Subject::user('u-42'), ['author.columns']);
$settings->forget(Subject::user('u-42'));
```

Every value is checked before any is written: a refused value throws `InvalidSettingValue`, whose `reason` is a
translation key of the `phil_settings` domain, and nothing changes. Only what differs from the default is stored, so a
default changed in the code reaches everyone who never chose. `save()` and `restoreDefaults()` return the keys whose
value changed, `forget()` the keys erased.

## Events

Each change dispatches `Application\Event\SettingsChanged` on the event dispatcher: the scope (`user` or `site`), the
subject (the user's identifier or `site`) and the keys. It carries no value, so it can be forwarded to a message bus or
a log without what a person chose; a listener reads the new values through `Settings`.

```php
#[AsEventListener]
public function __invoke(SettingsChanged $event): void
{
    if ('user' === $event->scope && \in_array('author.signature', $event->keys, true)) {
        $this->bus->dispatch(new RefreshSignature($event->subject));
    }
}
```
