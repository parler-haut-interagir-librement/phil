<!-- phil-dev source-sha256=1c2534606c5931b9841a4e65397a67588e6770ea077abbdff51ecfacc666ac0a -->
# Tests

## Comment le bundle est testé

Chaque type implémenté a un test unitaire PHPUnit 13 sous `tests/Unit/`, à l'image de `src/` :

| Test | Couvre |
|---|---|
| `tests/Unit/PhilAnalyticsBundleTest.php` | L'alias de l'extension est `phil_analytics` ; sans DoctrineBundle, le stockage `dbal` n'a pas de connexion |
| `tests/Unit/Infrastructure/DependencyInjection/AnalyticsServicesTest.php` | Ce que configure chaque bloc de `phil_analytics` : les adaptateurs par défaut, les services de l'application à leur place, les tâches, et chaque configuration refusée |
| `tests/Unit/Domain/ValueObject/*Test.php` | Chaque objet-valeur : entrées acceptées et refusées, normalisation, égalité |
| `tests/Unit/Domain/Collection/AdmissionRuleTest.php` | L'ordre fixe de l'admission, y compris le refus avant tout le reste et le verdict strict |
| `tests/Unit/Domain/Source/ReferrerClassifierTest.php` | Les six classes de sources et `isVisit()` |
| `tests/Unit/Domain/Device/DeviceClassTest.php` | Des agents utilisateurs par classe, y compris les cas `Tablet PC` et en minuscules |
| `tests/Unit/Domain/Country/GeoDatabaseFreshnessTest.php` | La limite d'âge, et une date de construction dans le futur |
| `tests/Unit/Domain/Reading/SmallNumberSuppressionTest.php` | Les compteurs montrés, regroupés et cachés, le compteur `(other)` stocké |
| `tests/Unit/Domain/Reading/LinkCountReaderTest.php` | Qui lit le compte de chaque lien |
| `tests/Unit/Domain/Measure/*Test.php` | Les listes fermées, les objectifs, les paires de routes, les termes et hôtes refusés |
| `tests/Unit/Application/Measure/MeasureCountersTest.php` | Les compteurs de chaque événement ; des déchets dans chaque champ ne comptent rien |
| `tests/Unit/Domain/{Retention,Sharing,Report,Alert}/*Test.php` | La borne de rétention, les jetons et la révocation, les périodes des rapports, les pics et les creux |
| `tests/Unit/Application/Collection/CollectorTest.php` | L'ordre de la collecte : les refus avant le limiteur, les issues, les corps déchets, les compteurs qu'une page vue, un événement et un verdict robot font avancer |
| `tests/Unit/Application/{Report,Retention}/*Test.php` | Les rapports dus un jour donné, la borne du cumul |
| `tests/Unit/Infrastructure/Doctrine/DbalCountersTest.php` | Les incrémentations, les lectures, le retrait d'un site et le cumul, sur SQLite en mémoire |
| `tests/Unit/Infrastructure/{Salt,RateLimiter,Collection,BrokenFlow,Scheduler,Messenger}/*Test.php` | Le sel réclamé une fois puis oublié, le limiteur fermé en cas d'échec, les adaptateurs par défaut, l'historique des objectifs, l'horaire et les *handlers* |

Chaque classe de test déclare `#[CoversClass]` pour les types qu'elle couvre. Les tests unitaires n'utilisent ni
conteneur ni réseau ; les instants viennent d'une `MockClock`, et les adaptateurs DBAL tournent sur SQLite en mémoire.
Les ports n'ont pas de test propre : ce sont des interfaces.

Une suite fonctionnelle sous `tests/Functional/` démarre un vrai noyau, `tests/Functional/App/TestKernel.php`
(FrameworkBundle, DoctrineBundle sur un fichier SQLite, et le bundle sous le bloc `phil_analytics` de chaque test), avec
sa cache dans un répertoire à lui sous le répertoire temporaire du système. `BundleRegistrationTest` fixe l'alias, la
référence qu'affiche `config:dump-reference phil_analytics`, l'échec de construction que cause une clé inconnue, et un
noyau sans DoctrineBundle qui démarre sans compter ; `ApplicationWiringTest` utilise les services du domaine que le
bundle configure d'après sa configuration ; `CollectionTest` démarre le bundle avec ses adaptateurs par défaut et un
site déclaré, envoie des pages vues à la route `phil_analytics_collect`, et relit les compteurs. Elle ne demande ni
serveur ni variable d'environnement : `vendor/bin/phpunit packages/analytics-bundle/tests/Functional`.

Les tests utilisent `Phil\BotDetectionBundle\Domain\Verdict\Verdict` et `Phil\DddBundle\Domain\Exception\InvalidValue`
des bundles voisins, qui sont donc nécessaires pour les lancer.

## Lancer les tests

### Depuis la racine du monodépôt

Les tests du bundle tournent sur le `vendor/` de la racine et la configuration PHPUnit de la racine :

```bash
vendor/bin/phpunit packages/analytics-bundle/tests
```

Le script de collecte tourne sous l'exécuteur de tests de Node, Stimulus remplacé par une doublure par
`tests/js/register.mjs` :

```bash
node --import ./packages/analytics-bundle/tests/js/register.mjs --test packages/analytics-bundle/tests/js
```

