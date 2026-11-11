<!-- phil-dev source-sha256=c8e55674bb44c51e8c933b3a7c55f650b951269baa7c865a03e5b72e054e3eaf -->
# Estimer la robustesse

## L'estimation

```php
$strength = $estimator->estimate($password, ['jean.dupont@example.com', 'Jean Dupont']);
```

| Membre de `Strength` | Ce qu'il contient |
|---|---|
| `guesses` | Les essais de la couverture la moins coûteuse du mot de passe, un nombre à virgule flottante. |
| `guessesLog10()` | Leur ordre de grandeur. |
| `score` | Un `Score` de 0 à 4. |
| `sequence` | Les `PatternMatch` de la couverture, dans l'ordre. |
| `crackTimes` | `CrackTimes` : les secondes nécessaires pour faire les essais en ligne avec et sans limite de débit, hors ligne contre une empreinte lente et une empreinte rapide. |
| `feedback` | `Feedback` : un `Warning` ou null, et des `Suggestion`. |
| `length`, `analysedLength`, `isTruncated()` | Les caractères du mot de passe et ceux qui ont été analysés. |

Une robustesse et ses correspondances contiennent des parties du mot de passe : elles sont montrées à la personne qui
l'a saisi, jamais journalisées ni stockées.

## Les motifs

| `Pattern` | Trouvé | Essais |
|---|---|---|
| `Dictionary` | Chaque partie du mot de passe qui est une entrée d'un dictionnaire, telle qu'écrite, à l'envers, ou avec des caractères qui remplacent des lettres (`@` pour `a`, `0` pour `o`, `1` pour `i` ou `l`…) | Le rang de l'entrée, multiplié par ses variantes de casse et par ses variantes de substitution, doublé à l'envers |
| `Spatial` | Les suites de trois touches ou plus, chacune voisine de la précédente sur un clavier | Les parcours d'au plus cette longueur et autant de changements de direction à partir de n'importe quelle touche, multipliés par les variantes des touches majuscules |
| `Sequence` | Les suites de trois caractères ou plus à un pas constant de 1 à 5 points de code, vers le haut ou vers le bas | 4, 10 ou 26 selon le premier caractère, doublé vers le bas, multiplié par la longueur |
| `Repeat` | Une partie écrite deux fois ou plus de suite | Les essais de sa base, analysée à son tour, multipliés par les répétitions |
| `Date` | Un jour, un mois et une année de deux ou quatre chiffres, avec ou sans séparateur, dans n'importe quel ordre | Les années entre la date et l'année de référence, au moins 20, multipliées par 365, quadruplées avec un séparateur |
| `Year` | Une année de 1900 à 2099 | Les années entre elle et l'année de référence, au moins 20 |
| `Bruteforce` | Ce que les motifs laissent | 10 pour chaque caractère |

Les variantes de casse comptent 1 pour un mot en minuscules, 2 pour un mot avec une majuscule initiale, en majuscules
ou avec sa dernière lettre en majuscule, et sinon le nombre de façons de placer les majuscules. L'année de référence
est l'année en cours, sauf si l'estimateur est construit avec une autre.

## La couverture

L'estimateur prend la façon la moins coûteuse de couvrir le mot de passe avec des motifs, les caractères qui n'en
suivent aucun étant devinés un par un. Un attaquant qui essaie des suites de `l` motifs paie `l!` fois le produit de
leurs essais, plus 10 000 à la puissance `l - 1` pour avoir d'abord essayé les suites plus courtes. Une partie d'un mot
de passe plus long coûte au moins 10 essais pour un caractère et 50 pour plusieurs : la découper en petits motifs n'est
ainsi jamais moins coûteux.

## La note

| `Score` | Essais |
|---|---|
| `VeryWeak` (0) | moins de 10³ |
| `Weak` (1) | moins de 10⁶ |
| `Fair` (2) | moins de 10⁸ |
| `Strong` (3) | moins de 10¹⁰ |
| `VeryStrong` (4) | 10¹⁰ ou plus |

## La rétroaction

Un mot de passe noté 3 ou 4 ne reçoit aucune rétroaction. Sinon, le plus long motif de la couverture donne
l'avertissement — un mot de passe courant et son rang, un mot ou un nom seul, un parcours de clavier, une répétition,
une suite, une année, une date, ce que l'application sait de la personne — et des suggestions : ajouter un mot, puis ce
que le motif appelle, comme des majuscules ou des substitutions qui n'aident presque pas. Pour un mot de passe vide, la
suggestion est d'utiliser quelques mots.

`Warning` et `Suggestion` sont des enums adossées à des chaînes dont les valeurs sont stables. Leurs clés de
traduction, `warning.<value>` et `suggestion.<value>`, se trouvent dans le domaine `phil_passwords`, en anglais et en
français. Sans traducteur, `FeedbackMessages` lit les mêmes fichiers :

```php
$messages = FeedbackMessages::forLocale('fr_CA');   // fr_CA, then fr, then en
$sentences = $messages->describe($strength->feedback);
```

## Ce que l'application sait

Les données de l'utilisateur deviennent un dictionnaire à part, `user_inputs`, classé dans leur ordre : chaque donnée,
et ses parties de trois caractères ou plus découpées sur `@` et les espaces, et en suites de lettres et de chiffres.
Jusqu'à 32 données sont gardées, chacune coupée à 64 caractères. Un mot de passe construit sur elles reçoit
l'avertissement `UserInput`.

## Bornes

| Borne | Valeur | Pourquoi |
|---|---|---|
| Caractères analysés | 128 | Le travail de la couverture croît au pire avec le cube de la longueur ; un mot de passe plus long ne fait que coûter plus d'essais. |
| Motifs d'une couverture | 8 | Une couverture de plus de motifs coûte plus de 10²⁸, bien au-dessus de la meilleure note. |
| Suite devinée un par un après un motif | 20 caractères | Une suite plus longue coûte plus de 10²⁰ ; la suite du mot de passe entier reste. |
| Données de l'utilisateur | 32, de 64 caractères | Chacune devient des entrées cherchées à chaque analyse. |
| Entrées de dictionnaire | 64 caractères | Une entrée plus longue ne fait que ralentir chaque analyse. |

Au-delà de 10²⁰ essais, les bornes peuvent donner plus d'essais que la couverture la moins coûteuse : la note est 4 de
toute façon. Un mot de passe ordinaire est analysé en bien moins d'une milliseconde, l'entrée la plus hostile de 128
caractères en quelques dizaines de millisecondes ; voyez [Tests](testing.md#le-banc-dessai).
