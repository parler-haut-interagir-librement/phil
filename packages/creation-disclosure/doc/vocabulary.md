# Vocabulary

`DigitalSourceType` holds every code of the IPTC Digital Source Type vocabulary, as IPTC publishes it at
`http://cv.iptc.org/newscodes/digitalsourcetype/`. Its value is the code, `label()` the English name the vocabulary
gives it, and `generativeAi()` its view.

## The types

| Case | Code | Name | Generative AI |
|---|---|---|---|
| `DigitalCapture` | `digitalCapture` | Digital capture sampled from real life | `None` |
| `ComputationalCapture` | `computationalCapture` | Multi-frame computational capture sampled from real life | `None` |
| `NegativeFilm` | `negativeFilm` | Digitised from a transparent negative | `None` |
| `PositiveFilm` | `positiveFilm` | Digitised from a transparent positive | `None` |
| `Print` | `print` | Digitised from a non-transparent medium | `None` |
| `HumanEdits` | `humanEdits` | Human-edited media | `None` |
| `CompositeWithTrainedAlgorithmicMedia` | `compositeWithTrainedAlgorithmicMedia` | Edited using Generative AI | `Partial` |
| `AlgorithmicallyEnhanced` | `algorithmicallyEnhanced` | Algorithmically-altered media | `Possible` |
| `DigitalCreation` | `digitalCreation` | Digital creation | `None` |
| `DataDrivenMedia` | `dataDrivenMedia` | Data-driven media | `None` |
| `TrainedAlgorithmicMedia` | `trainedAlgorithmicMedia` | Created using Generative AI | `Full` |
| `AlgorithmicMedia` | `algorithmicMedia` | Pure algorithmic media | `None` |
| `ScreenCapture` | `screenCapture` | Screen capture | `None` |
| `VirtualRecording` | `virtualRecording` | Virtual event recording | `None` |
| `Composite` | `composite` | Composite of elements | `Possible` |
| `CompositeCapture` | `compositeCapture` | Composite of captured elements | `None` |
| `CompositeSynthetic` | `compositeSynthetic` | Composite including generative AI elements | `Partial` |

`declarable()` lists them, in this order: the types a form offers.

`Possible` is given to the two types that do not say whether generative AI took part: media altered by algorithms, and
a composite of elements of unstated origin. Neither is AI-free; a site that wants a firmer answer asks for a more
precise type.

## Retired codes

IPTC retired three codes. They stay cases, so that metadata written with them still reads, and `isRetired()` says so:

| Case | Code | Name | Replaced by |
|---|---|---|---|
| `MinorHumanEdits` | `minorHumanEdits` | Original media with minor human edits | `HumanEdits` |
| `SoftwareImage` | `softwareImage` | Created by software | `DigitalCreation` |
| `DigitalArt` | `digitalArt` | Digital art | `DigitalCreation` |

`current()` gives the replacement, and the type itself for a current one; `CreationDisclosure::declare()` declares the
replacement. All three are `None`.

## Reading a code

`fromCode()` reads a code as the vocabulary writes it, case included, and `fromUri()` an address; anything else throws
`Exception\InvalidDisclosure`, whose message shows at most 80 characters of what was given. `tryFrom()`, the enum's
own, returns `null` instead.
