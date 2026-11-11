<!-- phil-dev source-sha256=9cf911990b5494e4ccee0262a90d0254d1a0b0202ca65b68a6836943a0e9652b -->
# Points d'extension

Ce qu'une application implémente, étiquette, configure ou utilise.

## Ports

### `CacheTagResolver`

`Phil\HttpCacheTagsBundle\Application\CacheTagResolver` : les étiquettes des réponses qui affichent une entité. Un
service qui l'implémente reçoit l'étiquette de service `phil_http_cache_tags.resolver` par configuration automatique.
Voir [Purge](purging.md).

| Méthode | Contrat |
|---|---|
| `tagsFor(object $entity): iterable<string>` | Les étiquettes à purger quand l'entité change, aucune pour une entité qu'il ne connaît pas ; peu coûteux, puisque chaque entité qu'un flush modifie est passée à chaque résolveur |

### `CacheTagPurger`

`Phil\HttpCacheTagsBundle\Application\CacheTagPurger` : le cache placé devant. `VarnishPurger` ou `CloudflarePurger`,
selon `purger` ; un service propre à l'application, déclaré comme alias du port, remplace celui qui est configuré : un
purgeur Fastly, un CDN qui lui est propre.

| Méthode | Contrat |
|---|---|
| `purge(list<string> $tags): void` | Purge chaque réponse qui porte l'une des étiquettes ; essaie chaque cible même quand l'une échoue, puis lance une seule `PurgeFailed` qui nomme chaque échec |

## Étiquetage

| Depuis | Comment |
|---|---|
| Un contrôleur | `#[CacheTag('page:{id}', 'menu')]`, `{name}` étant un attribut de la requête |
| Un gabarit | `cache_tag()` |
| Le code | `ResponseTags::add()` |

Voir [Étiquetage](tagging.md).

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `header` | L'en-tête dans lequel les étiquettes sont écrites : `xkey`, `cache-tag` ou `surrogate-key` |
| `purger` | Le cache qui est purgé : aucun, `varnish` ou `cloudflare` |
| `varnish`, `cloudflare` | Où et comment |
| `invalidate_entities` | Si chaque entité qu'un flush de Doctrine modifie est passée aux résolveurs |

## Services

| Service | Comment une application l'utilise |
|---|---|
| `Invalidator` | `invalidate()` et `invalidateEntity()`, pour les étiquettes et les objets qu'aucun flush ne porte ; purgés une seule fois à la fin de la requête, de la commande ou du message |
| `ResponseTags` | Les étiquettes de la réponse courante ; public, remis à zéro entre les requêtes |

Les échecs de purge sont journalisés sur le canal `http_cache` : un gestionnaire Monolog de l'application sur ce canal
les envoie où elle veut.
