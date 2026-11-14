<!-- phil-dev source-sha256=833f453522b8e05c01d23bfd0ab749c81ccd8a2dae986a50375225e8e1b42ab9 -->
# Tests

## Les tests du bundle

La suite `tests/Unit` teste le domaine — les bornes d'un sondage et de ses options, l'ouverture et la fermeture à la
seconde près, la fermeture et le changement de dates, les bulletins nominatifs et anonymes — ; `Polls` sur les
stockages en mémoire et une horloge simulée — la politique consultée à chaque action, les bulletins déposés, modifiés
et retirés, les bulletins anonymes modifiés par reçu, un deuxième bulletin refusé, la visibilité des résultats dans
chaque cas, les événements, les dépouillements gardés dans un cache jusqu'au prochain changement — ; les dépouillements
gardés dans un vrai pool sur les stockages Doctrine, sur SQLite — une nouvelle révision après chaque bulletin déposé,
modifié ou retiré, chaque fermeture et chaque nouveau calendrier, les dépouillements d'un sondage supprimé retirés, la
durée de vie respectée, et ni votant, ni reçu, ni empreinte d'un reçu dans ce qui est gardé d'un sondage anonyme — ; le
vote unique transférable — l'élection de la nourriture publiée dans l'article de Wikipédia en anglais, les votes et la valeur
épuisée qui s'additionnent à chaque tour, les transferts, les égalités, les bornes — ; les vues, la lecture d'un
formulaire soumis et les fonctions Twig ; les stockages Doctrine et en mémoire contre un même contrat partagé, sur
SQLite en mémoire, ceux en mémoire vidés par une réinitialisation ; le mappage des trois tables ; et la configuration
et les services du bundle. La suite `tests/Functional` démarre un noyau avec DoctrineBundle sur SQLite, TwigBundle et
TwigComponentBundle, avec les recettes du kit PHIL UX copiées là où le Toolkit les installe, fait tourner un sondage anonyme à bulletins classés,
dépouillé par une méthode de l'application et entendu par un écouteur, et dessine les gabarits en anglais et en
français, leurs blocs remplacés par un gabarit qui les intègre ; puis les dessine de nouveau avec les recettes du kit Shadcn du
Symfony UX Toolkit.

La suite `tests/Browser` ouvre les pages de cette application dans Chrome, par Panther et le chromedriver du système,
avec chaque kit : chaque sorte de bulletin remplie et envoyée au clavier seulement, un bulletin blanc, un bulletin
refusé et son alerte, le formulaire désactivé pour une personne non connectée et une fois le sondage fermé, et les noms
et les rôles que calcule Chrome pour les groupes, les contrôles et les barres. Elle tourne à part, avec les autres
suites de navigateur :

```sh
composer test:browser
```

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
