<!-- phil-dev source-sha256=407aa5e27112f0dbc3889d29af878f30c53fcf51747098e912a6392da976b7ac -->
# phil/proposal-review

**Un seul moteur d'évaluation par jury pour les appels de propositions, les candidatures à un cours et les concours :
phases datées, critères pondérés, évaluation à l'aveugle avec conflits d'intérêts, décisions par lot, et un mode de vote
par paires noté par Bradley–Terry.**

phil/proposal-review conduit l'évaluation de propositions par un jury. Une campagne a un calendrier daté — dépôt,
évaluation, réponse facultative des auteurs, délibération — qui ouvre et ferme chaque action selon l'horloge ; des
critères pondérés, chacun sur sa propre échelle ; et une politique : à l'aveugle ou non, combien d'évaluations une
proposition exige et peut recevoir, moyenne ou médiane, quand un évaluateur voit les évaluations des autres. Les
évaluateurs sont assignés à la main ou de façon équilibrée et automatique ; un évaluateur ne voit ni ne note jamais une
proposition qu'il a écrite ou avec laquelle il a déclaré un conflit. « Enregistrer et suivant » envoie chaque évaluateur
vers la proposition la moins évaluée qu'il peut évaluer. Le classement ordonne les propositions ; le jury consigne ses
décisions pendant la délibération, puis les applique toutes d'un coup.

À côté des notes, une campagne peut recueillir des votes par paires : un votant voit deux propositions et en choisit une.
Les paires sont tirées pour équilibrer l'exposition, ne se répètent jamais pour un votant et évitent les conflits ; les
votes vont dans un journal en ajout seul, d'où le modèle de Bradley–Terry calcule la force de chaque proposition, la
même chaque fois pour le même journal.

Elle sert à toute application PHP qui demande à un jury de choisir : les conférences d'un congrès, les candidats à un
cours ou à une bourse, les participants à un concours de costumes, d'écriture ou de jeux. Les propositions, les
évaluateurs et les auteurs sont les identifiants stables de l'application ; leur contenu, leurs commentaires et leurs
écrans restent dans l'application. C'est une bibliothèque PHP simple qui repose sur `psr/clock` et
`psr/event-dispatcher`, avec des magasins derrière des ports, des adaptateurs en mémoire et sur Doctrine DBAL ; chaque instant est gardé en
UTC.

## État

| Partie | État |
|---|---|
| Campagnes, calendriers datés, critères pondérés, politiques d'évaluation | Implémenté et testé |
| Propositions, assignation manuelle et équilibrée, conflits d'intérêts par proposition ou par auteur | Implémenté et testé |
| Évaluations dans l'échelle, une par évaluateur, remplacée quand on note de nouveau ; « enregistrer et suivant » | Implémenté et testé |
| Évaluation à l'aveugle, visibilité des évaluations des autres, fenêtre de réponse des auteurs | Implémenté et testé |
| Classement par moyenne ou médiane, trié par `\SortDirection` | Implémenté et testé |
| Décisions consignées pendant la délibération, appliquées ensemble | Implémenté et testé |
| Vote par paires : paires équilibrées, journal en ajout seul, notes de Bradley–Terry | Implémenté et testé |
| Événements pour les changements de phase, les évaluations, les décisions et les votes | Implémenté et testé |
| Magasins en mémoire | Implémenté et testé |
| Magasins Doctrine DBAL : tables tirées de leur propre schéma, identifiants comparés octet par octet, votes numérotés sous concurrence | Implémenté et testé |
| Enchères d'évaluateurs, fils de discussion, commentaires | Non prévu ici : les commentaires relèvent de l'application ou d'un paquet de commentaires |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, la déclaration des services dans Symfony. |
| [Utilisation](doc/fr/usage.md) | Une campagne du dépôt aux décisions, étape par étape. |
| [Vote par paires](doc/fr/pairwise-voting.md) | Tirer des paires, voter, lire les notes. |
| [Algorithmes](doc/fr/algorithms.md) | Les notes et leur agrégation, l'assignation équilibrée, « enregistrer et suivant », le tirage des paires, Bradley–Terry, et leurs garanties. |
| [Stockage](doc/fr/storage.md) | Les quatre ports, les adaptateurs en mémoire et sur Doctrine DBAL, et ce qu'un adaptateur doit conserver. |
| [Événements](doc/fr/events.md) | Ce que le moteur répartit, et quand. |
| [Sécurité](doc/fr/security.md) | Les phases comme permissions, les conflits, l'aveugle, les identifiants, les bornes. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application peut remplacer ou ajouter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests et le banc d'essai du paquet, et comment tester du code qui s'en sert. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/proposal-review
```

```php
use Phil\ProposalReview\Campaign;
use Phil\ProposalReview\Criterion;
use Phil\ProposalReview\Memory\InMemoryCampaignStore;
use Phil\ProposalReview\Memory\InMemoryProposalStore;
use Phil\ProposalReview\Memory\InMemoryReviewStore;
use Phil\ProposalReview\Phase;
use Phil\ProposalReview\PhaseWindow;
use Phil\ProposalReview\ReviewBoard;
use Phil\ProposalReview\Schedule;
use Phil\ProposalReview\Score;
use Symfony\Component\Clock\NativeClock;

$campaigns = new InMemoryCampaignStore();
$campaigns->save(new Campaign(
    'cfp-2027',
    new Schedule([
        new PhaseWindow(Phase::Submission, new DateTimeImmutable('2027-01-04'), new DateTimeImmutable('2027-02-01')),
        new PhaseWindow(Phase::Review, new DateTimeImmutable('2027-02-01'), new DateTimeImmutable('2027-02-22')),
        new PhaseWindow(Phase::Deliberation, new DateTimeImmutable('2027-02-22'), new DateTimeImmutable('2027-03-01')),
    ]),
    [new Criterion('content', weight: 2), new Criterion('clarity')],
));

$board = new ReviewBoard($campaigns, new InMemoryProposalStore(), new InMemoryReviewStore(), new NativeClock());

$board->submit('cfp-2027', 'talk-81', authors: ['u-1042']);        // during submission
$board->assignBalanced('cfp-2027', ['r-1', 'r-2', 'r-3', 'r-4'], 3);
$board->review('cfp-2027', 'talk-81', 'r-2', [new Score('content', 4), new Score('clarity', 5)]); // during review
$next = $board->nextFor('r-2', 'cfp-2027');                         // "save and next"
$standings = $board->standings('cfp-2027');                         // best first
```

## Prérequis

PHP 8.5 ou une version plus récente, `psr/clock`, `psr/event-dispatcher` et `symfony/polyfill-php86` pour
`\SortDirection` avant PHP 8.6. Une horloge comme `symfony/clock`, et un répartiteur d'événements pour écouter les
événements. `doctrine/dbal` 4.5 pour les magasins en base de données.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