### Le bundle installé seul

C'est ce que voit un projet qui installe le bundle, et cela attrape une dépendance que le bundle utilise sans la
déclarer. Le bundle n'a pas de `phpunit.xml` propre : lancez PHPUnit sans configuration :

```bash
cp -R packages/analytics-bundle /tmp/analytics-bundle && cd /tmp/analytics-bundle
rm -rf vendor composer.lock
# phil/ddd-bundle and phil/bot-detection-bundle are not on Packagist yet: point Composer at the monorepo
composer config repositories.siblings '{"type": "path", "url": "/path/to/monorepo/packages/*", "options": {"symlink": false}}'
composer install
vendor/bin/phpunit --no-configuration tests
```

Le monodépôt fait exactement cela pour chaque bundle avec `composer packages`, qui lance aussi
`composer-dependency-analyser` avec le `composer-dependency-analyser.php` du bundle. Ce fichier déclare les intégrations
facultatives — Doctrine, Symfony Lock, Symfony Scheduler, `phil/broken-flow-alerts` —, requises en développement
seulement et suggérées.

### Portes de qualité

Dans le monodépôt, le code du bundle passe aussi par l'analyse statique, les règles d'architecture, la norme de codage
et les tests de mutation du dépôt. Aucune ne demande de configuration dans le bundle.

## Tester vos adaptateurs

Les ports sont assez petits pour que des adaptateurs en mémoire tiennent en quelques lignes chacun. Servez-vous-en pour
tester votre point de collecte et vos tableaux de bord sans base de données :

```php
final class InMemoryCounterStore implements CounterStore
{
    /** @var array<string, int> */
    public array $counters = [];

    public function increment(CounterKey $key): void
    {
        $id = implode('|', [$key->site, $key->day, $key->metric, $key->dimension, $key->value]);
        $this->counters[$id] = ($this->counters[$id] ?? 0) + 1;
    }

    public function counts(SiteId $site, string $metric, string $dimension, UtcDay $from, UtcDay $to): array
    {
        $out = [];
        foreach ($this->counters as $id => $n) {
            [$s, $d, $m, $dim, $value] = explode('|', $id, 5);
            if ($s === $site->value && $m === $metric && $dim === $dimension
                && !UtcDay::fromString($d)->isBefore($from) && !$to->isBefore(UtcDay::fromString($d))) {
                $out[$value] = ($out[$value] ?? 0) + $n;
            }
        }

        return $out;
    }

    public function deleteSite(SiteId $site): void
    {
        $this->counters = array_filter($this->counters, static fn (string $id): bool => !str_starts_with($id, $site->value.'|'), \ARRAY_FILTER_USE_KEY);
    }
}

final class FailingRateLimiter implements CollectionRateLimiter
{
    public function consume(SiteId $site, ClientDigest $digest): bool
    {
        return false; // what a fail-closed limiter returns when its backend is down
    }
}
```

Ce qu'il faut vérifier sur vos vrais adaptateurs, par port :

| Port | Vérifiez |
|---|---|
| `CounterStore` | Deux `increment()` simultanés de la même clé donnent 2 (contre la vraie base de données) ; `counts()` inclut les deux bouts de la plage et additionne les jours ; `deleteSite()` laisse les autres sites intacts ; rien d'autre que les parties de la clé n'est stocké |
| `CollectionProjection` | Une origine non déclarée donne `null` ; toutes les origines déclarées du site sont retournées ; un site suspendu est retourné avec son état (la collecte le refuse, pas la projection) |
| `CollectionRateLimiter` | Le budget est appliqué par site et par empreinte ; **une panne du stockage retourne `false` ou lance une exception** ; la clé stockée est `ClientDigest::toString()`, jamais une adresse ; les entrées expirent |
| `OutboundLinkDirectory` | Une référence inconnue donne `null` |
| `CounterRollup` | Deux passages donnent les totaux d'un seul ; un échec laisse jours et mois tels qu'ils étaient ; un terme rare disparaît |
| `SharedViews` | Aucune colonne ne contient le jeton ; une recherche par empreinte trouve une vue révoquée avec son `revokedAt` |
| `CountryResolver` | Une adresse qu'il ne sait pas situer, ou une adresse privée, donne `unknown()` ; une base plus vieille que la borne de fraîcheur donne `unknown()` ; l'adresse n'apparaît dans aucun journal |
| `CrawlerDetector` | L'agent utilisateur d'un robot d'indexation déclaré est détecté ; celui d'un navigateur ordinaire ne l'est pas |
| `RobotVerdictCounter` | Une instance d'adaptateur par site ; `increment()` ne change que le compte de ce site |

`CollectorTest` vérifie que le point de collecte du bundle n'écrit jamais pour un site suspendu et ne consomme jamais le
limiteur pour lui. Un point de collecte propre à l'application mérite le même test, avec un limiteur espion.

Installée seule, la suite fonctionnelle est sautée : avec `symfony/event-dispatcher` requis pour le développement
seulement, le conteneur de Symfony laisse de côté le répartiteur d'événements et aucun noyau ne démarre. Elle tourne
depuis une application qui requiert un noyau HTTP, comme la racine du monodépôt.

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. Depuis la racine du
monodépôt :

```sh
composer bench -- packages/analytics-bundle/tests/Benchmark
```
