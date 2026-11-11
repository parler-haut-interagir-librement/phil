<!-- phil-dev source-sha256=d07d335f2d8612d13207b16d94142ba11383c2f187b5d66f1e44b904369b7a19 -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste les résultats et les bornes de leurs textes ; les décisions de la politique ; les
analyseurs nul et factice ; chaque réponse de clamd que le lecteur connaît, et beaucoup qu'il ne connaît pas ; le
connecteur sur un vrai socket Unix et un vrai port TCP. Elle teste aussi l'analyseur clamd contre un clamd scripté qui
note chaque octet envoyé (le découpage des morceaux, la limite de taille à l'octet près, une connexion fermée avant,
pendant et après le fichier, des écritures acceptées en partie) et contre un vrai socket qui ne répond jamais, pour les
délais d'attente.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/upload-scan/tests
```

La bibliothèque est tenue à un score de mutation couverte de 100 % par Infection.

`tests/Integration/ClamdTest.php` s'exécute contre un vrai clamd à l'adresse de `PHIL_UPLOAD_SCAN_CLAMD`, et est sauté
sans elle :

```sh
docker run -d --name clamav -p 3310:3310 clamav/clamav:stable
PHIL_UPLOAD_SCAN_CLAMD=tcp://127.0.0.1:3310 vendor/bin/phpunit packages/upload-scan/tests/Integration
```

## La cible de fuzzing

`tests/Fuzz/clamd-response.php` donne au lecteur des réponses de clamd des octets que personne n'a écrits, à partir des
germes de `tests/Fuzz/corpus/clamd-response` : il doit répondre par un résultat dont le texte est de l'ASCII
imprimable, sans jamais lancer d'exception ni d'avertissement. `composer fuzz` l'exécute.

## Le banc d'essai

`tests/Benchmark/ClamdScannerBench.php` mesure ce que la bibliothèque ajoute à chaque téléversement : un fichier de
1 Mio découpé et envoyé à un clamd scripté qui répond aussitôt, l'analyseur factice sur le même fichier, et une réponse
lue. `composer bench` l'exécute avec les autres bancs d'essai.

## Tester du code qui analyse

Donnez au code un `FakeScanner` plutôt qu'un clamd :

```php
use Phil\UploadScan\FakeScanner;

$scanner = new FakeScanner(['MALICIOUS' => 'Test.Malicious']);

$scanner->scanPath($upload);                  // infected when the file holds the EICAR string or MALICIOUS
new FakeScanner(error: 'down')->scanPath($upload);     // an error result, for the policy
new FakeScanner(reachable: false)->version();          // null, for a health check
```

`FakeScanner::eicar()` est la chaîne de test EICAR, que chaque antivirus, clamd compris, signale comme une signature de
test : écrivez-la dans un fichier pour tester un refus de bout en bout. `NullScanner` trouve chaque fichier sain, pour
les tests que cela ne concerne pas.
