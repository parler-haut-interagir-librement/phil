<!-- phil-dev source-sha256=24e71863f645619b558766dd8c1b8609e23f27d4925ec63d2a909e78fe1147ee -->
# Vie privée

Le texte d'un avis est un renseignement personnel, tout comme son auteur, les votes qu'un membre a donnés et les
réponses qu'il a écrites. Avec `phil/privacy-bundle` installé, `ReviewPrivacy` est son module `reviews`, la personne
concernée étant l'identifiant du membre ; une personne concernée qui ne peut pas être un identifiant de membre — une
adresse courriel — ne détient rien ici.

## L'export

L'export donne, pour le membre :

- `reviews` : chaque avis qu'il a écrit, quel que soit son état — sujet, notes, titre, texte, superlatifs,
  compensation, mention vérifiée, état, critère de retrait, moments, et la contestation d'un retrait ;
- `votes` : les avis sur lesquels il a voté, comment, et quand ;
- `responses` : les réponses qu'il a écrites, et quand.

## L'effacement

L'effacement est idempotent : relancé, il ne trouve plus rien et rapporte les mêmes effets.

| Dépôt | `erasure: anonymise` (par défaut) | `erasure: remove` |
|---|---|---|
| `phil_review` | L'auteur, le titre, le texte, la contestation et les mots de la compensation sont retirés ; les notes, les superlatifs, la mention de la compensation, le sujet et le moment sont conservés. | Les avis sont supprimés. |
| `phil_review_score`, `phil_review_superlative` | Conservés avec l'avis anonymisé. | Supprimés avec l'avis. |
| `phil_review_vote` | Supprimés ; les décomptes des avis sont écrits de nouveau. | Supprimés ; les décomptes sont écrits de nouveau. |
| Réponses | Retirées des avis auxquels elles répondaient. | Retirées. |

Le rapport nomme les dépôts `Anonymised` ou `Deleted`. `anonymise` conserve les notes des sujets telles que les avis les
ont faites, ce que le site juge non identifiable une fois qu'il ne reste que des notes et une date ; un site où un
sujet a si peu d'avis qu'une note et une date désignent une personne choisit `remove`. Dans les deux cas, les notes de
chaque sujet que l'effacement a touché sont recalculées.

Un avis anonymisé affiche « A former member » comme auteur, et ne peut plus être modifié ni contesté.
