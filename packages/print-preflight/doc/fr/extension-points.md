<!-- phil-dev source-sha256=4c83c598dcae136af891c8facf2dd823e30a54655909c7f453f7e2e8f1a21bc3 -->
# Points d'extension

La bibliothèque est en PHP simple et ne déclare aucun service. Ce qu'une application ajoute, ce sont des
vérifications ; ce qu'elle décrit, c'est l'imprimeur et le document.

## `Check`

`Phil\PrintPreflight\Check\Check` — une famille de règles exécutée sur un document. Voyez
[Vérifications et constats](checks.md#ajouter-une-vérification).

| Méthode | Contrat |
|---|---|
| `check(Document $document, PrintProfile $profile): list<Finding>` | Les constats à leur propre gravité, chacun avec un code qui lui est propre — `app.spine` plutôt qu'une valeur intégrée de `Rule` ; ne lève jamais d'exception pour un document qui enfreint une règle |

Les gravités et les codes ignorés du profil s'appliquent à une vérification de l'application comme aux vérifications
intégrées.

## Les vérifications

| Point | Comment |
|---|---|
| Ajouter une vérification | `Preflight::withCheck()` |
| Remplacer les vérifications intégrées | `new Preflight($profile, [...])`, avec `Preflight::defaultChecks()` pour en garder certaines |
| Ce qu'exige un imprimeur | `PrintProfile` : formats rognés, fond perdu, marge de sécurité, nombres de pages, couverture d'encre, épaisseur de filet, surimpression et transparence, et la gravité de chaque règle ou son code ignoré — voyez [Profils d'imprimeur](profiles.md) |
| D'où vient le document | `Document`, construit par l'application à partir d'un document `phil/paged-media`, du rendu d'un navigateur, ou de toute mise en page qu'elle mesure — voyez [Le modèle de document](model.md) |
