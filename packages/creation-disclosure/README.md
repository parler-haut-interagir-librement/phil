# phil/creation-disclosure

**How a work was made, declared part by part in the IPTC Digital Source Type vocabulary: captured, made by people,
edited or created with generative AI, or undeclared, with the AI-free view and the requirement a contest or a shop
sets.**

phil/creation-disclosure records how a work was made — a book, a game module, an illustration, a song — one part at a
time: its text, its images, its audio, its video, its code. Each part is declared with a code of the IPTC Digital
Source Type vocabulary, the one XMP, C2PA and schema.org's `digitalSourceType` carry, or stays undeclared, which stays
visible and never passes for AI-free. From the declarations it reads one view, what the work owes to generative AI:
none, possibly, in part or in full. "AI-free" is that view at `None`, not a separate label.

It is for any PHP application that receives or sells works and lets its readers filter on how they were made: a
catalogue with an "AI-free" filter, a contest that refuses generated entries, a shop that asks its sellers to declare
their images. The application stores the disclosure in a JSON column and shows the view; the library holds the
vocabulary and the rules.

It is a plain PHP library whose only requirement is `ext-mbstring`.

The design: `DigitalSourceType` is the vocabulary, retired codes included; `Part` names the parts of a work;
`CreationDisclosure` holds a declaration per part, immutable; `GenerativeAi` is the view and its combination over the
parts; `DisclosureRequirement` is what a contest or a shop asks.

## Status

| Part | State |
|---|---|
| The IPTC Digital Source Type vocabulary, codes, addresses and labels, retired codes and their replacements | Implemented and tested |
| Declarations part by part, undeclared parts kept visible | Implemented and tested |
| The generative AI view of a part and of a whole work, the AI-free view | Implemented and tested |
| Requirements: parts to declare and a ceiling of generative AI | Implemented and tested |
| Storing and reading back as an array of codes | Implemented and tested |
| Reading and writing the XMP of a file, C2PA manifests | Not started: the library gives the property and the addresses |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Declaring](doc/declaring.md) | Parts, declarations, the views, storing a disclosure. |
| [Vocabulary](doc/vocabulary.md) | Every digital source type, what it says of generative AI, the retired codes. |
| [Requirements](doc/requirements.md) | What a contest, a shop or a catalogue asks, and how to check an entry. |
| [Extension points](doc/extension-points.md) | What an application may replace or add, and when it is stable. |
| [Testing](doc/testing.md) | The package's tests and its mutation bar. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/creation-disclosure
```

```php
use Phil\CreationDisclosure\CreationDisclosure;
use Phil\CreationDisclosure\DigitalSourceType;
use Phil\CreationDisclosure\GenerativeAi;
use Phil\CreationDisclosure\Part;

$book = CreationDisclosure::of(Part::Text, Part::Image)
    ->declare(Part::Text, DigitalSourceType::HumanEdits)
    ->declare(Part::Image, DigitalSourceType::TrainedAlgorithmicMedia);

$book->generativeAi();         // GenerativeAi::Partial
$book->isFreeOfGenerativeAi(); // false
$book->toArray();              // ['text' => 'humanEdits', 'image' => 'trainedAlgorithmicMedia']
```

## Requirements

PHP 8.5 or later, with `ext-mbstring`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
