<!-- phil-dev source-sha256=22058753e23d4c01c4acf7228c99b5bf49d5578b82460a991198c951ae933793 -->
# Vote par paires

Certains jurys trouvent plus facile de comparer que de noter : lequel de ces deux costumes, de ces deux idées de jeu, de
ces deux logos est le meilleur ? `PairwiseVoting` recueille de tels votes pour une campagne et les transforme en notes.

```php
use Phil\ProposalReview\Memory\InMemoryVoteLog;
use Phil\ProposalReview\Pairwise\BradleyTerry;
use Phil\ProposalReview\Pairwise\PairwiseVoting;

$voting = new PairwiseVoting($board, $proposalStore, new InMemoryVoteLog(), $clock, new BradleyTerry(), $events);
```

Il travaille sur les propositions et les conflits de la campagne, par le même `ReviewBoard`, et exige un `VoteLog` (voir
[Stockage](storage.md)).

## Montrer une paire

```php
$pair = $voting->nextPair('v-31', 'logo-contest');

if (null === $pair) {
    // v-31 has judged every pair they may judge
}

// show $pair->left and $pair->right side by side
```

La paire favorise les propositions les moins montrées jusqu'ici, ne répète jamais une paire que le votant a déjà jugée,
et écarte toute proposition avec laquelle le votant est en conflit. [Algorithmes](algorithms.md#le-tirage-des-paires)
donne la règle exacte.

## Voter

```php
$vote = $voting->vote('v-31', 'logo-contest', winner: 'logo-4', loser: 'logo-9');
```

Les votes s'expriment pendant la phase d'évaluation. Un votant en conflit avec l'une ou l'autre des propositions est
refusé. Le vote est ajouté au journal avec son numéro, son votant et son instant en UTC, et `PairwiseVoteCast` est
réparti. Un journal est en ajout seul : le moteur ne change ni ne retire jamais un vote.

Le moteur ne décide ni qui peut voter, ni combien de fois : l'application vérifie que le votant est membre du jury, ou
du public pour un vote public, et limite le débit.

## Lire les notes

```php
foreach ($voting->scores('logo-contest') as $score) {
    // $score->proposal, ->strength (log scale, 0 for the reference), ->wins, ->losses, ->comparisons()
    // $score->rating(): the strength on the chess rating scale, 1 500 for the reference
}

$chance = $first->chanceAgainst($second); // the model's chance that $first is preferred to $second
```

Les notes sont calculées chaque fois à partir de tout le journal, la plus forte d'abord ; passez
`\SortDirection::Ascending` pour la plus faible d'abord. Le même journal donne toujours les mêmes notes : quiconque
détient le journal peut donc les recalculer et vérifier le classement. Une proposition sans vote a la force 0, comme la
référence du modèle.

## Régler le modèle

```php
new BradleyTerry(prior: 1.0, iterations: 500, tolerance: 1e-9);
```

- L'a priori compte des parties virtuelles contre la référence : un a priori plus grand tire plus fort vers le milieu
  les propositions qui ont peu de votes, un a priori plus petit laisse quelques votes parler plus haut. 1 convient aux
  jurys de quelques personnes ; un vote public de milliers de votes peut en prendre moins.
- L'itération s'arrête quand aucune force ne bouge de plus que la tolérance, ou après les itérations permises.

Les notes et les votes par paires peuvent vivre dans la même campagne : un concours peut noter les finalistes après que
des votes par paires les ont choisis, ou montrer les deux classements côte à côte.
