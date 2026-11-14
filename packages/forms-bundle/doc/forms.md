# Forms

## Defining a form

```php
$form = Form::create('registration', 'Tournament registration', $fields, retentionDays: 90);
$form->rename('Registration');
$form->define($fields);        // the fields, replaced
$form->keepFor(30);            // 1 to 3650 days
$form->identifyBy('email');    // the email field that names the person an entry is about; null for none
$form->close();                // its page is a 404, it takes no entry
$form->open();
$repository->save($form);
```

A key is 1 to 64 lower-case letters, digits or `-`, and is unique: it is the form's address, `/forms/{key}`. A form
has at least one field, each name once.

## Fields

```php
Field::of('message', 'Your message', FieldType::Textarea, required: true, maxLength: 2000);
Field::of('team', 'Team', FieldType::Choice, choices: ['Blue', 'Green']);
Field::of('born', 'Date of birth', FieldType::BirthDate, required: true);
```

| Type | Accepts | Longest by default |
|---|---|---|
| `Text` | One line | 255 |
| `Textarea` | Several lines | 5000 |
| `Email` | An email address | 255 |
| `Url` | An `http` or `https` URL | 255 |
| `Date` | A date, `YYYY-MM-DD`, that exists | — |
| `BirthDate` | A date that exists, not after the day the entry comes nor more than 130 years before it; with ages, a person within them | — |
| `Choice` | One of its choices | — |
| `Checkbox` | Ticked or not; required means it must be ticked, for a consent | — |

A name is 1 to 64 lower-case letters, digits or `_`, starting with a letter; a label 1 to 255 characters. A length,
1 to 10000, is for text fields only; choices, 1 to 500 of 1 to 255 characters each given once, for choice fields only.

The bundle keeps to these types on purpose: numbers, scales, ratings, file uploads, addresses and other advanced
fields belong to a survey bundle built over it, not to this one.

## Ages of a birth date

A birth date may take only some ages, counted in full years:

```php
Field::of('born', 'Date of birth', FieldType::BirthDate, required: true, minAge: 18);
Field::of('born', 'Date of birth', FieldType::BirthDate, minAge: 8, maxAge: 12, referenceDate: '2027-07-01');
```

| Option | Key in the definition | Takes |
|---|---|---|
| `minAge` | `min_age` | The youngest age accepted, 0 to 130 |
| `maxAge` | `max_age` | The oldest age accepted, 0 to 130, not under `minAge` |
| `referenceDate` | `reference_date` | The day the age is counted on, `YYYY-MM-DD`, with `minAge` or `maxAge` only; none: the day the entry comes |

Both bounds are inclusive. With `minAge: 18`, a person whose eighteenth birthday is the reference day is accepted, and
one whose birthday is the next day is refused with `too_young`. With `maxAge: 12`, a person of 12 is accepted up to
the day before their thirteenth birthday; on that day they are refused with `too_old`. A year is complete on the
birthday; a person born on 29 February completes it on 1 March in a year without 29 February. `minAge` and `maxAge`
may be equal, for one age only, and either may stand alone.

The day the entry comes is read from the clock `Submissions` receives — the container's `clock` service, from
`symfony/clock` —, so a test sets it with a mock clock. A reference date fits an event: the ages of the day of a camp,
whatever day the entry comes. The refusal messages, `phil_forms.error.too_young` and `phil_forms.error.too_old`, take
the age as `%age%`. A birth date is never after the day the entry comes, whatever the reference date.

Only a birth date has ages; an age out of 0 to 130, `minAge` above `maxAge`, a reference date without an age, or one
that is not a date, is refused with `InvalidForm`.

## Questions asked under a condition

A field may be asked only when a condition on the fields **before it** holds:

```php
Field::of('team', 'Team', FieldType::Choice, choices: ['Blue', 'Green']),
Field::of('captain', 'Captain of Blue?', FieldType::Checkbox, shownWhen: ['==' => [['var' => 'team'], 'Blue']]),
Field::of('armband', 'Armband size', FieldType::Text, required: true, shownWhen: ['==' => [['var' => 'captain'], true]]),
```

