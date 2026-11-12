# Messages

A finding carries a `Message`: a key, such as `link-text.generic`, and the values of its placeholders, such as
`['href' => '/booking', 'text' => 'here']`. `symfony/translation` writes it in a language, in the domain
`phil_accessibility_check`.

```php
use Phil\AccessibilityCheck\Translation\Messages;

$report->message($finding);                       // in the report's locale, the policy's `locale`
$report->inLocale('de')->message($finding);       // in German
Messages::translate(Messages::translator(), $finding->message, 'pt-BR');
```

## Languages

`Translation\Messages::translator()` gives a `Translator` that speaks English (`en`), French (`fr`), Spanish (`es`),
German (`de`), Italian (`it`) and Portuguese (`pt`), English the fallback: `Translation\English`, `French`, `Spanish`,
`German`, `Italian` and `Portuguese` hold their messages, which `Messages::resources()` gives by locale. A locale is read
as a language tag, with `-` or `_`, in any case: `fr-ca` is read as `fr_CA`, which takes the messages of `fr_CA`, then
of `fr`, then the English ones. What is not a language tag, such as the locale of a report read back from a file, is
read as English. A key no locale knows is written as it is.

## Keys

The keys of the rules are listed with each rule in [Rules](rules.md). `report.blocked`, with `count` and `message`, is
the message the publication guard blocks with. A placeholder is written `{name}` in a message.

## Adding or replacing messages

```php
use Phil\AccessibilityCheck\AccessibilityChecker;
use Phil\AccessibilityCheck\Translation\Messages;

$translator = Messages::translator();
$translator->addResource('array', ['image-alt.missing' => 'De afbeelding {source} heeft geen tekstalternatief.'], 'nl', Messages::DOMAIN);
$translator->addResource('array', ['link-text.generic' => 'Le lien vers {href} dit seulement « {text} ».'], 'fr_CA', Messages::DOMAIN);
$translator->addResource('array', ['my-rule.found' => 'Found {what}.'], 'en', Messages::DOMAIN);

$checker = new AccessibilityChecker(translator: $translator);
```

A resource added later replaces the messages of the same keys in its locale. An application gives the checker its own
translator instead, the `translator` service of Symfony with `Messages::resources()` loaded in the domain
`phil_accessibility_check`, or translates `messageKey` with `parameters`, which the JSON of the report carries.
