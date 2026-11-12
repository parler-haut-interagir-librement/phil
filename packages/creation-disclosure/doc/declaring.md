# Declaring

## Parts

A work is made of parts whose making is declared on its own: `Part::Text`, `Part::Image`, `Part::Audio`,
`Part::Video` and `Part::Code`. A book has a text and images; a game, images, audio and code; a photo, one image.

`CreationDisclosure::of()` starts a work with its parts, none of them declared:

```php
$work = CreationDisclosure::of(Part::Text, Part::Image);

$work->parts();      // [Part::Text, Part::Image]
$work->undeclared(); // [Part::Text, Part::Image]
```

The parts are always listed in the order of `Part`, whatever order they were given in, and each appears once.

## Declarations

`declare()` gives a part its digital source type and returns a new disclosure; the disclosure itself never changes.
Declaring a part the work did not have adds it. `undeclare()` sets a part back to undeclared.

```php
$work = $work
    ->declare(Part::Text, DigitalSourceType::HumanEdits)
    ->declare(Part::Image, DigitalSourceType::CompositeWithTrainedAlgorithmicMedia);

$work->sourceOf(Part::Image); // DigitalSourceType::CompositeWithTrainedAlgorithmicMedia
$work->sourceOf(Part::Code);  // null: not a part of the work
$work->has(Part::Code);       // false
```

A retired type, such as `digitalArt`, is declared as the type that replaces it, `digitalCreation`. The
[vocabulary](vocabulary.md) lists the types.

## The views

Each type says what it owes to generative AI, a `GenerativeAi`:

| View | Means |
|---|---|
| `None` | Nothing was generated: captured, digitised, made or edited by people, or computed by plain algorithms. |
| `Undeclared` | Nothing says how it was made. |
| `Possible` | It may hold generated elements: altered by algorithms, or composed of elements of unstated origin. |
| `Partial` | Some of it was generated: edited with generative AI, or composed with generated elements. |
| `Full` | All of it was created with generative AI. |

`generativeAiOf()` reads the view of one part; an undeclared part, or one the work does not have, is `Undeclared`.
`generativeAi()` combines the parts into the view of the whole work:

- `Full` when every part is `Full`;
- otherwise `Partial` as soon as one part is `Full` or `Partial`;
- otherwise `Possible` as soon as one part is;
- otherwise `Undeclared` as soon as one part is;
- otherwise `None`.

A work without parts is `Undeclared`. `GenerativeAi::combine()` does the same over any list of views, for a collection
or a bundle of works.

### The AI-free view

`isFreeOfGenerativeAi()` is `true` when the whole work is `None`: every part declared, and none generated, in part or
possibly. An undeclared part keeps a work out of it, so that a filter "AI-free" never shows a work nobody described.

```php
$work->isFreeOfGenerativeAi(); // false: the images were edited with generative AI
```

## Storing a disclosure

`toArray()` gives each part's code, `null` when undeclared, ready for a column of type `json` or an API:

```php
$work->toArray(); // ['text' => 'humanEdits', 'image' => 'compositeWithTrainedAlgorithmicMedia']
```

`fromArray()` reads it back. A value may also be the address of a type, as XMP carries it. A key that is not a part, a
value that is neither a code, an address nor `null`, throws `Exception\InvalidDisclosure`; a stored retired code is
read as it is, so that an old disclosure keeps saying what it said.

## Metadata

`DigitalSourceType::uri()` is the address of a type, `http://cv.iptc.org/newscodes/digitalsourcetype/digitalCapture`,
the value of the XMP property `DigitalSourceType::XMP_PROPERTY`, `Iptc4xmpExt:DigitalSourceType`, and of a C2PA
assertion. `fromUri()` reads an address over `http` or `https`, with or without a trailing slash.
