<!-- phil-dev source-sha256=43609dd50ed57866f20eb5b6b43e08e80533618d9f91148bc8701ac04376cc6d -->
# L'état dans l'adresse

| Paramètre | Sens |
|---|---|
| `sort` | Une colonne triable, précédée de `-` en ordre décroissant |
| `page` | La page, à partir de 1 |
| `size` | L'une des tailles offertes |
| `filters[...]` | Les filtres, par nom |
| `columns[]` | Les colonnes visibles, toutes en son absence |
| `selected[]` | Les lignes sélectionnées |
| `confirm`, `row` | L'action dont la question est affichée, et sa ligne |
| `edit`, `row` | La colonne en cours de modification, et sa ligne |

`StateResolver` vérifie chaque valeur selon la déclaration, et une valeur inconnue revient à la valeur par défaut du
tableau. Rien de l'adresse n'atteint une requête tel qu'il a été écrit.

Les autres paramètres de la page sont gardés dans les liens et les formulaires du tableau. Quand une page contient
plusieurs tableaux, donnez à chacun un `urlPrefix` : ses paramètres deviennent `items_sort`, `items_page`, et ainsi de
suite.

## Sans JavaScript

Chaque contrôle fonctionne en simple HTML :

- le tri, la taille de page, les filtres et les colonnes appartiennent à un formulaire GET, que soumettent les boutons
  des en-têtes ;
- la pagination, les liens Modifier et les actions de ligne sont des liens vers le même tableau dans un autre état ;
- la question d'une action et le contrôle d'une cellule sont des formulaires qui envoient un POST aux routes du bundle,
  puis reviennent avec un `303`.

Un lien n'est qu'une chaîne de requête : il fonctionne donc depuis la page comme depuis la route propre au composant.
