# Declaring personal data

**State: implemented and tested.** Pure PHP, no container, no I/O.

## The attribute

`Phil\PrivacyBundle\Domain\Attribute\PersonalData` marks a property as personal information. It targets
properties only (`Attribute::TARGET_PROPERTY`), promoted constructor properties included.

```php
use Phil\PrivacyBundle\Domain\Attribute\PersonalData;

final class Member
{
    public function __construct(
        #[PersonalData('email', retention: 'R-002')]
        public string $email,
        #[PersonalData('person_name')]   // no retention rule cited yet
        public string $displayName,
    ) {
    }
}
```

| Argument | Type | Rule |
|---|---|---|
| `category` | `string` | Becomes a `DataCategory`: a lowercase `snake_case` token (`^[a-z][a-z0-9_]*$`) of at most 64 characters. `'E-mail'` or `'email address'` is refused. |
| `retention` | `?string`, default `null` | The identifier of a rule in your retention schedule. If given, it must match `RetentionRule::ID_PATTERN`: a letter or digit, then letters, digits, `.`, `_` or `-`, at most 64 characters (`R-002`, `RET-042`, `users.email`). |

Public properties after construction: `$category` (`DataCategory`) and `$retention` (`?string`). A malformed
argument throws `InvalidPrivacyValue` when the attribute is instantiated — that is, when something reads it
with `ReflectionAttribute::newInstance()`, not when the class is loaded.

### Why an attribute, and why so little

- **Declared where the field lives.** The declaration moves with the property; an inventory kept in a
  separate file drifts from the model.
- **Metadata and nothing else**, so a domain model can carry it the same way it carries mapping attributes.
  What reads it — an inventory test, an export, a scrubber — lives outside the model.
- **The vocabulary is yours.** The bundle checks the shape of a category, not a list: a free-text
  classification cannot be grouped, counted or matched against a retention rule.
- **No rule is guessed.** A field declared without `retention` is reported as uncovered, not assigned a default.

## Reading declarations

`Phil\PrivacyBundle\Domain\Declaration\DeclaredPersonalFields` has two static methods.

### `of(class-string $class): list<PersonalField>`

Every declared field of the class **and its parents**. Parents are walked explicitly because reflection does
not return a parent's private properties from the child, and a field declared privately on a base model is
still a personal field of every subclass.

Order is stable: parents first, then declaration order within each class. Each property is reported once, by
the class that declares it.

### `uncoveredIn(class-string $class, ?RetentionSchedule $schedule = null): list<PersonalField>`

The fields no retention rule covers:

- without a schedule, the fields that cite no rule;
- with a schedule, also the fields whose cited rule the schedule does not define — a misspelt or removed
  identifier covers nothing.

## `PersonalField`

A read-only value: `$class` (the declaring class), `$property`, `$category` (`DataCategory`) and
`$retention` (`?string`).

| Method | Returns `true` when |
|---|---|
| `isCoveredByRetention(): bool` | the field cites a rule, whether or not it exists |
| `isCoveredBy(RetentionSchedule $schedule): bool` | the field cites a rule that the schedule defines |

## The inventory test

The intended use is a test in the application that fails when a personal field has no retention rule:

```php
use Phil\PrivacyBundle\Domain\Declaration\DeclaredPersonalFields;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PersonalDataInventoryTest extends TestCase
{
    /** @return iterable<array{class-string}> */
    public static function models(): iterable
    {
        yield [Member::class];
        yield [Order::class];
    }

    #[DataProvider('models')]
    public function testEveryPersonalFieldHasARetentionRule(string $class): void
    {
        $schedule = (new MyScheduleSource())->load(); // your RetentionScheduleSource

        $uncovered = array_map(
            static fn ($field): string => $field->class.'::$'.$field->property,
            DeclaredPersonalFields::uncoveredIn($class, $schedule),
        );

        self::assertSame([], $uncovered);
    }
}
```

The bundle does not discover your models: which classes the test walks is the application's list.
