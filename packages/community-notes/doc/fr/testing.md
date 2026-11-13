<!-- phil-dev source-sha256=3d8803af9fb4fa2ad036f377469bb36d782a4238642427630923f7b3d25ac6e4 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau. Elle teste la règle —
notes publiées par deux camps et non par un seul, décidées non utiles, trop peu d'évaluations, aucune majorité nette,
chaque évaluateur dans une seule paire, les plus anciens évaluateurs d'abord, les bornes — ; `CommunityNotes` sur les
stockages en mémoire et une horloge simulée — rédiger, évaluer de nouveau, les refus, retirer, décider de nouveau, les
événements — ; les données d'un membre exportées et effacées ; la qualification par Symfony Security ; et le message
pour décider de nouveau. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

`tests/Benchmark/CrossAgreementBench.php` mesure une décision parmi cinquante évaluateurs sur dix mille évaluations,
pour deux paires, dix paires, et aucune paire trouvée.

## Tester du code qui utilise des notes

Construisez `CommunityNotes` sur `InMemoryNoteRepository`, `InMemoryRatingRepository`, le `MockClock` de Symfony et une
qualification du test, avec une règle assez petite pour quelques évaluateurs :

```php
$notes = new CommunityNotes(
    new InMemoryNoteRepository(),
    new InMemoryRatingRepository(),
    $qualification,
    new MockClock('2026-11-12 10:00:00 UTC'),
    new CrossAgreementRule(minRatings: 2, pairs: 1, minShared: 1),
);
```

Les évaluateurs qui divergent d'ordinaire se fabriquent en faisant d'abord évaluer des notes antérieures les uns contre
les autres.
