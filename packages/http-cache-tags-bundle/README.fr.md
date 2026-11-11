<!-- phil-dev source-sha256=afbf6f302c8455be7db44d542b97bc8449aef1fb7c8f07897edf9db327bd3308 -->
# phil/http-cache-tags-bundle

**Mettez des pages entières en cache dans Varnish ou un CDN, et purgez exactement celles qu'une modification change.**

phil/http-cache-tags-bundle étiquette les réponses d'une application Symfony pour le cache HTTP placé devant elle. Une
réponse recueille des étiquettes pendant son rendu : depuis le `#[CacheTag]` d'un contrôleur, le `cache_tag()` d'un
gabarit ou `ResponseTags` dans le code. Les étiquettes sont écrites dans l'en-tête que lit le cache : `xkey` pour
Varnish, `Cache-Tag` pour Cloudflare, `Surrogate-Key` pour Fastly. Quand une entité change lors d'un flush de Doctrine,
ou quand l'application le demande, ses étiquettes sont purgées à la fin de la requête ou de la commande. Le bundle purge
Varnish et Cloudflare ; pour Fastly, il écrit seulement l'en-tête. Il ne fixe pas la durée de mise en cache d'une
réponse : l'en-tête `Cache-Control` reste celui de l'application.

Il s'adresse à un site qui sert la plupart de ses pages depuis un cache et veut qu'une modification s'affiche tout de
suite, sans tout purger. Une page étiquetée `page:42` et `menu` quitte le cache quand cette page ou le menu change, et
seulement à ce moment. Les étiquettes d'un fragment atteignent la page dont il fait partie : un bloc intégré est donc
purgé avec chaque page qui l'affiche.

Le bundle se branche sur le noyau HTTP de Symfony pour écrire l'en-tête et pour purger après l'envoi de la réponse.
Doctrine ORM, Twig et le client HTTP de Symfony sont facultatifs, pour l'invalidation au flush, `cache_tag()` et les
purgeurs. Deux ports portent la conception : un `CacheTagResolver` nomme les étiquettes des réponses qui affichent une
entité, et un `CacheTagPurger` envoie la purge à un cache.

## État

| Partie | État |
|---|---|
| `CacheTag`, `ResponseTags`, l'en-tête sur la réponse principale | Implémenté et testé |
| `#[CacheTag]` avec des paramètres de route ; `cache_tag()` dans Twig | Implémenté et testé |
| `Invalidator`, `CacheTagResolver`, l'invalidation à chaque flush, la purge à la fin | Implémenté et testé |
| `VarnishPurger` (xkey), `CloudflarePurger` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement, la configuration. |
| [Étiquetage](doc/fr/tagging.md) | L'attribut, la fonction Twig, le service, l'en-tête. |
| [Purge](doc/fr/purging.md) | Les résolveurs, le flush, la fin d'une requête, Varnish et Cloudflare. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | La façon dont le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```php
#[CacheTag('page:{id}', 'menu')]
public function show(string $id): Response { /* … */ }
```

```twig
{{ cache_tag('product:' ~ product.id) }}
```

```php
$invalidator->invalidate('menu');   // purged when the request ends
```

## Prérequis

PHP 8.5 ou plus récent ; les composants DependencyInjection, Config et HttpKernel de Symfony 8.2. Doctrine ORM, le
client HTTP de Symfony et Twig sont facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
