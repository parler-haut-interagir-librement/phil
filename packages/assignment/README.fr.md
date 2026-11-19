<!-- phil-dev source-sha256=595c7264a83e30004431dfee5a389e88a66c0934601f53331587c289ad736d5d -->
# phil/assignment

**Affectation par préférences classées — tables de jeu, ateliers, salles, rôles — par rondes de choix ou par
optimisation globale, déterministe pour un germe et expliquée placement par placement.**

phil/assignment place les gens là où ils ont demandé à aller. Chaque candidat — une personne, ou un groupe qui reste
ensemble — classe les créneaux qu'il veut ; chaque créneau a une capacité et un minimum sous lequel il n'a pas lieu ;
l'organisateur peut interdire un créneau à un candidat, ou l'y placer d'office. La bibliothèque répond par un placement
pour chaque candidat, la ronde et le rang qui l'ont donné, les candidats restés sans créneau et ce qu'est devenu chacun
de leurs choix, et les créneaux fermés parce qu'ils finissaient sous leur minimum.

Deux familles d'assigneurs résolvent le même problème. `RankedRounds` sert les choix dans un ordre que tire un germe :
ronde après ronde — tous les premiers choix, puis tous les deuxièmes choix de ceux qui attendent encore — ou en une
seule passe où chaque candidat prend son meilleur choix qui a de la place. `MinCostAssigner` regarde tout le monde à la
fois : un flot de coût minimal place autant de gens qu'il est possible de placer et, parmi ces placements, donne le
coût total de rang le plus bas. Les deux donnent le même résultat pour le même problème et le même germe, sur toutes
les machines, et le germe et l'ordre du tirage reviennent avec le résultat, de sorte que n'importe qui peut relancer
l'affectation et la vérifier.

Elle sert à toute application PHP qui remplit des places limitées à partir de souhaits : un congrès qui assoit des
joueurs à des tables de jeu, une école qui envoie des élèves à des ateliers, une retraite qui partage des chambres, un
jeu de rôle grandeur nature qui distribue des rôles. C'est une bibliothèque PHP simple, sans aucune dépendance. Les
problèmes sont bornés avant tout travail et chaque exécution a un budget de travail, de sorte qu'une entrée que
personne n'a bornée ne peut pas retenir un worker.

## État

| Partie | État |
|---|---|
| Créneaux avec capacités et minimums, candidats seuls ou en groupes, paires interdites et forcées, limites | Implémenté et testé |
| Rondes de choix classés, dans le même ordre ou en alternance ; dictature en série | Implémenté et testé |
| Optimisation globale par flot de coût minimal, coûts de rang linéaires ou au carré, groupes placés d'abord | Implémenté et testé |
| Créneaux fermés sous leur minimum et leurs gens placés de nouveau | Implémenté et testé |
| Tirage à germe, reproductible ; résultats expliqués par placement ; données simples pour un journal d'audit | Implémenté et testé |
| Lecture d'un problème depuis du JSON ou des tableaux, avec une cible de fuzzing | Implémenté et testé |
| Garder deux personnes séparées, ou scinder un groupe entre des créneaux | Non prévu : les groupes restent entiers, les contraintes sont des paires candidat–créneau |
| Stockage, formulaires et écrans | Laissés à l'application : la bibliothèque reçoit un problème et renvoie un résultat |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Utilisation](doc/fr/usage.md) | Construire un problème, choisir un assigneur, lire le résultat, le conserver pour un audit. |
| [Algorithmes](doc/fr/algorithms.md) | Chaque mécanisme, ce qu'il garantit, comment les minimums ferment des créneaux, la complexité et les budgets. |
| [Sécurité](doc/fr/security.md) | Les limites, le budget de travail, les identifiants et la lecture d'une entrée non fiable. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application peut remplacer ou ajouter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du paquet, son banc d'essai et sa cible de fuzzing. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/assignment
```

```php
use Phil\Assignment\Applicant;
use Phil\Assignment\Problem;
use Phil\Assignment\RankedRounds;
use Phil\Assignment\Seed;
use Phil\Assignment\Slot;

$problem = new Problem(
    [new Slot('table-1', capacity: 5, minimum: 3), new Slot('table-2', capacity: 4)],
    [
        Applicant::person('u-104', ['table-1', 'table-2']),
        Applicant::group('g-7', ['u-201', 'u-202'], ['table-2', 'table-1']),
    ],
);

$seed = Seed::random();                                   // keep $seed->value with the result
$result = new RankedRounds()->assign($problem, $seed);    // or new MinCostAssigner()

foreach ($result->placements as $placement) {
    printf("%s: %s, choice %d, round %d\n", $placement->applicant, $placement->slot, $placement->rank, $placement->round);
}
```

## Prérequis

PHP 8.5 ou une version plus récente ; rien d'autre.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
