<!-- phil-dev source-sha256=d013c7b96fa5146a9624a5fc3e48c07fb3707005de17a6a5acc66bce5134e305 -->
# Tests

Les tests sont des tests PHPUnit dans `tests/Unit`, lancés depuis la racine du monodépôt par `composer test`, ou
seuls :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

- L'adaptateur BigBlueButton est testé contre le `MockHttpClient` de Symfony : chaque somme de contrôle est recalculée
  et vérifiée, chaque réponse est un document que le test écrit, et les notifications sont signées avec le secret du
  test.
- Les jetons de Jitsi sont vérifiés comme le ferait un serveur : le HMAC sous le secret de l'application, la signature
  RSA sous la clé publique d'une paire de clés tirée pour le test.
- L'horloge est le `MockClock` de Symfony, à 1 700 000 000.

## Cible de fuzzing

`responses`, dans `tests/Fuzz/responses.php`, lancée par `composer fuzz` : son premier octet choisit le lecteur — les
enregistrements ou les présences que répond BigBlueButton, son assiduité, une notification Jitsi signée avec le secret
de la cible —, le reste est le corps. Quoi qu'il contienne, le lecteur aboutit à un résultat ou à une
`VirtualClassroomException`. Les germes sont dans `tests/Fuzz/corpus/responses`.

## Banc d'essai

`tests/Benchmark/ClassroomBench.php`, lancé par `composer bench` : un lien d'accès BigBlueButton, un lien d'accès
Jitsi, l'assiduité de 500 participants postée à la fin d'une séance, et la part d'une séance de 200 séjours.
