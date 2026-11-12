<!-- phil-dev source-sha256=f568bbb6acd0f2ce85921bfbe1e0002515f9ac54ad1478e37ed47f47fb39852c -->
# Tests

## Comment le bundle est testé

| Suite | Dossier | A besoin de | Couvre |
|---|---|---|---|
| Unitaire | `tests/Unit` | rien hors du processus ; `pdo_sqlite` | le domaine, chaque mappeur de données, fusionneur et normaliseur, le cycle de publication sur des ports en mémoire (une locale et plusieurs), un corps de chaque format publié en UJO avec son HTML, le branchement du conteneur du bundle avec et sans DoctrineBundle et TwigBundle, les adaptateurs de balisage, et le stockage Doctrine sur SQLite en mémoire |
| Fonctionnelle | `tests/Functional` | `pdo_sqlite`, ou un serveur nommé par `PHIL_CONTENT_TEST_DATABASE_URL` | un noyau propre (FrameworkBundle, DoctrineBundle, TwigBundle, SecurityBundle, `phil/search-bundle`) : le cycle de publication par Doctrine, un corps BBCode enregistré en UJO sur la ligne en ligne, le contrôleur (200, 301, 404), l'archive, les fonctions Twig, les commandes de console, les événements de page, l'alimentation de recherche, les copies de travail et l'administration |
| Intégration | `tests/Integration` | un serveur MySQL 8 et un serveur PostgreSQL | le stockage Doctrine — mappage, types DBAL, contrainte d'unicité sur la sentinelle `locale_key` — sur de vrais serveurs ; sautée sans leurs URL |

Les tests du stockage partagent un cas abstrait, `tests/Unit/Infrastructure/Doctrine/ContentStorageTestCase.php`,
exécuté sur SQLite dans la suite unitaire et sur MySQL et PostgreSQL dans la suite d'intégration.

