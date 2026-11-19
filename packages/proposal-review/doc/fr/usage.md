<!-- phil-dev source-sha256=9bd3c90bfff7c83f6422b342f4317e83d8168145fcd1817886ab3d95a4703e79 -->
# Utilisation

`ReviewBoard` est le moteur. Chaque méthode nomme la campagne sur laquelle elle agit ; chaque action vérifie d'abord la
phase de la campagne selon l'horloge, et refuse par `Refused` quand la phase ne la permet pas.

## La campagne

```php
use Phil\ProposalReview\Aggregation;
use Phil\ProposalReview\Campaign;
use Phil\ProposalReview\Criterion;
use Phil\ProposalReview\Phase;
use Phil\ProposalReview\PhaseWindow;
use Phil\ProposalReview\ReviewPolicy;
use Phil\ProposalReview\Schedule;
use Phil\ProposalReview\Visibility;

$campaign = new Campaign(
    id: 'costume-contest-2027',
    schedule: new Schedule([
        new PhaseWindow(Phase::Submission, new DateTimeImmutable('2027-05-01 09:00', $zone), new DateTimeImmutable('2027-05-20 17:00', $zone)),
        new PhaseWindow(Phase::Review, new DateTimeImmutable('2027-05-20 17:00', $zone), new DateTimeImmutable('2027-05-21 12:00', $zone)),
        new PhaseWindow(Phase::AuthorResponse, new DateTimeImmutable('2027-05-21 12:00', $zone), new DateTimeImmutable('2027-05-21 14:00', $zone)),
        new PhaseWindow(Phase::Deliberation, new DateTimeImmutable('2027-05-21 14:00', $zone), new DateTimeImmutable('2027-05-21 18:00', $zone)),
    ]),
    criteria: [
        new Criterion('craftsmanship', weight: 3, min: 0, max: 10),
        new Criterion('fidelity', weight: 2, min: 0, max: 10),
        new Criterion('presence', weight: 1, min: 1, max: 5),
    ],
    policy: new ReviewPolicy(
        blind: true,
        minReviews: 3,
        maxReviews: 5,
        aggregation: Aggregation::Median,
        visibility: Visibility::AfterOwnReview,
        assignedOnly: true,
    ),
);

$campaigns->save($campaign);
```

- Les fenêtres du calendrier suivent l'ordre des phases, chacune commençant où la précédente finit. L'évaluation et la
  délibération sont obligatoires ; le dépôt et la réponse des auteurs sont facultatifs. Avant la première fenêtre, la
  campagne est `NotStarted`, après la dernière, `Closed`. Les instants sont convertis en UTC.
- Chaque critère a sa propre échelle et un poids relatif : 3 compte trois fois plus que 1.
- La politique fixe la façon dont la campagne évalue ; par défaut, c'est une évaluation à l'aveugle, trois évaluations
  par proposition, aucun maximum, la moyenne, les évaluations des autres montrées une fois la sienne faite, et des
  évaluateurs limités à ce qui leur est assigné.

## Les phases et ce qu'elles ouvrent

| Phase | Ouvert |
|---|---|
| `NotStarted` | Déclarer des conflits, assigner des évaluateurs. |
| `Submission` | `submit()` ; conflits, assignations. |
| `Review` | `review()`, `nextFor()`, votes par paires ; conflits, assignations. |
| `AuthorResponse` | `mayRespond()` est vrai pour les auteurs ; conflits, assignations. |
| `Deliberation` | `decide()`, `applyDecisions()`. |
| `Closed` | `applyDecisions()` pour les décisions encore en attente. |

La lecture — `standings()`, `reviewsVisibleTo()`, `authorsVisibleTo()` — est ouverte dans toutes les phases.
`phaseOf()` donne la phase actuelle ; `advance()`, appelée depuis une tâche planifiée, l'enregistre et répartit
`PhaseChanged` quand elle a changé :

```php
// every few minutes, from a cron job or a Scheduler task
$board->advance('costume-contest-2027');
```

## Les propositions

```php
$board->submit('costume-contest-2027', 'entry-17', authors: ['u-201', 'u-202']);
```

