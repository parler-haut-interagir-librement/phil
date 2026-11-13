<!-- phil-dev source-sha256=9417f98056037243536d99ac6e687d05786d5d9b0d68b0acc9d370f0712c11ee -->
# Notes et évaluations

## Rédiger une note

```php
$note = $notes->write(ResourceReference::of('post', '42'), $member, 'The photo was taken in 2019, not this week.');
```

Le sujet est n'importe quelle ressource de l'application, nommée par une référence de `phil/resource`. La qualification
est consultée d'abord : un membre qu'elle refuse reçoit `NoteRefused`. Le texte est un texte UTF-8 de 1 à 2000
caractères, qui n'est pas fait que d'espaces ; tout autre texte lève `InvalidNote`. Une nouvelle note a besoin
d'évaluations : son état est `NeedsMoreRatings` et elle n'a pas de décision.

## Évaluer une note

```php
$note = $notes->rate($note->id, $rater, Verdict::Helpful);   // or Verdict::NotHelpful
```

Un évaluateur a une évaluation par note : évaluer de nouveau remplace la précédente, et c'est l'heure de la nouvelle qui
compte. Après chaque évaluation, la [règle d'accord croisé](cross-agreement-rule.md) décide de nouveau de la note, et la
note est conservée avec son nouvel état et la décision qui le lui a donné. `NoteRefused` arrête une évaluation quand la
note est retirée, quand l'évaluateur l'a rédigée, ou quand la qualification le refuse.

## Retirer une note

Seul son auteur retire une note. Une note retirée garde son texte et sa dernière décision, n'est plus affichée ni
évaluée, et reste retirée quand on décide d'elle de nouveau.

## Ce qui est affiché, et à qui

| État | Affichée à |
|---|---|
| `NeedsMoreRatings` | Les évaluateurs : `notesOf($subject)` énumère chaque note d'une ressource. |
| `Helpful` | Les lecteurs de la ressource : `shownOf($subject)` énumère celles-là seulement, les plus anciennes d'abord. |
| `NotHelpful` | Les évaluateurs. |
| `Withdrawn` | Personne sauf l'auteur et les modérateurs que l'application laisse la voir. |

Une note passe de `NeedsMoreRatings` à `Helpful` ou `NotHelpful` au fil des évaluations ; seules les notes `isShown()`
atteignent les lecteurs. Une page qui dit à un lecteur pourquoi une note est affichée utilise
`$note->decision?->toPublicArray()`, qui omet les noms des évaluateurs ; `toArray()`, avec les noms, est pour les
modérateurs.

## Décider de nouveau

`reconsider($note)` décide de nouveau d'une note d'après ses évaluations : après un changement de la règle, après un
changement de l'historique de ses évaluateurs, ou pour une note décidée selon une autre règle. Le message
`ReconsiderNote` le fait en arrière-plan : voir [Événements et messages](events.md).

## Lire les notes

`get($id)` lève `NoteNotFound` pour une note que le stockage n'a pas ; `find($id)` donne `null`.
