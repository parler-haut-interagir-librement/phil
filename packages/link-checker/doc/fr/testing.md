<!-- phil-dev source-sha256=dd60076fa382e86c73e9568d7a9d9c5e90def4e725f123e12274f1a0e2f75654 -->
<!-- phil-dev source-sha256=x -->
# Tests

## Les tests du paquet

Les tests sont des tests PHPUnit dans `tests/Unit`, lancés depuis la racine du monodépôt avec `composer test`, ou
seuls :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Ils s'exécutent sans noyau, sans base de données et sans réseau. Les requêtes passent par phil/safe-http vers le
`MockHttpClient` de Symfony, avec les noms d'hôte fixés à des adresses publiques des plages de documentation, ou à des
adresses privées pour tester les refus, de sorte qu'aucun test ne résout un nom ; le limiteur de débit dort sur le
`MockClock` de Symfony, et le cache est un `ArrayAdapter` sur le même genre d'horloge, de sorte que les durées de vie
se testent sans attendre.

- `tests/Support/ScriptedProbe` répond à partir d'un scénario, par adresse, et retient ce qu'on lui a demandé ;
- `tests/Support/MemoryInternalPages` garde en mémoire les pages d'un site ;
- `tests/Support/RecordingThrottle` n'attend jamais, et retient les hôtes et les réinitialisations.

`tests/Unit/PipelineTest` exécute toute la chaîne : deux contenus, leurs liens extraits, dédupliqués, espacés, mis en
cache, redirigés, refusés, et les ancres d'une page interne lues par HTTP. Dans le monodépôt, Infection tient le paquet
à 100 % des mutants couverts.

Aucun test d'intégration n'atteint un serveur réel : phil/safe-http refuse le bouclage et les réseaux privés où écoute
un serveur de test local, et un serveur public ferait dépendre la suite du réseau.

## Tester les vérifications d'une application

Une application teste ses propres `InternalPages`, ou ce qu'elle fait des rapports, avec une sonde qui répond à partir
d'un scénario :

```php
use Phil\LinkChecker\ContentLinks;
use Phil\LinkChecker\Contract\LinkProbe;
use Phil\LinkChecker\LinkChecker;
use Phil\LinkChecker\LinkResult;
use Phil\LinkChecker\LinkStatus;
use Phil\LinkChecker\Probe\ProbeResult;
use Phil\Url\Url;

$probe = new class implements LinkProbe {
    public function probe(Url $url, bool $withBody = false): ProbeResult
    {
        return new ProbeResult(new LinkResult(str_ends_with($url->path, '/gone') ? LinkStatus::Dead : LinkStatus::Alive, 200));
    }
};

$report = new LinkChecker($probe, internalPages: $pages, internalHosts: ['www.example.com'])
    ->checkContent(new ContentLinks('1', Url::fromString('https://www.example.com/a'), '<a href="/gone">x</a>'));
```

Ou avec la vraie sonde sur un `MockHttpClient`, comme le font les tests du paquet :

```php
use Phil\LinkChecker\Probe\HttpLinkProbe;
use Phil\LinkChecker\Throttle\IntervalThrottle;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

$probe = new HttpLinkProbe(
    new MockHttpClient([new MockResponse('', ['http_code' => 404])]),
    resolve: ['example.org' => '93.184.215.14'],
    throttle: new IntervalThrottle(1.0, new MockClock()),
);
```

## La cible de fuzzing

`tests/Fuzz/extract.php` extrait les liens d'un HTML que personne n'a écrit, avec de petites limites, puis indexe ses
ancres : rien ne lève d'exception, pas plus de liens que la limite n'en sort, chaque lien a soit une adresse du Web,
soit un état, jamais les deux, et chaque adresse telle qu'écrite et chaque texte restent sur une ligne, dans leurs 200
caractères. Ses germes sont dans `tests/Fuzz/corpus/extract`.

```sh
composer fuzz -- extract
```

## Le banc d'essai

`tests/Benchmark/ExtractBench.php` mesure le travail fait avant toute requête sur un grand article — 2 000 liens, des
images avec `srcset`, 500 sections — : les liens extraits et résolus, et les ancres de la page indexées.

```sh
composer bench
```