La proposition est l'identifiant de l'application avec les identifiants de ses auteurs ; son contenu reste dans
l'application. Déposer de nouveau pendant le dépôt remplace les auteurs. Une campagne dont les propositions viennent
d'ailleurs, sans fenêtre de dépôt, les enregistre directement dans le `ProposalStore`.

## Les évaluateurs et les conflits

```php
use Phil\ProposalReview\ConflictOfInterest;

$board->declareConflict(ConflictOfInterest::withAuthor('costume-contest-2027', 'r-7', 'u-201'));
$board->declareConflict(ConflictOfInterest::withProposal('costume-contest-2027', 'r-3', 'entry-22'));

$board->assign('costume-contest-2027', 'entry-17', 'r-4');
$made = $board->assignBalanced('costume-contest-2027', ['r-1', 'r-2', 'r-3', 'r-4', 'r-5', 'r-7'], perProposal: 3);
```

Un évaluateur est toujours en conflit avec ses propres propositions ; un conflit déclaré couvre une proposition, ou
toutes les propositions d'un auteur. Un évaluateur en conflit n'est jamais assigné, n'évalue jamais, et ne voit ni
évaluation ni auteur de la proposition. Les conflits et les assignations restent ouverts jusqu'à la délibération.
[Algorithmes](algorithms.md) dit comment l'assignation équilibrée choisit.

## Évaluer

```php
use Phil\ProposalReview\Score;

if ($board->mayReview('r-4', 'costume-contest-2027', 'entry-17')) {
    $board->review('costume-contest-2027', 'entry-17', 'r-4', [
        new Score('craftsmanship', 8),
        new Score('fidelity', 9),
        new Score('presence', 4),
    ]);
}

$next = $board->nextFor('r-4', 'costume-contest-2027'); // the least reviewed proposal r-4 may still review, or null
```

Une évaluation note chaque critère de la campagne une fois, dans son échelle, sinon elle est refusée par `InvalidInput`.
Noter de nouveau la même proposition remplace l'évaluation de l'évaluateur. `mayReview()` est faux pour un évaluateur en
conflit, pour un évaluateur non assigné quand la politique limite les évaluateurs à leurs assignations, et pour un
nouvel évaluateur une fois que la proposition a le maximum d'évaluations que la politique permet.

Ce qu'un évaluateur voit :

```php
$reviews = $board->reviewsVisibleTo('r-4', 'costume-contest-2027', 'entry-17');
$authors = $board->authorsVisibleTo('r-4', 'costume-contest-2027', 'entry-17');
```

`reviewsVisibleTo()` suit la `Visibility` de la politique : toujours, après sa propre évaluation, ou jamais.
`authorsVisibleTo()` ne donne rien dans une campagne à l'aveugle tant que la décision de la proposition n'est pas
appliquée. Cacher les noms dans le contenu de la proposition est la part de l'application.

## La réponse des auteurs

Pendant la fenêtre de réponse des auteurs, `mayRespond($author, $campaign, $proposal)` est vrai pour les auteurs de la
proposition. La réponse elle-même — un texte, un fichier — relève de l'application, qui la montre au jury.

## Le classement et les décisions

```php
use Phil\ProposalReview\Decision;

foreach ($board->standings('costume-contest-2027') as $standing) {
    // $standing->proposal, ->reviews, ->score (0 to 1, null before the first review),
    // ->enoughReviews, ->decision (pending or applied, or null)
}

$board->decide('costume-contest-2027', 'entry-17', Decision::Accepted, 'u-chair');
$board->decide('costume-contest-2027', 'entry-22', Decision::Waitlisted, 'u-chair');

$applied = $board->applyDecisions('costume-contest-2027'); // ['entry-17', 'entry-22']
```

`standings()` trie la meilleure note d'abord ; passez `\SortDirection::Ascending` pour la plus basse d'abord. Les
propositions sans note viennent en dernier dans les deux cas, et les notes égales suivent l'identifiant.

Les décisions sont consignées pendant la délibération et restent en attente — une décision consignée de nouveau
remplace celle qui est en attente — jusqu'à ce que `applyDecisions()` applique d'un coup toutes les décisions en
attente de la campagne et répartisse `DecisionsApplied`, le moment d'avertir les auteurs. Une décision appliquée est
définitive.
