<!-- phil-dev source-sha256=75553b1bc42127e05f56642c9bdaeee825f04bf6a5f52e684b3edea906615363 -->
<!-- phil-dev source-sha256=x -->
# phil/link-checker

**Trouve les liens morts du contenu d'un site avant ses lecteurs : chaque adresse est vérifiée une seule fois, à un
rythme réglé par hôte, les ancres sont contrôlées et les réseaux privés sont refusés.**

phil/link-checker est une bibliothèque PHP simple. Elle vérifie les liens des contenus que le site connaît déjà — ses
articles, ses pages et ses descriptions — au lieu de parcourir le site : une application lui remet le HTML d'un contenu
et l'adresse de la page qui l'affiche, et reçoit en retour un **`LinkReport`** : chaque lien, son état et ce que sa
vérification a trouvé.

**`LinkExtractor`** lit les liens du HTML avec l'analyseur HTML5 de PHP, comme un navigateur le lit, bien formé ou non
— `a[href]`, `img[src]` et `srcset`, `link[href]`, `iframe[src]`, `source` — et résout chacun par rapport à la page
selon le standard URL du WHATWG. **`UrlNormalizer`** donne la clé sous laquelle deux liens vers la même adresse ne sont
vérifiés qu'une fois ; l'ordre de la requête ne compte que si l'appelant le demande. **`HttpLinkProbe`** interroge
chaque adresse par phil/safe-http, qui refuse les réseaux privés et borne le temps, les octets et les redirections :
`HEAD` d'abord, puis un `GET` sans corps quand le serveur refuse `HEAD`, chacun espacé par hôte par un
**`HostThrottle`**, avec un `User-Agent` neutre que l'application peut changer.

**`LinkChecker`** classe chaque lien : vivant ; redirigé, de façon permanente ou temporaire, avec sa cible ; mort (404,
410, un nom d'hôte qui n'existe pas) ; à examiner (429, 403, un délai dépassé, une erreur TLS), jamais compté comme
mort ; refusé, pour une adresse qui n'est pas publique. Un lien vers le site lui-même doit trouver son `#fragment`
dans la page cible, lue sans requête quand l'application la fournit par **`InternalPages`**. Les résultats sont gardés
dans un cache de Symfony entre les lots, et chaque adresse n'est interrogée qu'une fois par lot. Seule l'adresse que
nomme un lien est jamais demandée : le vérificateur ne suit jamais un lien plus loin dans un autre site.

## État

| Partie | État |
|---|---|
| `LinkExtractor`, `ExtractedLinks`, `Link` : les liens du HTML lus par l'analyseur HTML5, résolus, bornés | Implémenté et testé |
| `UrlNormalizer` : la clé de déduplication, la requête triée seulement sur demande | Implémenté et testé |
| `HttpLinkProbe` : `HEAD` puis `GET` borné sur phil/safe-http, les redirections et leur permanence | Implémenté et testé |
| `IntervalThrottle` et le port `HostThrottle` : un rythme par hôte, sur une horloge | Implémenté et testé |
| `LinkChecker` : la classification, les ancres des pages internes, `InternalPages`, le cache, les lots | Implémenté et testé |
| `LinkReport`, `CheckedLink` : le rapport en texte et en JSON | Implémenté et testé |
| Le port `DeadLinkFixer` | Déclaré ; implémenté par un bundle ou par l'application |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et une première vérification de bout en bout. |
| [Vérifier les liens](doc/fr/checking.md) | Les liens lus, la clé, les requêtes, chaque état, les ancres, le rapport. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les lots et les files d'attente, le cache, le limiteur de débit, les pages internes. |
| [Intégration continue](doc/fr/ci.md) | Vérifier les liens des fichiers d'un dépôt avec lychee, à côté de cette bibliothèque. |
| [Tests](doc/fr/testing.md) | Les tests, la cible de fuzzing et le banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/link-checker
```

```php
use Phil\LinkChecker\ContentLinks;
use Phil\LinkChecker\LinkChecker;
use Phil\LinkChecker\Probe\HttpLinkProbe;
use Phil\Url\Url;

$checker = new LinkChecker(new HttpLinkProbe(), cache: $cache, internalHosts: ['www.example.com']);
$report = $checker->checkContent(new ContentLinks('article-42', Url::fromString('https://www.example.com/news/42'), $html));

echo $report->toText();      // a summary, then one line per link that is not alive
$json = $report->toJson();   // every link, for a screen or an API
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-dom`, `ext-mbstring` et `ext-uri` ; phil/safe-http et phil/url ;
HttpClient, Clock et les contrats de cache et de service de Symfony.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