Le noyau fonctionnel est `tests/Functional/App/TestKernel.php` : deux genres de contenu, `news` (un identifiant
UUIDv7 qu'il génère) et `static_page` (un entier généré par la base de données), montés sous
`/{_locale}/news/{slug}` et `/{_locale}/{slug}`, le second aussi sur sa propre route, `/{_locale}/about`. Le schéma
est supprimé et recréé avant chaque test. Son cache se trouve sous le dossier temporaire du système, un conteneur par
URL de base de données.

### Variables d'environnement

| Variable | Utilisée par | Exemple |
|---|---|---|
| `PHIL_CONTENT_TEST_MYSQL_URL` | `tests/Integration` (MySQL) | `mysql://root:secret@127.0.0.1:3306/probe` |
| `PHIL_CONTENT_TEST_POSTGRESQL_URL` | `tests/Integration` (PostgreSQL) | `postgresql://user:password@127.0.0.1:5432/probe` |
| `PHIL_CONTENT_TEST_DATABASE_URL` | `tests/Functional`, au lieu de son fichier SQLite | `postgresql://user:password@127.0.0.1:5432/probe?serverVersion=17` |

DoctrineBundle lit la version d'un serveur PostgreSQL dans le `serverVersion` de l'URL. Les tests d'intégration sont
dans les groupes PHPUnit `mysql` et `postgresql`.

### Lancer les tests dans le monodépôt

Le bundle est incubé dans un monodépôt et installé dans le projet racine par un dépôt `path` ; ses tests s'exécutent
depuis le `vendor/` racine, avec la configuration PHPUnit racine :

```bash
vendor/bin/phpunit packages/content-bundle/tests                  # every suite; Integration skipped without URLs
vendor/bin/phpunit packages/content-bundle/tests/Unit
vendor/bin/phpunit packages/content-bundle/tests/Functional

PHIL_CONTENT_TEST_MYSQL_URL='mysql://root:secret@127.0.0.1:3306/probe' \
PHIL_CONTENT_TEST_POSTGRESQL_URL='postgresql://user:password@127.0.0.1:5432/probe' \
    vendor/bin/phpunit --fail-on-skipped packages/content-bundle/tests/Integration

PHIL_CONTENT_TEST_DATABASE_URL='mysql://root:secret@127.0.0.1:3306/probe' \
    vendor/bin/phpunit packages/content-bundle/tests/Functional
PHIL_CONTENT_TEST_DATABASE_URL='postgresql://user:password@127.0.0.1:5432/probe?serverVersion=17' \
    vendor/bin/phpunit packages/content-bundle/tests/Functional
```

### Lancer les tests du bundle installé seul

Le bundle n'est fourni avec aucun `phpunit.xml`. Dans une copie de travail du bundle seul :

```bash
composer install
vendor/bin/phpunit --no-configuration tests
```

`composer.json` déclare les dépendances de développement dont les suites ont besoin : `doctrine/doctrine-bundle`,
`symfony/framework-bundle` et `symfony/twig-bundle` pour le noyau fonctionnel, `symfony/event-dispatcher` pour les
événements du workflow, `symfony/uid` pour un jeu de données d'essai d'identifiant, `psr/log` pour le journaliseur nul
du noyau, et `phpunit/phpunit`. `composer-dependency-analyser.php` vérifie que les dépendances déclarées correspondent
à ce qu'utilisent `src/` et `tests/`.

## Tester une application qui utilise le bundle

### Tests unitaires sur des ports en mémoire

La couche application travaille sur six ports : elle s'exécute donc sans base de données quand ils sont en mémoire.
Les adaptateurs en mémoire propres au bundle se trouvent dans `tests/Unit/Fixture`
(`InMemoryDimensionContentRepository`, `InMemoryRouteRepository`, `InMemoryContentRootRepository`,
`InMemoryPublishedRows`, `InMemoryContentTransaction`, et `InMemoryContents`, qui branche toute la couche application
sur eux). C'est du code de test, qui ne fait pas partie de l'API chargée automatiquement : reprenez le motif dans vos
propres tests plutôt que d'en dépendre.

Un branchement minimal, pour le test d'un service de l'application :

```php
use Phil\ContentBundle\Application\Merger\{ExcerptMerger, RoutableMerger, TemplateMerger, WorkflowMerger};
use Phil\ContentBundle\Application\Normalizer\WorkflowNormalizer;
use Phil\ContentBundle\Application\Pipeline\ContentNormalizer;
use Phil\ContentBundle\Application\Publication\{ContentPublisher, ContentVersions, ContentWorkflow,
    DimensionContentCopier};
use Phil\ContentBundle\Application\Routing\RouteUpdater;
use Symfony\Component\Clock\MockClock;

$mergers = [new TemplateMerger(), new WorkflowMerger(), new ExcerptMerger(), new RoutableMerger()];
$copier = new DimensionContentCopier($mergers);
$versions = new ContentVersions($rows, $copier, $transaction);
$publisher = new ContentPublisher(
    $rows, $roots, $versions, $copier, ContentWorkflow::create(), new RouteUpdater($routes, $roots),
    $transaction, new ContentNormalizer([new WorkflowNormalizer()]), new MockClock('2026-11-02 12:00:00'),
);
```

`$rows`, `$roots`, `$routes` et `$transaction` sont vos implémentations en mémoire des ports
([extension-points.md](extension-points.md)). `ContentPublisher` prend une horloge : une `MockClock` fixe les dates de
publication. Passez un `EventDispatcher` à `ContentWorkflow::create()` pour exercer vos protections.

### Tests sur noyau

Dans un `KernelTestCase` ou un `WebTestCase` de l'application, les services sont sous leur nom de classe : obtenez
`ContentManager` du conteneur de test, créez et publiez du contenu, puis demandez son URL.

```php
final class NewsPageTest extends WebTestCase
{
    public function testAPublishedNewsIsServed(): void
    {
        $client = self::createClient();
        $contents = self::getContainer()->get(ContentManager::class);

        $news = $contents->create('news', 'fr', ['templateKey' => 'news', 'slug' => 'opening', 'excerptTitle' => 'Opening']);
        $contents->publish($news, 'fr');

        $client->request('GET', '/fr/news/opening');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Opening');
    }
}
```

Créez le schéma avant le test (`SchemaTool` ou vos migrations), comme le fait le `FunctionalTestCase` du bundle.
Chaque écriture de `ContentManager` valide sa propre transaction (imbriquée dans la vôtre quand une est ouverte) :
enveloppez donc le test dans une transaction que vous annulez, ou reconstruisez le schéma, pour isoler les tests.

Le noyau propre du bundle (`tests/Functional/App/TestKernel.php`) enregistre aussi SecurityBundle avec trois
utilisateurs en mémoire, l'administration derrière le rôle `ROLE_EDITOR`, `phil/search-bundle` sur son adaptateur
`dbal`, et un enregistreur des événements de page : `ContentAdminTest` se connecte par le fournisseur d'utilisateurs du
noyau et soumet les formulaires de l'administration avec leurs jetons CSRF ; `SearchFeedTest` cherche ce qu'une
publication a indexé ; `PageEventsTest` relit les événements répartis. Une application teste un écouteur des événements
de page de la même façon, avec un service écouteur qui garde ce qu'il reçoit.

### Tests de console

Les commandes sont des commandes Symfony ordinaires : exécutez-les avec `CommandTester` (ou `ApplicationTester`) et
vérifiez la sortie et le code de retour. `phil:content:create -q` affiche la référence seule, qu'un test peut relire.

## Bancs d'essai

`BodyNormalizerBench` purifie des corps de trois longueurs ; `PublishedContentsBench` et `ContentUrlProviderBench`
lisent 10, 100 et 1 000 articles publiés sur SQLite en mémoire. Depuis la racine du monodépôt :

```sh
composer bench -- packages/content-bundle/tests/Benchmark
```
