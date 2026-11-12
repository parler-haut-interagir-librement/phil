<!-- phil-dev source-sha256=a431ac7045571fd429a0bfce12d126da698f0bf782d112f491134b3afdd0c97c -->
# Vocabulaire

`DigitalSourceType` tient chaque code du vocabulaire Digital Source Type de l'IPTC, tel que l'IPTC le publie à
`http://cv.iptc.org/newscodes/digitalsourcetype/`. Sa valeur est le code, `label()` le nom anglais que lui donne le
vocabulaire, et `generativeAi()` sa vue.

## Les types

| Cas | Code | Nom | IA générative |
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

`declarable()` les liste, dans cet ordre : les types qu'offre un formulaire.

`Possible` va aux deux types qui ne disent pas si l'IA générative a pris part : les médias modifiés par des
algorithmes, et un composé d'éléments d'origine non dite. Aucun n'est « sans IA » ; un site qui veut une réponse plus
ferme demande un type plus précis.

## Les codes retirés

L'IPTC a retiré trois codes. Ils restent des cas, pour que les métadonnées écrites avec eux se lisent encore, et
`isRetired()` le dit :

| Cas | Code | Nom | Remplacé par |
|---|---|---|---|
| `MinorHumanEdits` | `minorHumanEdits` | Original media with minor human edits | `HumanEdits` |
| `SoftwareImage` | `softwareImage` | Created by software | `DigitalCreation` |
| `DigitalArt` | `digitalArt` | Digital art | `DigitalCreation` |

`current()` donne le remplaçant, et le type lui-même pour un type courant ; `CreationDisclosure::declare()` déclare le
remplaçant. Les trois sont `None`.

## Lire un code

`fromCode()` lit un code tel que le vocabulaire l'écrit, casse comprise, et `fromUri()` une adresse ; tout le reste
lance `Exception\InvalidDisclosure`, dont le message montre au plus 80 caractères de ce qui a été donné. `tryFrom()`,
celui de l'énumération, rend `null` à la place.
