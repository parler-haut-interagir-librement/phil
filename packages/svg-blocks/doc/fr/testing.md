<!-- phil-dev source-sha256=328e93a32a7e33367fba2ec9e312e0fad2d6744b118044758fdf84313be5618f -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, dessine, conserve, redessine et refuse des dessins, signe et vérifie des clés, et
appelle l'adaptateur Mermaid par le `MockHttpClient` de Symfony. Dans le monodépôt, Infection maintient le paquet à
100 % de mutants couverts.

`tests/Integration` appelle le vrai service Mermaid à `PHIL_MERMAID_URL`, et est sauté sans lui :

```sh
docker build -t phil/mermaid-service vendor/phil/svg-blocks-bundle/docker/mermaid
docker run --rm -p 3000:3000 phil/mermaid-service
PHIL_MERMAID_URL=http://localhost:3000 vendor/bin/phpunit --no-configuration tests/Integration
```

## Banc d'essai

`tests/Benchmark/StoredMarkupBench.php` mesure le rendu d'un document de trois dessins, le chemin critique de la
lecture, et l'enregistrement d'un document inchangé.

## Tester du code qui dessine des blocs

Donnez à `BlockMarkup` vos propres moteurs de rendu — un `MarkupRenderer` qui retourne un balisage fixe — et un
`Signer` avec un secret de test.
