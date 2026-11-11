<!-- phil-dev source-sha256=2956a3891274f77528f2ba45a77bef1d965fc8424d1341282100aa6da80e010a -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste les identifiants, les clés et leurs bornes ; le repliement et les bornes des titres et des
lieux ; chaque type de critère, ses bornes et la correspondance des choix, des plages et des indicateurs, ainsi que
d'ensembles entiers de critères ; les politiques, leurs valeurs par défaut et chaque borne ; chaque transition d'une
annonce, à la seconde près (soumission sous les deux modérations, approbation et rejet, signalements qui masquent une
annonce et sa restauration, modifications, expiration, rappels, prolongations à partir de l'expiration ou de maintenant,
fermeture et retrait) et chaque transition que son statut refuse ; les réponses et leurs transitions ; et les refus du
répondeur dans leur ordre, sur des ports en mémoire et une horloge figée.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/listings/tests
```

La bibliothèque est tenue à un score de mutation couvert de 100 % par Infection.

## Le banc d'essai

`tests/Benchmark/ListingsBench.php` mesure ce que coûte une recherche par annonce lue (la correspondance de six critères
avec quatre critères recherchés, avec et sans la vérification de visibilité) et ce que coûte un formulaire soumis : un
titre et un ensemble de critères. `composer bench`, depuis la racine du monodépôt, l'exécute avec les autres bancs
d'essai.

## Tester du code construit sur la bibliothèque

Les annonces et les réponses n'ont besoin d'aucune horloge : donnez à chaque transition l'instant du test. Le
`Responder` prend une horloge PSR-20, comme le `MockClock` de Symfony, et trois ports qui tiennent en quelques lignes
chacun en mémoire :

```php
use Phil\Listings\KindPolicies;
use Phil\Listings\KindPolicy;
use Phil\Listings\ListingKind;
use Phil\Listings\Responder;
use Symfony\Component\Clock\MockClock;

$clock = new MockClock('2026-03-14 10:30:00');
$partners = KindPolicy::of(ListingKind::of('partner'), minimumAge: 18);
$responder = new Responder(new KindPolicies([$partners]), $noBlocks, $agesByMember, $memoryStore, $clock);

$clock->sleep(30 * 86400);
self::assertSame(Refusal::NotOpen, $responder->refusal($listing, $member));
```
