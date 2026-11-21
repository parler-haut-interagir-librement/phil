<!-- phil-dev source-sha256=8fbbf6c5198743649e85d372fa1e403be0c480c0258c926a4e83548db554f2a1 -->
# Tests

## Les tests du paquet

Les tests sont des tests PHPUnit sous `tests/Unit`, lancés depuis la racine du monodépôt avec `composer test`, ou seuls :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Ils s'exécutent sans noyau, sans base de données et sans réseau. `tests/Support/MemoryTarget` est une cible qui garde
ses enregistrements en mémoire, refuse les clés qu'on lui indique et se souvient de chaque recherche et de chaque lot ;
`tests/Support/SampleRule` est la règle d'une application, relue par son nom. Dans le monodépôt, Infection maintient le
paquet à 100 % des mutants couverts.

## Tester la cible d'une application

Une cible se teste avec un plan fait à partir d'un petit texte CSV et du profil qu'elle écrit :

```php
use Phil\ImportWizard\Csv\CsvRowSource;
use Phil\ImportWizard\Import\Importer;
use Phil\ImportWizard\Lms\LmsProfiles;
use Phil\ImportWizard\Plan\ImportPlanner;

$plan = new ImportPlanner()->plan(CsvRowSource::fromString("code,name,parent_code\nsci,Sciences,\n"), LmsProfiles::organisationalUnits(), $target);
$result = new Importer()->commit($plan, $target);
```

## La cible de fuzzing

`tests/Fuzz/csv.php` lit un texte que personne n'a écrit avec `CsvRowSource`, dans de petites limites, puis le planifie
avec les trois formats de `LmsProfiles` : chaque étape retourne ou lance une `ImportWizardException`, chaque ligne garde
une ligne située dans le texte, et chaque ligne du fichier est planifiée ou refusée, jamais les deux. Ses germes sont
dans `tests/Fuzz/corpus/csv`.

```sh
composer fuzz -- csv
```

## Le banc d'essai

`tests/Benchmark/PlanBench.php` mesure les chemins chauds sur 1 000 personnes et sur une chaîne de 1 000 unités écrites
en commençant par les enfants : la lecture du fichier, l'essai à blanc et le plan écrit à travers une cible en mémoire.

```sh
composer bench
```
