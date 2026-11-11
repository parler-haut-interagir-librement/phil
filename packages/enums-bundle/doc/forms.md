# Forms

The bundle extends Symfony's `EnumType`. Without its options, a field offers every case, as Symfony's does; with
them, it offers a [selection](selection.md):

```php
use Symfony\Component\Form\Extension\Core\Type\EnumType;

$builder->add('status', EnumType::class, [
    'class' => OrderStatus::class,
    'group' => 'customer',
    'except' => [OrderStatus::CANCELLED],
]);
```

| Option | Type | Default | Keeps |
|---|---|---|---|
| `group` | string or list of strings | `[]` | The cases in at least one of these groups. |
| `only` | list of cases | `[]` | The cases among those given. |
| `except` | list of cases | `[]` | The cases other than those given. |
| `where_meta` | map of meta property class to value | `[]` | The cases whose meta properties have these values. |

The choices keep the enum's declaration order. `choices` itself still works, and accepts a `CaseCollection` —
`OrderStatus::inGroup('customer')` — as any iterable; when one of the options above is set, it replaces `choices`.

Three more options arrange the choices rather than select them:

| Option | Type | Default | Effect |
|---|---|---|---|
| `without_deprecated` | bool | `false` | Leaves out the cases marked `#[\Deprecated]`, after the selection. |
| `group_by_meta` | meta property class, or `null` | `null` | Puts the choices in `<optgroup>`s by the first value of that meta property; a case without it stays outside every group. |
| `preferred_group` | string or list of strings | `[]` | Offers the cases of these groups first, as `preferred_choices`; it replaces `preferred_choices` when set. |

```php
$builder->add('status', EnumType::class, [
    'class' => OrderStatus::class,
    'without_deprecated' => true,
    'group_by_meta' => Group::class,   // <optgroup label="customer">, <optgroup label="staff">
    'preferred_group' => 'common',
]);
```

**`without_deprecated` is off by default**, and should stay off on a form that edits a record: a record that holds a
deprecated case would show no choice selected, and saving it would lose the value. Turn it on where a record is
created. The group names are translated as group labels are, in the field's `choice_translation_domain`.

## Labels

The label of a choice is, in order:

1. the key, when the choices are given keyed by label;
2. the case itself, when the enum is translatable — Symfony translates it ([Translation](translation.md));
3. the case's `Label`;
4. the case's name.

## Sets: EnumSetType

A field whose data is an `EnumSet` of `phil/enums`: the cases as checkboxes, those of
the set checked.

```php
use Phil\EnumsBundle\Infrastructure\Form\EnumSetType;

$builder->add('permissions', EnumSetType::class, ['class' => Permission::class]);
```

It is an `EnumType` with `multiple` and `expanded` set, so every option above applies — `group`, `except`,
`without_deprecated`, labels —; `expanded: false` gives a multiple select. An empty submission is the empty set,
never `null`. Data that is not an `EnumSet` of the field's enum fails the form's transformation.

## Validating the same subset

A form only shows choices; a forged request may still submit another case. Validate the property with the
`EnumCase` constraint and the same criteria ([Validation](validation.md)), so that the server accepts exactly what
the form offers.
