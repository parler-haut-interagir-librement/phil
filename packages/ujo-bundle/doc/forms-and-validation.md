# Forms and validation

## The field

`UjoType` is a text area holding the JSON of a document — the value a block editor's script writes into it before
the form is submitted. On submission the JSON is read and checked with the `Ujo` service:

- a valid document becomes the model: its canonical JSON, by default, or a `Phil\Ujo\Document` with
  `document: true`;
- an empty submission becomes `null`;
- an invalid document makes the field invalid, with the message `This value is not a valid UJO document: {{ error }}`,
  `{{ error }}` naming the path and the reason, as `The UJO document is invalid at blocks[0].data.level: 0 is below the minimum 1.`

```php
$builder->add('body', UjoType::class);                         // ?string, canonical JSON
$builder->add('body', UjoType::class, ['document' => true]);   // ?Document
```

| Option | Default | Effect |
|---|---|---|
| `document` | `false` | Whether the model is a `Document` rather than its canonical JSON |
| `invalid_message` | `This value is not a valid UJO document.` | The fallback message; the field's own message, with the reason, is used first |

The field renders as a `textarea`; the editor's script usually hides it and writes the JSON on submit.

## The constraint

`ValidUjo` checks a value — UJO JSON or a `Document` — on an entity or a data object, whatever filled it:

```php
use Phil\UjoBundle\Infrastructure\Validator\ValidUjo;

#[ValidUjo]
public ?string $body = null;

#[ValidUjo(types: ['paragraph', 'header', 'list', 'quote'])]
public ?string $comment = null;
```

| Option | Default | Effect |
|---|---|---|
| `types` | `null`, every registered type | The canonical types accepted; a block of another type, nested ones included, adds one violation per type |
| `message` | `This value is not a valid UJO document: {{ error }}` | When the document is not valid |
| `typeMessage` | `Blocks of type {{ type }} are not accepted here.` | When a type is not accepted |
| `level` | `Validation::Shape` | How much of the document is checked: its shape, for every save, or `Validation::Publication`, also what it needs to be published |

A draft is saved with its shape checked; publishing, scheduling and sending for review ask for more — a heading filled
in, a list with items. Two constraints in two validation groups say both:

```php
use Phil\Ujo\Validation;

#[ValidUjo]
#[ValidUjo(level: Validation::Publication, groups: ['publication'])]
public ?string $body = null;
```

```php
$violations = $validator->validate($page, groups: ['Default', 'publication']);   // before publishing
```

The form field checks the shape only, so that a draft can always be saved. What each level checks is in the
library's documentation, under validation levels.

`null` and the empty text are valid: add `NotBlank` to require a document. The violation codes are
`ValidUjo::INVALID_ERROR` and `ValidUjo::TYPE_ERROR`. The messages are translated in English and French, in the
`validators` domain.
