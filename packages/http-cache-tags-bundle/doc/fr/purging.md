<!-- phil-dev source-sha256=f25f81a7034fe7b8d3854a860baed69d7a2ae220a13aaed08a92ebcf8a55a819 -->
# Purge

`Invalidator::invalidate('menu')` recueille des étiquettes à purger ; elles sont purgées une seule fois, chaque
étiquette une seule fois, à la fin :

| Où | Quand |
|---|---|
| D'une requête | `kernel.terminate`, une fois la réponse envoyée |
| D'une commande | `console.terminate` |
| Du message d'un worker de Messenger | `WorkerMessageHandledEvent` ou `WorkerMessageFailedEvent` : une page qu'une tâche asynchrone modifie, une publication que `phil/scheduled-transitions-bundle` exécute depuis Symfony Scheduler, est purgée dès que le message est traité, pas quand le worker s'arrête |

## Quand une entité change

Un `CacheTagResolver` nomme les étiquettes des réponses qui affichent une entité ; avec la configuration automatique, le
bundle le trouve. Avec `invalidate_entities`, chaque entité qu'un flush de Doctrine insère, met à jour ou supprime est
passée aux résolveurs.

```php
final class PageTags implements CacheTagResolver
{
    public function tagsFor(object $entity): iterable
    {
        return $entity instanceof Page ? ['page:'.$entity->id, 'page-list'] : [];
    }
}
```

`Invalidator::invalidateEntity($entity)` fait la même chose pour un objet qui ne passe pas par un flush.

## Les caches

| Purgeur | Ce qu'il envoie |
|---|---|
| `VarnishPurger` | Une requête `PURGE` à chaque Varnish, avec les étiquettes dans `xkey-purge`, séparées par des espaces, pour le module xkey |
| `CloudflarePurger` | `POST /zones/{zone}/purge_cache` avec `{"tags": […]}`, cent étiquettes par appel, avec le jeton d'API |

Chaque Varnish est purgé, et chaque appel à Cloudflare est fait, même quand l'un échoue. Une réponse de 400 ou plus, ou
un cache injoignable, donne alors une seule `PurgeFailed` qui nomme chaque échec, avec l'erreur de transport comme
exception précédente : ces réponses restent périmées jusqu'à leur expiration. `InvalidationFlushListener` la journalise
comme erreur, sur le canal `http_cache`, avec le `logger` du conteneur, pour qu'une réponse déjà envoyée ou un worker
poursuive son travail ; sans journaliseur, elle est lancée.
