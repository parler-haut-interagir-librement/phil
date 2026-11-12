<!-- phil-dev source-sha256=780388f0514e79ae76864435a53e734e5edb82e30a4772ee3303363b1cb890e5 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Phil\Publishing\PagePublished` | Une page est passée en ligne, ou l'est de nouveau avec un nouveau contenu : adresse canonique, langue, indicateur d'indexation, identifiant du propriétaire |
| `Phil\Publishing\PageRetired` | Une page est passée hors ligne, retirée ou supprimée : les mêmes quatre champs, `indexable` tel qu'il était pendant qu'elle était en ligne |
| `Phil\Publishing\Contract\PageEvent` | Les quatre champs en lecture seule que portent les deux événements, pour un écouteur qui les traite de la même façon |
| `Phil\Publishing\Exception\InvalidPageEvent` | Une langue qui n'est pas une étiquette BCP 47, ou un identifiant de propriétaire mal formé |
| `Phil\Publishing\PageEventFields` | Interne : les contrôles de la langue et de l'identifiant du propriétaire |
