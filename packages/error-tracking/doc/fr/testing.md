<!-- phil-dev source-sha256=87bed2a3da5fa85d0255bad6cd4418bcd902d8666971aff3fc49876da51aa9d7 -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste les cadres et les noms qu'ils donnent aux fonctions anonymes et aux classes anonymes ; les
bornes de chaque texte ; le résumé de la requête et son adresse anonymisée ; les événements, leurs valeurs simples et
ce que leur relecture refuse ; la fabrique sur de vrais throwables, chaînes et traces, dans le projet et hors de lui ;
le normaliseur sur chaque sorte de valeur ; l'empreinte, ce qui la garde et ce qui la change ; l'assainisseur sur les
en-têtes, les étiquettes, les adresses et les textes ; l'échantillonneur sur un moteur fixe ; chaque transition d'un
groupe ; et le traqueur sur un magasin en mémoire, un limiteur qui compte et un alerteur qui enregistre.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/error-tracking/tests
```

La bibliothèque est tenue par Infection à un score de mutation couverte de 100 %.

## Les bancs d'essai

`tests/Benchmark/FingerprintBench.php` mesure l'empreinte d'une chaîne de deux exceptions de quarante cadres et le
normaliseur sur un message ; `tests/Benchmark/ScrubberBench.php` mesure l'assainisseur sur un événement de vingt
en-têtes, dix étiquettes et deux messages, et sur un texte. `composer bench`, depuis la racine du monodépôt, les lance
avec les autres bancs d'essai.

## Tester du code qui capture des erreurs

Construisez le traqueur sur un magasin en mémoire (un tableau de groupes par empreinte et une liste d'événements) et
une horloge figée comme le `MockClock` de Symfony, puis faites vos assertions sur la `Capture` qu'il retourne ou sur
ce que contient le magasin :

```php
$capture = $tracker->captureThrowable(new RuntimeException('Order 12 failed'));

self::assertSame(Occurrence::First, $capture?->occurrence);
self::assertSame('RuntimeException: Order 12 failed', $store->groups[$capture->fingerprint]->title);
```