A condition is data whose JSON is a subset of JsonLogic. `BasicConditions`, the bundle's evaluator, reads three
shapes, each on one field:

| Condition | Holds when the field |
|---|---|
| `{"==": [{"var": "team"}, "Blue"]}` | has this value; `true` or `false` for a checkbox |
| `{"!=": [{"var": "team"}, "Blue"]}` | has another value, or none: left empty |
| `{"in": [{"var": "team"}, ["Blue", "Green"]]}` | has one of 1 to 100 values; not for a checkbox |

`Form::define()` checks each condition against the types of the fields before it: a field that comes later, a value
a choice field never takes, or a checkbox compared with a text is refused. When an entry comes, a field is asked
when its condition holds and every field it reads was asked itself, so that hiding a question hides the questions
that follow from it. **A field not asked keeps no value**, whatever the visitor sent, and is not required. Values are
compared strictly. A field left empty has no value: as in JsonLogic, it satisfies `!=` — no value is not `"Blue"` —,
and neither `==` nor `in`; a field not asked satisfies no condition at all, not even `!=`.

An application with richer logic aliases `ConditionEvaluator` to another evaluator
([extension points](extension-points.md#conditionevaluator)).

### In the browser

Without `phil/form-logic`, the page renders every field and lets the definition decide; the question's label says
when to answer it.

With `phil/form-logic` installed, the conditions also apply in the browser, as the form is filled. `EntryType` then
names the row of each field asked under a condition in `data-form-logic-field`, and `_form.html.twig` writes the
library's Stimulus controller on the form, its steps drawn from the conditions of the fields the page shows:

```html
<form name="phil_form" method="post" class="phil-form" novalidate="novalidate"
      data-controller="phil--form-logic--logic"
      data-phil--form-logic--logic-steps-value="[{&quot;field&quot;:&quot;captain&quot;,…}]">
    …
    <div data-form-logic-field="captain">…</div>
</form>
```

The controller hides the row of a field whose condition does not hold and disables its controls, so the browser
does not send them, and shows it again with its value when the condition holds. It judges as `Form::accept()` does:
a field is shown when its condition holds and every field it reads is shown, so a field left empty and a field not
asked do not read alike; a condition that reads a field the page does not show — not for the entry's tiers — hides
its field. The server judges the entry again, whatever the browser sent.

The controller is served with AssetMapper and StimulusBundle, as `phil/form-logic` describes in its
`doc/installation.md`, and the page loads the import map: an application's own page includes `_form.html.twig` in a
layout that calls `importmap()`; the bundle's bare page, `form.html.twig`, loads none. A page that renders the form
by other means writes the two attributes of the form from `form.vars.phil_form_logic_steps`, null when no field shown
has a condition; the rows carry theirs through the option `row_attr`. A form without conditions is rendered as
without the library.

### Richer conditions with phil/form-logic

`phil/form-logic` reads the conditions as its whole subset of JsonLogic — `and`, `or`, `!`, orderings and intervals,
`missing`, arithmetic —, through its adapter `Phil\FormLogic\FormsBundle\JsonLogicConditions`:

```yaml
# config/services.yaml
services:
    Phil\FormLogic\FormsBundle\JsonLogicConditions: ~
    Phil\FormsBundle\Domain\Contract\ConditionEvaluator: '@Phil\FormLogic\FormsBundle\JsonLogicConditions'
```

```php
Field::of('armband', 'Armband size', FieldType::Text, shownWhen: ['and' => [
    ['==' => [['var' => 'team'], 'Blue']],
    ['!' => [['var' => 'captain']]],
]]),
```

`Form::define()` then checks each condition with the library's type checker: a choice is typed by its choices, a
checkbox as a boolean, a date or a birth date as a day, any other field as a text. The bundle's own rules stay: a
condition reads only the fields before it, and one that reads a field not asked does not hold.

The three shapes `BasicConditions` reads mean the same under `JsonLogicConditions`, a field left empty included:
`!=` holds for it under both. JsonLogic's `==` converts types as JavaScript does, but the type checker refuses
comparing a field with a value it never takes — a text with a number, a checkbox with a text —, so a condition it
accepts compares like for like. A condition that needs a field answered says so, as in
`{"and": [{"!!": [{"var": "team"}]}, {"!=": [{"var": "team"}, "Blue"]}]}`.

The library also has computed variables; the bundle stores none. Its controller applies the conditions in the browser,
whichever evaluator reads them on the server ([In the browser](#in-the-browser)).

## Tiers

A field may be asked only of entries for some tiers — a kind of ticket, a membership, a role —, each 1 to 64
lower-case letters, digits, `_` or `-`:

```php
Field::of('meal', 'Meal', FieldType::Choice, required: true, choices: ['Vegan', 'Other'], tiers: ['vip']),
```

A field without tiers is asked of every entry. The application says which tiers an entry is for:
`Submissions::submit($form, $values, tiers: ['vip'])`, and `EntryType`'s option `tiers` builds the form of those
fields. `Form::fieldsFor($tiers)` lists them. The page `/forms/{key}` is for no tier: it asks only the fields without
tiers. An application that takes several people in one order submits one entry per person, each with their tiers.

## Choices that disqualify

A choice may refuse the entry with a message of 1 to 500 characters, shown on the field:

```php
Field::of('age', 'Age', FieldType::Choice, choices: ['Under 18', '18 or over'], disqualifying: ['Under 18' => 'The event is for adults.']),
```

The entry is refused with `disqualified` on that field, and nothing is kept. `Field::disqualification($value)` gives
the message.

## Places in a choice

A choice may hold a number of places, 1 to 1000000:

```php
Field::of('workshop', 'Workshop', FieldType::Choice, choices: ['Painting', 'Dice'], capacities: ['Painting' => 12]),
```

Each entry that keeps the choice takes a place; once the places are taken, the entry is refused with `full`, and the
page offers the choice disabled. The count lives in the table `phil_form_choice_count`, raised in one statement under
the capacity, so that two entries sent together never take the last place both. An entry refused for another reason,
or that cannot be kept, gives back the places it took; so does an entry removed — erased, or purged at the end of its
retention. Raising a capacity offers places again at once.

## Sensitive fields

A field marked `sensitive` is never sent by email or webhook, and is kept sealed by the configured sealer — by
default through Symfony's KeyManagement component, under the master key `sealing.key` names
([entries](entries.md#sensitive-values)):

```php
Field::of('health', 'Anything we should know about your health?', FieldType::Textarea, sensitive: true),
```

A checkbox, and a choice with places, cannot be sensitive. A definition keeps only what a field uses: a field without
a condition, tiers, places or ages stores none of those keys.

Without a sealer, a form with a sensitive field is refused: `EntryType` does not build its page and `Form::submit()`
takes no entry, both throwing `UnsealedSensitiveField`, whose message says what to configure. Only
`allow_unsealed_sensitive: true` lifts the refusal ([entries](entries.md#keeping-sensitive-values-as-sent)).

## What is accepted

`Form::accept()` is the one place values are checked: they are trimmed, an empty text is none, and every field
refused is said at once in `InvalidEntry::$errors` — `required`, `too_long`, `email`, `url`, `date`, `birth_date`,
`too_young`, `too_old`, `choice`, `disqualified`, `type` —, or `form` → `closed`. `Submissions` adds `full` for a choice whose places are
taken. Values for fields the form does not have, or does not ask, are dropped.

## The page

`FormController` builds the Symfony form from the definition, `EntryType`, protected against spam with its text
fields inspected and the scope `form-{key}`. An entry taken redirects to `?sent=1`; values refused come back with a
message on each field — the form's own message for a disqualifying choice —, `422`. The choices whose places are all
taken are offered disabled. The page is `@PhilForms/form.html.twig`; an application's own extends its layout and
includes `@PhilForms/_form.html.twig`, which receives `form`, `definition` and `sent`.
