<!-- phil-dev source-sha256=2edbded96f14567ee7c6766d47d4efa1647878ce976fdebe5382c4b709abdfb9 -->
# Points d'extension

La bibliothèque est en PHP simple et ne déclare aucun service. Son seul port est le générateur de slugs, et on le
change par décoration.

## `Slugger`

`Phil\Slug\Slugger` — transforme un texte en `Slug`. Voyez [Référence](reference.md#slugger).

| Méthode | Contrat |
|---|---|
| `slug(string $text): Slug` | Un slug valide, quel que soit le texte ; `InvalidSlug` quand le texte ne contient aucune lettre ni aucun chiffre dont on puisse faire un slug |

| Point | Comment |
|---|---|
| Translittération et longueur | `new TransliteratingSlugger($locale, $maxLength)` |
| Changer ce que donne un générateur | Un décorateur : une classe qui implémente `Slugger`, reçoit le générateur qu'elle enveloppe, l'appelle, et change le texte avant ou le slug après ; `LeadingWordsRemoved` en est un |
| Un générateur par usage | `Sluggers`, un générateur par nom et un par défaut : les titres, les noms de fichiers, les entrées de répertoire ont chacun le leur |

`Slug::fromString()` vérifie et ne corrige jamais : un décorateur retourne un slug construit par le générateur qu'il
enveloppe, ou un slug qui suit déjà les règles.
