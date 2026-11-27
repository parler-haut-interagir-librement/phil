<!-- phil-dev source-sha256=818e32b3292c0e070ed2366ad3857ce61a6ee368f9d09305f5fa19cb0c3bbcb7 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/http-cache-tags-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-25

### Ajouté

- `http.client`, `http.timeout` et `http.max_duration` : le client HTTP des purgeurs, `http_client` par défaut, et la
  durée que peut prendre un appel de purge, 5 et 10 secondes par défaut ; `TIMEOUT` et `MAX_DURATION` de `VarnishPurger`
  et de `CloudflarePurger`, et leurs deux derniers arguments, facultatifs.

### Modifié

- Les prérequis de développement demandent la dernière mineure de chaque dépendance (`doctrine/orm` 3.8, `doctrine/dbal`
  4.6, `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31, …).
- Les prérequis de développement prennent `twig/twig` 3.31.1 ou plus récent, qui corrige les avis sur son bac à sable
  publiés le 2026-11-24.

### Sécurité

- `VarnishPurger` et `CloudflarePurger` appelés sans délai d'attente : un cache lent retenait un worker jusqu'à
  `default_socket_timeout`, 60 secondes. Chaque appel attend un octet 5 secondes au plus et dure 10 secondes au plus par
  défaut.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `CacheTag` (valeur), avec `tryOf()`, et `InvalidCacheTag`.
- `ResponseTags`, `Invalidator`, `CacheTagPurger` et `CacheTagResolver`.
- `VarnishPurger` et `CloudflarePurger`, qui purgent chaque cache et chaque morceau avant de lancer une seule
  `PurgeFailed` qui nomme chaque échec, erreurs de transport comprises.
- `PhilHttpCacheTagsBundle`, alias d'extension `phil_http_cache_tags`, clés `header`, `purger`, `varnish`, `cloudflare`
  et `invalidate_entities`.
- L'attribut `#[CacheTag]` ; `ResponseTagsListener`, qui laisse de côté une étiquette que la valeur de sa route empêche
  d'être une étiquette.
- `InvalidateOnFlushListener` ; `InvalidationFlushListener`, qui purge à la fin d'une requête, d'une commande ou du
  message d'un *worker* de Messenger, et journalise une purge refusée avec le `logger` du conteneur.
- `CacheTagExtension`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
