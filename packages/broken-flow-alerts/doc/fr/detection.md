<!-- phil-dev source-sha256=e51facba56d39e652747d9fd312abb868cdb93c5d3c13bf355ee8540cfd0669c -->
# Détection

`BrokenFlowRule` décide si un parcours est interrompu d'après ses aboutissements quotidiens. Elle lit un historique de
`historyDays()` comptes, du plus ancien au plus récent, en deux parties :

| Partie | Jours | Rôle |
|---|---|---|
| La base | `baselineDays`, 28 par défaut (de 7 à 366) | Fixe le rythme : combien d'aboutissements chaque jour doit apporter |
| La fenêtre | `windowDays`, 7 par défaut (de 1 à 31) | Les derniers jours, comparés au rythme |

```php
use Phil\BrokenFlowAlerts\BrokenFlowRule;

$rule = new BrokenFlowRule();                                   // 28 + 7 days, 0.001, weekly
$status = $rule->evaluate($history);                            // exactly 35 counts

$status->broken;       // bool
$status->days;         // how many of the last days the numbers cover
$status->observed;     // the completions over those days
$status->expected;     // the completions the rhythm expects over those days
$status->probability;  // how likely so few completions are in a working flow
```

## Le rythme d'un jour

Avec `weekly` (par défaut) et une base de deux semaines ou plus, chaque jour de la fenêtre doit apporter la moyenne des
jours de la base qui tombent le même jour de la semaine : une boutique fermée le dimanche a un dimanche habituel à zéro,
et un dimanche silencieux n'est pas une baisse. Autrement, chaque jour doit apporter la moyenne de toute la base. Le jour
de la semaine se trouve par position — un nombre entier de semaines avant le jour —, et l'historique n'a donc besoin
d'aucune date.

Une base d'un nombre entier de semaines donne le même poids à chaque jour de la semaine ; 28 jours en donnent quatre de
chaque.

## Le compte de Poisson

Les aboutissements arrivent un à un, assez indépendamment pour que le compte d'un jour suive une loi de Poisson autour de
sa valeur attendue. Pour chaque étendue de la fenêtre qui se termine son dernier jour — le dernier jour, les deux derniers
jours, et ainsi de suite jusqu'à toute la fenêtre —, la règle additionne les aboutissements observés et attendus, et
calcule `P(X <= observed)` pour `X` un compte de Poisson de cette moyenne. L'étendue la moins probable est celle qui est
rapportée, et **le parcours est interrompu quand cette probabilité est sous `significance`**.

| Le jour habituel d'un parcours | Jours silencieux avant qu'il soit interrompu, à 0.001 |
|---|---|
| 20 aboutissements | 1 (`e^-20`) |
| 5 aboutissements | 2 (`e^-10`) |
| 2 aboutissements | 4 (`e^-8`) |
| 1 aboutissement | 7 (`e^-7`) |
| 0.9 aboutissement ou moins | jamais, dans une fenêtre de 7 jours |

Une baisse est aussi une interruption, pas seulement un silence : un dixième des aboutissements habituels sur trois jours
d'un parcours qui en apporte d'habitude 50 par jour est bien sous 0.001. Un compte égal ou supérieur à ce qu'attend le
rythme n'est jamais une baisse.

Les étendues se terminent toutes le dernier jour : un dernier jour normal ne cache pas les jours silencieux d'avant. Deux
jours silencieux suivis d'un jour normal pèsent encore comme trois jours bien sous leur rythme, jusqu'à ce que les jours
silencieux sortent de la fenêtre.

## Un travail borné

La queue est additionnée terme par terme en logarithmes, pour qu'une moyenne de plusieurs milliers ne s'annule pas à
zéro, jusqu'à `PoissonTail::EXACT_UP_TO` (10 000) aboutissements ; au-delà, la borne de Chernoff
`exp(-mean) (e mean / k)^k` remplace la somme. La borne n'est jamais sous la queue exacte, et ne peut donc que rendre une
alerte moins probable. Une vérification coûte au plus `windowDays` sommes de 10 001 termes par parcours, quels que soient
les comptes.

## Les réglages

| Réglage | Plus bas | Plus haut |
|---|---|---|
| `baselineDays` | Suit plus tôt un changement de rythme ; une courte période de silence pèse davantage sur le rythme | Rythme plus stable ; un changement de rythme durable met plus de temps à devenir la norme |
| `windowDays` | Seuls les courts silences sont pesés ; un parcours rare peut ne jamais être interrompu | Les parcours rares peuvent être interrompus après un silence plus long ; un silence passé garde un parcours interrompu plus longtemps |
| `significance` | Moins de fausses alertes, et une vraie plus tard | Des alertes plus tôt, et plus de fausses |
| `weekly: false` | — | Chaque jour a le même rythme : pour un parcours sans motif hebdomadaire, ou une base de moins de deux semaines |

Les réglages refusés lancent `InvalidFlowValue` : une base hors de 7 à 366 jours, une fenêtre hors de 1 à 31 jours, un
seuil de signification qui n'est pas au-dessus de 0 et sous 0.5. Un historique de la mauvaise longueur, ou qui contient
un compte négatif, est refusé de la même façon.
