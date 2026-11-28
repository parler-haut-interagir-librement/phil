<!-- phil-dev source-sha256=0b4738815559c6179c8e4a10b1a66dcee7a429ed2339fa178e5cd7cf6ac425a6 -->
# Tests

## Les tests du paquet

Les tests sont des tests PHPUnit sous `tests/Unit`, lancés depuis la racine du monodépôt avec `composer test`, ou seuls :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Ils s'exécutent sans noyau, sans base de données et sans réseau. Les ports sont implémentés en mémoire sous
`tests/Support` :

- `MemoryAudience` et `MemoryExclusions` répondent à partir des membres qu'on leur donne, triés comme le port l'exige ;
- `MemoryFacts` implémente chaque port de faits et se souvient des questions posées ;
- `MemoryMembershipStore` conserve les membres de chaque segment et les écritures faites ;
- `RecordingDispatcher` et `FixedClock` tiennent lieu du répartiteur et de l'horloge PSR.

`RecalculationTest` exécute un recalcul où des membres restent, entrent, perdent le palier, se désabonnent et quittent
l'audience. Les implémentations de ports qui rompent leur contrat sont testées aussi. Dans le monodépôt, Infection
maintient le paquet à 100 % des mutants couverts.

## Le banc d'essai

`tests/Benchmark/SegmentsBench` fige un segment de deux conditions sur une audience de 5 000 membres, lue par pages de
500, avec des exclusions :

```sh
composer bench -- packages/audience-segments/tests/Benchmark
```

## Tester un port d'une application

Un port se teste au travers d'un segment, de sorte que la bibliothèque vérifie ce qu'il renvoie :

```php
use Phil\AudienceSegments\AudienceConditions;
use Phil\AudienceSegments\SegmentJson;
use Phil\AudienceSegments\Segments;
use Symfony\Component\Clock\MockClock;

$json = new SegmentJson(AudienceConditions::types(tags: new DbalPeopleTags($connection)));
$segment = $json->read('press', '{"version":1,"name":"Press","condition":{"type":"person.tagged","operands":{"tags":["press"]}}}');
$segments = new Segments(new NewsletterSubscribers($connection), $exclusions, new MockClock());

self::assertSame(['person:7', 'person:9'], $segments->freeze($segment)->members);
```
