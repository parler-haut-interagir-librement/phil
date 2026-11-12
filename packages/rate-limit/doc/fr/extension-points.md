<!-- phil-dev source-sha256=e996411df95a09af3b84112a66835c3dfecbcba1c1c069f3668b72edd5f2d393 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faire |
|---|---|
| Garder les limites de débit d'un bundle | Ajouter `new RateLimitGuard()` dans `build()`, appeler `requireComponent()` et `watch()` dans `loadExtension()` |
| Accepter un magasin local | Passer `singleServer: true` à `watch()`, en général depuis une option `single_server` du bundle |
| Proposer l'option qui coupe une limite | Passer son nom comme `switch` à `watch()` |
| Demander si un service est local | Appeler `new StoreLocality()->isLocal($container, $id)` depuis une passe de compilation |

## Une localité à soi

`StoreLocality` est finale, et la passe la prend comme seul argument. Un bundle qui connaît un autre magasin local (un
pool à lui en mémoire partagée) le vérifie avant de le noter, au lieu de remplacer la classe :

```php
if (!$this->isSharedMemory($pool)) {
    RateLimitGuard::watch($builder, $pool, 'acme.rate_limit.cache_pool', $singleServer);
}
```

## Noms stables

`RateLimitGuard::STORES_PARAMETER` est le paramètre caché où chaque bundle écrit ; un bundle ne l'écrit jamais à la
main, `watch()` le fait. `StoreLocality::LOCAL_ADAPTERS` et `StoreLocality::LOCAL_CLASSES` listent ce qui est local.
