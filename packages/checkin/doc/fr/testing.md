<!-- phil-dev source-sha256=feeaa377589ae82ffc7c12ec82467dd9f57ed790f19226286a73e45a3e70d09d -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste les identifiants, les codes et les empreintes et leurs bornes ; les titres d'accès et les
points de contrôle qu'ils ouvrent ; chaque règle de la politique à sa seconde limite ; la fréquentation et ses
transitions ; les invariants d'un enregistrement ; le guichet — codes inconnus, admissions, refus, sorties et nouvelles
entrées, rejeux, courses décidées de nouveau et la contention devant laquelle il abandonne — ; le registre en mémoire ;
les manifestes, leurs tranches, leurs libellés et leur limite ; la lecture des journaux et chaque lecture mal formée ;
et la synchronisation, son ordre, ses conflits, ses doublons, ses bornes et ses courses.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/checkin/tests
```

La bibliothèque est tenue par Infection à un score de mutation couverte de 100 %.

## La cible de fuzzing

`tests/Fuzz/offline-batch.php` donne à `OfflineBatch::read()` du JSON que personne n'a écrit, à partir des germes de
`tests/Fuzz/corpus/offline-batch` : quoi qu'il lise, il retourne des lectures ou lève `InvalidOfflineBatch`.
`composer fuzz`, depuis la racine du monodépôt, l'exécute.

## Le banc d'essai

`tests/Benchmark/CheckInDeskBench.php` mesure une lecture décidée et inscrite en mémoire, la décision seule, une
empreinte, un manifeste de 10 000 titres d'accès et un journal de 500 lectures. `composer bench`, depuis la racine du
monodépôt, l'exécute avec les autres bancs d'essai.

## Tester un enregistrement

Construisez le guichet sur le registre en mémoire et une horloge figée, comme `MockClock` de Symfony, avec un résolveur
de quelques titres d'accès :

```php
use Phil\Checkin\CheckInDesk;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\CredentialCode;
use Phil\Checkin\DeviceId;
use Phil\Checkin\InMemoryCheckInLedger;
use Phil\Checkin\ScanOutcome;
use Symfony\Component\Clock\MockClock;

$ledger = new InMemoryCheckInLedger();
$desk = new CheckInDesk($resolver, $ledger, new MockClock('2026-11-08 10:00:00'));

$desk->scan(CredentialCode::of('T-1001'), CheckpointId::of('hall'), DeviceId::of('door-1'));
$again = $desk->scan(CredentialCode::of('T-1001'), CheckpointId::of('hall'), DeviceId::of('door-2'));

self::assertSame(ScanOutcome::AlreadyAdmitted, $again->outcome);
self::assertCount(2, $ledger->all());
```
