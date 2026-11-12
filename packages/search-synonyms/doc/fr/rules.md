<!-- phil-dev source-sha256=3249d5ccfb1586caa9d648bc8705597262afb279043a81bf4b3b9ddeefd3c258 -->
# Règles

| Règle | Construite avec | Une recherche de |
|---|---|---|
| Équivalence | `SynonymSet::equivalent(['jdr', 'jeu de rôle'])` | l'une ou l'autre trouve les deux |
| Sens unique | `SynonymSet::oneWay(['donjon'], ['donjon', 'dungeon'])` | `donjon` trouve `donjon` et `dungeon` ; `dungeon` ne trouve que lui-même |

Une règle à sens unique remplace les expressions de sa gauche par celles de sa droite : inscrivez aussi une expression
de gauche à droite pour continuer à la trouver. `$from` et `$to` contiennent les deux côtés — la même liste pour une
règle d'équivalence —, `$equivalent` dit de quel genre est une règle, et `phrases()` donne chaque expression une fois.

## Expressions

Chaque expression est gardée comme une recherche compare les mots : en minuscules, chaque suite d'espaces blancs — une
tabulation, un saut de ligne, une espace insécable — devenue une seule espace, sans espace autour. Ainsi gardée, une
expression apparaît une seule fois dans une règle.

| Limite | Valeur |
|---|---|
| Une expression | De 1 à 100 caractères, 8 mots au plus, de l'UTF-8 valide sans caractère de contrôle |
| Une règle | 64 expressions au plus ; deux ou plus pour une règle d'équivalence ; une ou plus de chaque côté d'une règle à sens unique, qui doit changer quelque chose |
| Une langue | 10 000 règles au plus |

Une règle hors de ces limites est refusée avec `InvalidSynonyms` à sa construction.

## Une langue

`SynonymList::of('fr-CA', $sets)` contient les règles d'une langue, dans leur ordre, sous sa sous-étiquette primaire en
minuscules : `fr`. `with($set)` donne la même liste avec une règle de plus.

## Le validateur

`SynonymValidator::validate($list)` énumère ce qui est permis mais probablement erroné, sous forme de `Violation` — la
position de la règle à partir de 0, un code et une phrase —, dans l'ordre des règles :

| Code | Quand | Pourquoi c'est important |
|---|---|---|
| `overlap` | Une expression est à gauche d'une deuxième règle | Une recherche la lit comme les deux règles fusionnées |
| `chain` | Une règle à sens unique mène à une expression qu'une autre règle réécrit | Les synonymes s'appliquent une fois : la deuxième règle n'est pas suivie |

Une administration les montre avant l'enregistrement ; rien n'arrête une liste qui en contient.
