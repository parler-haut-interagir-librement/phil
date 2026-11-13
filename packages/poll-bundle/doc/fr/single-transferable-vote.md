<!-- phil-dev source-sha256=9078a77a15997acdeee5f20857ab66f75c758363d94078299982cefde4af35c4 -->
# Vote unique transférable

Un sondage qui pourvoit plusieurs sièges — un conseil de cinq personnes, les trois jeux du prochain congrès — est
dépouillé par le vote unique transférable. Le bundle le compte selon la méthode de Meek, `meek_stv`, sur des bulletins
classés, en nombres exacts, et publie chaque tour : quiconque détient les bulletins peut rejouer le dépouillement et
retrouver les mêmes chiffres.

## Un sondage à sièges

```php
use Phil\Poll\BallotKind;
use Phil\PollBundle\Application\PollDraft;
use Phil\PollBundle\Domain\Counting\MeekStv;
use Phil\PollBundle\Domain\Status\ResultsVisibility;
use Phil\PollBundle\Domain\ValueObject\PollOption;

$poll = $polls->create(new PollDraft(
    'Who sits on the board?',
    [PollOption::of('ann', 'Ann'), PollOption::of('bob', 'Bob'), PollOption::of('cat', 'Cat'), PollOption::of('dan', 'Dan')],
    BallotKind::Ranked,
    counting: MeekStv::NAME,
    results: ResultsVisibility::AfterClosing,
    seats: 2,
), $member);
```

`seats` va de 1 à un de moins que les options. Un sondage dont la méthode ne pourvoit pas de sièges en refuse plus d'un
avec `InvalidPollDefinition` ; `meek_stv` avec un seul siège est un vote alternatif compté en nombres exacts.

## L'élection et ses tours

`Polls::election($poll)` compte les bulletins en une `Election` : la méthode, les sièges, les bulletins et les
abstentions comptés, les options élues dans l'ordre de leur élection, et les tours. `electionFor($poll, $viewer)`
compte pour une personne qui regarde, comme le fait `resultsFor()`. `results($poll)` donne le même dépouillement sous
la forme d'un `Result` de `phil/poll` : les options élues se partagent la première place.

Chaque `ElectionRound` publie :

| Champ | Ce qu'il contient |
|---|---|
| `number` | Le tour, à partir de 1. |
| `iterations` | Les passages sur les bulletins qu'il a fallu pour que les facteurs de rétention se stabilisent. |
| `quota` | Le quota de son dernier passage. |
| `tallies` | Pour chaque option encore dans le dépouillement, dans l'ordre déclaré : ses votes, son facteur de rétention et son état. |
| `exhausted` | La valeur qu'aucune option n'a retenue : les bulletins dont les choix se sont épuisés. |
| `surplus` | Ce que les options élues détenaient au-dessus du quota à la fin du tour. |
| `elected` | Les options élues pendant le tour. |
| `excluded` | L'option exclue à sa fin, ou les options laissées de côté une fois les sièges pourvus. |
| `tieBreak` | La façon dont une égalité pour le moins de votes a été départagée, quand il y en a eu une : `earlier_rounds` ou `declared_order`. |

`Election::toArray()` écrit le tout en valeurs simples, chaque nombre avec ses neuf décimales (`4.714288797`), pour une
page, un fichier ou une comparaison avec un dépouillement rejoué.

## Le dépouillement

Chaque option a un facteur de rétention : 1 tant qu'elle est en lice, 0 une fois exclue et, une fois élue, la part de
chaque vote qui l'atteint qu'elle garde, le reste passant au choix suivant du bulletin. Un passage parcourt les
bulletins : chaque bulletin donne à chaque option de sa liste, dans l'ordre, la part de sa valeur que l'option retient,
jusqu'à ce qu'il ne reste plus rien ; ce qui reste à la fin de la liste est épuisé.

- Le quota est les votes retenus par les options divisés par un de plus que les sièges, tronqué, plus un milliardième.
- Une option en lice qui atteint le quota ou le dépasse est élue.
- Le facteur de rétention de chaque option élue est ramené à quota ÷ votes, et le passage recommence, jusqu'à ce que le
  surplus des options élues soit sous un cent-millième de vote, cesse de diminuer, ou que mille passages aient eu lieu.
- L'option en lice qui a le moins de votes est alors exclue, et un nouveau tour commence.
- Le dépouillement prend fin quand les sièges sont pourvus, les options en lice restantes étant battues, ou quand les
  options en lice restantes ne sont pas plus nombreuses que les sièges encore vides, et sont toutes élues.

Une égalité pour le moins de votes est départagée par les tours précédents, du plus récent au plus ancien : l'option
qui détenait le moins de votes au dernier tour où les options à égalité diffèrent est exclue. Quand elles n'ont jamais
différé, c'est celle qui a été déclarée en dernier. Une application qui veut un tirage au sort déclare les options du
sondage dans un ordre aléatoire.

## Nombres exacts

Les votes, le quota, les facteurs de rétention et les surplus sont des nombres rationnels de dénominateur un milliard,
conservés comme entiers par `Billionths` ; aucun nombre à virgule flottante n'entre dans le dépouillement. Chaque
arrondi va dans un seul sens, pour qu'aucun vote ne soit jamais créé : la part qu'une option retient d'un vote est
tronquée, le reste passant en entier ; un facteur de rétention est arrondi vers le haut. À chaque tour, les votes
détenus par les options et la valeur épuisée s'additionnent exactement aux bulletins comptés.

Les mêmes bulletins donnent les mêmes tours, jusqu'à la dernière décimale, sur n'importe quelle machine.

## Coût et bornes

Un dépouillement lit les bulletins une seule fois et regroupe les classements identiques ; un passage parcourt les
classements distincts, non les bulletins. Il fait au plus un tour par option et mille passages par tour. Dix mille
bulletins sur dix options, en quelques centaines d'ordres distincts, se comptent en une dizaine de millisecondes, pour
un siège comme pour trois. Un dépouillement contient au plus un milliard de bulletins, pour que les votes, en
milliardièmes, restent dans un entier ; une application abaisse la borne en enregistrant `new MeekStv($maxBallots)` à
sa place, et un dépouillement qui la dépasse lève `InvalidPoll`.

Le dépouillement est soumis au fuzzing : sur des bulletins que personne n'a écrits, il pourvoit toujours ses sièges
quand un bulletin choisit quoi que ce soit, chaque tour s'additionne aux bulletins, et rien n'est levé que le refus
d'un mauvais sondage.

## Une méthode de l'application

Une méthode de l'application qui pourvoit des sièges implémente `Phil\PollBundle\Domain\Counting\Contract\ElectingMethod` :
`elect($rules, $ballots, $seats)` renvoie une `Election`. Voir [Points d'extension](extension-points.md).
