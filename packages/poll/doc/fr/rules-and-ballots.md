<!-- phil-dev source-sha256=477388432e8ce6030e469dbd2ea24f6d0cdd11415b2514fe2ad7402bbbfe5771 -->
# Règles et bulletins

## Les sortes de bulletins

| `BallotKind` | Un bulletin contient | Construit par |
|---|---|---|
| `Single` | exactement une option | `PollRules::single($options, $abstention)` |
| `Multiple` | entre `$min` et `$max` options, sans ordre | `PollRules::multiple($options, $min, $max, $abstention)` |
| `Ranked` | entre `$min` et `$max` options, la première étant préférée | `PollRules::ranked($options, $min, $max, $abstention)` |

`$min` vaut 1 par défaut et `$max` le nombre total d'options. `PollRules::of($kind, $options, $min, $max, $abstention)`
construit les règles de n'importe quelle sorte, telles qu'un stockage les garde.

## Les options

Un scrutin a de 2 à 100 options (`PollRules::MAX_OPTIONS`). Chacune est identifiée par 1 à 64 lettres ASCII, chiffres,
`_` ou `-` (`PollRules::MAX_OPTION_LENGTH`), et déclarée une seule fois ; son libellé, sa description et ses
traductions relèvent de l'application. L'ordre déclaré est gardé : c'est l'ordre du classement entre options à
égalité, et le dernier bris d'égalité d'un vote alternatif.

La borne sur les options borne le coût d'un dépouillement : le compte par paires de la méthode Schulze croît avec le
carré du nombre d'options, ses chemins les plus forts avec son cube.

## Les bornes et l'abstention

Les bornes respectent 1 ≤ min ≤ max ≤ le nombre d'options ; un bulletin unique contient exactement un choix. Un
bulletin hors de ces bornes est refusé.

Un bulletin blanc — sans aucun choix — est une abstention quand les règles en acceptent une (`$abstention: true`), et
est refusé sinon. Un dépouillement le compte parmi les bulletins et parmi les abstentions, et ne le donne à aucune
option.

## Vérifier un bulletin

`PollRules::ballot()` prend les choix tels qu'un formulaire ou une requête les donne, `array<mixed>`, et retourne un
`Ballot` ou lance `InvalidBallot` quand :

- les choix ne sont pas une liste ;
- un choix n'est pas une chaîne qui nomme l'une des options ;
- un choix est donné deux fois ;
- le nombre de choix est hors des bornes, sauf si le bulletin est blanc et l'abstention acceptée.

Un bulletin classé garde l'ordre donné : le premier choix est le préféré. Un bulletin ne porte rien sur son électeur ;
qui a voté et si cela est gardé relèvent de l'application.

`Ballot::of()` construit un bulletin tel quel, pour un stockage qui relit les choix qu'il a vérifiés au moment du
vote. Un dépouillement vérifie de nouveau que chaque choix est une option, donnée une seule fois, mais pas les bornes :
des règles dont les bornes ont été resserrées après le vote dépouillent encore les bulletins déposés avant.
