<!-- phil-dev source-sha256=c6cbfe3dd8bf8af88588f2ac508a2e4ab0450c89942b35b6fbb79959c7d43606 -->
# Utilisation

## Construire un problème

Un `Problem` contient tout ce qu'une affectation lit, vérifié une fois, en bloc, à la construction :

```php
use Phil\Assignment\Applicant;
use Phil\Assignment\Pair;
use Phil\Assignment\Problem;
use Phil\Assignment\Slot;

$problem = new Problem(
    slots: [
        new Slot('table-1', capacity: 5, minimum: 3),
        new Slot('table-2', capacity: 6, minimum: 4),
        new Slot('table-3', capacity: 4),
    ],
    applicants: [
        Applicant::person('gm-12', []),
        Applicant::person('u-104', ['table-1', 'table-3', 'table-2']),
        Applicant::group('g-7', ['u-201', 'u-202', 'u-203'], ['table-2', 'table-1']),
    ],
    forbidden: [new Pair('u-104', 'table-2')],
    forced: [new Pair('gm-12', 'table-1')],
);
```

- Un `Slot` est une place : une table de jeu, un atelier, une salle, un rôle. Sa capacité est le nombre de personnes
  qu'il contient ; son minimum, zéro par défaut, est le nombre dont il a besoin pour avoir lieu.
- Un `Applicant` est placé d'un seul tenant : `Applicant::person()` pour une personne, dont l'identifiant est celui du
  candidat ; `Applicant::group()` pour des gens qui restent ensemble, qui prennent autant de places qu'ils sont, tous
  dans un même créneau. Les choix sont des identifiants de créneaux, le plus voulu d'abord ; un candidat peut n'en
  classer aucun.
- Une `Pair` interdite tient un candidat hors d'un créneau, classé ou non. Une `Pair` forcée place un candidat dans un
  créneau avant tout le monde, classé ou non : un meneur de jeu à sa propre table, un hôte dans sa salle.

Le problème refuse par `InvalidProblem` ce qu'il ne peut pas résoudre tel quel : un identifiant donné deux fois, une
personne dans deux candidats, un choix qui ne nomme aucun créneau, un candidat forcé dans deux créneaux ou dans un
créneau qui lui est aussi interdit, des candidats forcés qui dépassent un créneau. L'ordre des listes n'a pas
d'importance : les créneaux et les candidats sont gardés dans l'ordre de leurs identifiants, de sorte que le même
problème écrit dans un autre ordre donne le même résultat.

## Choisir un assigneur

Chaque assigneur implémente `Contract\Assigner` et reçoit le problème et un `Seed` :

```php
use Phil\Assignment\Cost\SquaredRankCost;
use Phil\Assignment\Mechanism;
use Phil\Assignment\MinCostAssigner;
use Phil\Assignment\RankedRounds;
use Phil\Assignment\RoundOrder;
use Phil\Assignment\Seed;

$seed = Seed::fromString('spring-convention-2027');

$rounds = new RankedRounds()->assign($problem, $seed);
$fairer = new RankedRounds(Mechanism::Rounds, RoundOrder::Alternating)->assign($problem, $seed);
$serial = new RankedRounds(Mechanism::SerialDictatorship)->assign($problem, $seed);
$global = new MinCostAssigner(new SquaredRankCost())->assign($problem, $seed);
```

| Assigneur | Employez-le quand |
|---|---|
| `RankedRounds`, `Mechanism::Rounds` | L'organisateur annonce des rondes : le premier choix de chacun est servi avant le deuxième de quiconque. L'ordre de chaque ronde vient du tirage. |
| `RoundOrder::Alternating` | Les mêmes rondes, mais le dernier servi dans une ronde est servi en premier dans la suivante, de sorte qu'un mauvais tirage coûte moins. |
| `Mechanism::SerialDictatorship` | Chaque candidat, dans l'ordre du tirage, prend son meilleur choix qui a de la place : simple à expliquer, et personne ne gagne à classer un créneau plus bas qu'il ne le veut. |
| `MinCostAssigner` | L'organisateur veut placer autant de gens que possible et avoir le moins de mauvais placements au total, et accepte un résultat qui est un optimum global plutôt qu'une suite de tours. |

[Algorithmes](algorithms.md) dit ce que chacun garantit.

Un germe est un texte de 1 à 255 octets : un nom que l'organisateur annonce d'avance, ou `Seed::random()` quand personne
ne doit le choisir. Gardez sa valeur avec le résultat : le même problème et le même germe redonnent le même résultat.

## Lire le résultat

`assign()` renvoie un `Assignment` :

```php
foreach ($result->placements as $placement) {
    // $placement->applicant, $placement->slot
    // $placement->placedBy: PlacedBy::Forced, Round, Group or Optimisation
    // $placement->rank: 1 for a first choice, null for a forced slot the applicant did not rank
    // $placement->round: the round of ranked choices that placed them, null outside the rounds
}

foreach ($result->unplaced as $unplaced) {
    foreach ($unplaced->choices as $choice) {
        // $choice->slot, $choice->outcome: ChoiceOutcome::Full, Forbidden or Closed
    }
}

$result->closedSlots;              // slots closed for ending under their minimum, in the order they were closed
$result->placementOf('g-7');       // by applicant
$result->placementOfMember('u-202'); // by person, alone or in a group
$result->membersOf('table-1');     // the people in a slot
$result->occupancy('table-1');     // the seats taken
$result->rankCounts();             // [1 => 41, 2 => 12, 3 => 3]: how many got each rank
$result->cost;                     // the total rank cost of the placements
```

Les placements et les candidats non placés sont des listes dans l'ordre des identifiants de candidats. Chaque placement
en dit assez pour l'expliquer à la personne — « votre premier choix, à la ronde 1 », « votre deuxième choix : votre
premier était plein à la ronde 1 » — et chaque candidat non placé dit pourquoi chacun de ses choix a échoué. La
bibliothèque donne les raisons sous forme de données ; l'application écrit les phrases dans la langue du lecteur.

## Conserver le résultat pour un audit

`toArray()` transforme le résultat en données simples — la méthode et ses réglages, le germe, l'ordre du tirage, chaque
placement et chaque choix refusé — à conserver à côté du problème :

```php
$log = json_encode(['problem' => $input, 'result' => $result->toArray()], \JSON_THROW_ON_ERROR);
```

Quiconque détient le problème et le germe peut relancer le même assigneur et comparer. `ProblemReader` relit le problème
depuis le même JSON, ou depuis des tableaux que donne un formulaire ou une base de données :

```php
use Phil\Assignment\ProblemReader;

$problem = new ProblemReader()->fromJson($json);
```

Le JSON a quatre listes, toutes facultatives :

```json
{
  "slots": [{"id": "table-1", "capacity": 5, "minimum": 3}],
  "applicants": [{"id": "u-104", "choices": ["table-1"]}, {"id": "g-7", "members": ["u-201", "u-202"]}],
  "forbidden": [{"applicant": "u-104", "slot": "table-2"}],
  "forced": [{"applicant": "g-7", "slot": "table-1"}]
}
```
