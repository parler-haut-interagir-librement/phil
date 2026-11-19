<!-- phil-dev source-sha256=aca6f1d563302add3b1cfcc50d4776c2a7ac9a853c2f7df296722d9e2a626e77 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans serveur de base de données et sans réseau, sur les magasins en
mémoire et le `MockClock` de Symfony. Elle teste les objets-valeurs et leurs refus ; les phases aux bords de leurs
fenêtres ; chaque action dans sa phase et hors d'elle ; les conflits par proposition et par auteur ; l'assignation
manuelle et équilibrée ; le maximum d'évaluations et la règle des assignés seulement ; « enregistrer et suivant » ; les
trois visibilités et l'aveugle ; le classement par moyenne et médiane dans les deux sens ; les décisions consignées,
remplacées, appliquées ensemble et définitives ; les événements ; les magasins ; le tirage des paires, son équilibre et
ses conflits ; et l'ajustement de Bradley–Terry — son point fixe, une proposition invaincue, l'ordre du journal, les
bornes d'itération. Les magasins DBAL exécutent le même contrat sur SQLite en mémoire, avec leurs tables, leurs
préfixes, des lignes qu'ils n'ont pas écrites, et une autre requête qui prend d'abord le numéro d'un vote ou une
assignation.

La suite d'intégration, sous `tests/Integration`, exécute ce contrat sur MySQL et PostgreSQL quand
`PHIL_TEST_MYSQL_URL` ou `PHIL_TEST_POSTGRESQL_URL` donne un serveur, et elle est ignorée autrement. Dans le monodépôt,
Infection maintient le paquet à 100 % des mutants couverts.

## Le banc d'essai

`tests/Benchmark/ReviewBench.php` mesure le classement de cinq cents propositions avec trois évaluations de quatre
critères chacune, l'ajustement de Bradley–Terry de deux cents propositions sur vingt mille votes, et la paire suivante
d'un votant qui a jugé cinq cents paires, dans ce journal. Dans le monodépôt, `composer bench` l'exécute.

## Tester du code qui utilise le moteur

Construisez le tableau d'évaluation sur les magasins en mémoire et une horloge simulée, et avancez l'horloge jusqu'à la
phase dont le test a besoin :

```php
use Phil\ProposalReview\Memory\InMemoryCampaignStore;
use Phil\ProposalReview\Memory\InMemoryProposalStore;
use Phil\ProposalReview\Memory\InMemoryReviewStore;
use Phil\ProposalReview\ReviewBoard;
use Symfony\Component\Clock\MockClock;

$clock = new MockClock('2027-01-10 09:00:00 UTC');
$campaigns = new InMemoryCampaignStore();
$campaigns->save($campaign);
$board = new ReviewBoard($campaigns, new InMemoryProposalStore(), new InMemoryReviewStore(), $clock);

$board->submit($campaign->id, 'talk-1', ['u-1']);
$clock->modify('2027-02-05 09:00:00 UTC'); // into the review window
```

Pour vérifier les événements, passez un répartiteur qui enregistre ce qu'il reçoit.
