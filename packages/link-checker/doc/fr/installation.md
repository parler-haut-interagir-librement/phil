<!-- phil-dev source-sha256=4976493b855891575647c2ec3224aeb45b304004eb639fb9e371713b27673cc4 -->
<!-- phil-dev source-sha256=x -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `ext-dom` (l'analyseur HTML5, `Dom\HTMLDocument`), `ext-mbstring` et
  `ext-uri` (l'analyseur d'URL du WHATWG).
- phil/safe-http, qui fait les requêtes, et phil/url, qui lit les adresses.
- HttpClient, Clock et les contrats de cache et de service de Symfony. Un cache est facultatif : tout pool de
  `symfony/cache` implémente le contrat que prend le vérificateur.

## Installer

```sh
composer require phil/link-checker
```

La bibliothèque n'a aucun fichier de configuration. `LinkChecker`, `LinkExtractor` et `UrlNormalizer` ne gardent rien
entre deux appels ; `HttpLinkProbe` et `IntervalThrottle` implémentent le `ResetInterface` de Symfony, qu'un *worker* de
longue durée appelle entre deux messages. Un framework les enregistre comme services, ou le code les construit là où il
en a besoin.

## Une première vérification

```php
use Phil\LinkChecker\ContentLinks;
use Phil\LinkChecker\LinkChecker;
use Phil\LinkChecker\Probe\HttpLinkProbe;
use Phil\LinkChecker\Throttle\IntervalThrottle;
use Phil\SafeHttp\Limits;
use Phil\Url\Url;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$probe = new HttpLinkProbe(
    limits: new Limits(timeout: 5.0, userAgent: 'ExampleLinks/1.0 (+https://www.example.com/contact)'),
    throttle: new IntervalThrottle(interval: 2.0),
);

$checker = new LinkChecker(
    $probe,
    cache: new FilesystemAdapter('links'),
    internalHosts: ['www.example.com', 'example.com'],
);

foreach ($checker->check($contents) as $report) {
    // $contents is an iterable of ContentLinks: the id of a content, the address of its page, its HTML.
    foreach ($report->broken() as $link) {
        // $link->link->url, $link->link->element, $link->link->attribute, $link->link->text,
        // $link->result->status, $link->result->code, $link->result->target, $link->result->reason
    }
}
```

Un contenu se décrit par un `ContentLinks` :

```php
new ContentLinks('article-42', Url::fromString('https://www.example.com/news/42'), $article->html());
```

Ses liens relatifs se résolvent par rapport à l'adresse de la page, ou au premier `<base href>` du HTML.
`checkContent()` vérifie un contenu ; `check()` prend n'importe quel itérable et produit un rapport par contenu, dans
l'ordre, dès qu'il est prêt.

[Vérifier les liens](checking.md) décrit chaque étape, et les [points d'extension](extension-points.md) montrent comment
un bundle ou une application répartit le travail sur une file d'attente.
