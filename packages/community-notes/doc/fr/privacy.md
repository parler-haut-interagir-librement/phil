<!-- phil-dev source-sha256=ebcd9bddf3877e2edd7dc622c2e370d7781f0886df8c7222b5e11d1c3971b3b5 -->
# Vie privée

## Ce que la bibliothèque conserve

| Donnée | Conservée par | Personnelle |
|---|---|---|
| Une note : son sujet, son auteur, son texte, son heure, son état et sa décision | `NoteRepository` | L'auteur, et le texte qu'il a rédigé |
| Une évaluation : sa note, son évaluateur, son verdict et son heure | `RatingRepository` | L'évaluateur, et ce qu'il a pensé d'une note |
| Une décision : les paires d'évaluateurs qui l'ont prise | Avec la note | Les évaluateurs nommés dans chaque paire |

Aucun score d'évaluateur n'est calculé ni conservé. Les événements et le message nomment une note seulement, jamais un
membre.

## Montrer une décision

Une évaluation dit ce qu'un membre pense d'une note ; les paires d'une décision nomment des évaluateurs. Une page pour
les lecteurs montre `toPublicArray()`, qui omet les évaluateurs ; `toArray()`, avec eux, est pour les modérateurs.

## Accès et effacement

`exportMember($member)` donne ce que la bibliothèque conserve d'un membre, en valeurs simples : les notes qu'il a
rédigées — identifiant, sujet, texte, état, heure — et les évaluations qu'il a rendues — note, verdict, heure. Il est
vide quand le membre n'a rien rédigé ni évalué.

`eraseMember($member)` l'efface :

- les notes qu'il a rédigées sont supprimées, avec les évaluations de ces notes, et `NoteErased` est diffusé pour chacune ;
- les évaluations qu'il a rendues sont supprimées ;
- les notes qu'il a évaluées sont décidées de nouveau, si bien qu'aucune décision conservée ne le nomme plus ; une note
  retirée garde son état et reçoit une nouvelle décision.

Supprimer les notes d'un membre change l'historique des autres évaluateurs, que d'autres décisions lisent : une
application qui veut les tenir toutes à jour envoie `ReconsiderNote` pour les notes concernées, ou pour chaque note, en
arrière-plan.

Une application qui a un processus d'accès et d'effacement appelle les deux depuis ses propres gestionnaires de ces
demandes.
