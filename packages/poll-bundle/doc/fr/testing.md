<!-- phil-dev source-sha256=c5ec0f43b5b000a973df7008555bc192282c69108e0ccb57f8a54128daac9c4e -->
# Tests

## Les tests du bundle

La suite `tests/Unit` teste le domaine — les bornes d'un sondage et de ses options, l'ouverture et la fermeture à la
seconde près, la fermeture et le changement de dates, les bulletins nominatifs et anonymes — ; `Polls` sur les
stockages en mémoire et une horloge simulée — la politique consultée à chaque action, les bulletins déposés, modifiés
et retirés, les bulletins anonymes modifiés par reçu, un deuxième bulletin refusé, la visibilité des résultats dans
chaque cas, les événements, les dépouillements gardés dans un cache jusqu'au prochain bulletin — ; le vote unique
transférable — l'élection de la nourriture publiée dans l'article de Wikipédia en anglais, les votes et la valeur
épuisée qui s'additionnent à chaque tour, les transferts, les égalités, les bornes — ; les vues, la lecture d'un
formulaire soumis et les fonctions Twig ; les stockages Doctrine et en mémoire contre un même contrat partagé, sur
SQLite en mémoire, ceux en mémoire vidés par une réinitialisation ; le mappage des trois tables ; et la configuration
et les services du bundle. La suite `tests/Functional` démarre un noyau avec DoctrineBundle sur SQLite et TwigBundle,
fait tourner un sondage anonyme à bulletins classés, dépouillé par une méthode de l'application et entendu par un
écouteur, et dessine les gabarits en anglais et en français.

Le contrôleur Stimulus est testé avec l'exécuteur de tests de Node :

```sh
node --import ./packages/poll-bundle/tests/js/register.mjs --test packages/poll-bundle/tests/js
```

Le dépouillement selon la méthode de Meek est une cible de fuzzing, `meek-count`, lancée par `composer fuzz` ;
`tests/Benchmark/ElectionBench.php` le mesure sur dix mille bulletins, ainsi que les résultats lus dans le cache.

À partir de la racine du monodépôt :

```sh
vendor/bin/phpunit packages/poll-bundle/tests
```

Infection exige du bundle un score de mutation couverte de 100 %.

## Tester du code qui utilise des sondages

Configurez le stockage `memory` dans l'environnement de test et figez l'horloge :

```yaml
# config/packages/test/phil_poll.yaml
phil_poll:
    store: memory
```

```php
$clock->modify('2026-11-08 18:00:00 UTC');
$polls->cast($poll->id, ResourceReference::of('user', '8'), ['sun']);

self::assertSame(['sun'], $polls->results($poll->id)->winners());
```

Vous pouvez aussi construire `Polls` directement sur `InMemoryPollRepository`, `InMemoryVoteRepository`, le
`MockClock` de Symfony, une politique et `CountingMethods::defaults()`.
