# Translation

An enum becomes translatable by implementing Symfony's `TranslatableInterface` with `AsTranslatableEnumTrait`.
Its cases can then be handed to anything that translates a `TranslatableInterface`: Twig's `|trans` filter, a form
choice label, a translated message.

```php
use Phil\Enums\Attribute\Description;
use Phil\Enums\Attribute\Label;
use Phil\Enums\Attribute\Meta;
use Phil\Enums\Traits\AsMetadatableEnumTrait;
use Phil\EnumsBundle\Infrastructure\Translation\AsTranslatableEnumTrait;
use Symfony\Contracts\Translation\TranslatableInterface;

#[Meta(Label::class, Description::class)]
enum OrderStatus: string implements TranslatableInterface
{
    use AsMetadatableEnumTrait;
    use AsTranslatableEnumTrait;

    #[Label('In transit')] #[Description('The parcel has left the warehouse.')]
    case IN_TRANSIT = 'in_transit';

    case REFUNDED = 'refunded';
}
```

```twig
{{ status|trans }}                              {# the translated label #}
{{ status.translatableDescription|trans }}      {# the translated description #}
```

## The keys

- **The label key is the case's `Label`**: the source text is the key, so a missing translation shows the label as
  written, and a translation file reads as the labels it translates:

  ```yaml
  # translations/enums.fr.yaml
  'In transit': 'En transit'
  'The parcel has left the warehouse.': "Le colis a quitté l'entrepôt."
  ```

- **Without a label, the key is generated**: `<prefix>.<enum>.<case>`, the short class name and the case name in
  snake case — `enum.order_status.refunded`. An empty label counts as none.
- **The description key is the case's `Description`.** A case without one, or with an empty one, has no
  description: `translatableDescription()` returns `null`.

## The domain and the prefix

The domain is `enums` and the prefix `enum`, unless the enum says otherwise:

```php
use Phil\EnumsBundle\Application\Translation\Translatable;

#[Translatable(domain: 'shop', prefix: 'order')]
enum OrderStatus: string implements TranslatableInterface { … }
```

## What the trait adds

| Method | Returns |
|---|---|
| `trans(TranslatorInterface $translator, ?string $locale = null)` | The translated label, in the domain of the enum and the locale given, or the translator's. |
| `translatableDescription()` | A `TranslatableText` of the description key and domain, or `null` without a description. |

`EnumTranslation` computes the keys and the domain — `labelKey()`, `descriptionKey()`, `domain()` — and
`plainLabel()`, the label or the name, which is shown where nothing translates. `TranslatableText` is a key in a
domain, translated when rendered.

An enum that must stay free of any Symfony dependency — in a domain layer that admits none — does not use the
trait; Twig's `enum_label` and `enum_description` filters then show its `Label` and `Description` as written.

## Extracting the keys

With `symfony/translation` installed, the bundle gives `translation:extract` the keys of the translatable enums it
finds in the paths the command reads — `src/` and `templates/` by default:

```sh
bin/console translation:extract fr --force --format=yaml
```

Each case adds its label key and, when it has one, its description key, in its enum's domain; the message of a new
key is the key with the command's prefix, as Symfony's own extractors write it. Without the extractor, the keys an
enum translates by its metadata appear in no catalogue, and `debug:translation` cannot say they are missing.
`debug:enums --locale=fr` checks one enum's keys directly ([Commands](commands.md)).

