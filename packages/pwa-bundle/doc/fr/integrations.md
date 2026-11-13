<!-- phil-dev source-sha256=bc6eb83d0af9e0b7e1c6b356dde860dd7903a2fec7397dd1bd77b9bfe6ce188f -->
# Intégrations

Ce sur quoi s'appuie un bundle construit sur celui-ci : `phil/digital-signage`, dont les écrans montrent un horaire et
des annonces, `phil/schedule-app`, le programme d'un événement installé avec son horaire personnel, ou une borne propre
à l'application.

## Chemins préchargés

Un service qui implémente `Phil\PwaBundle\Application\Contract\PrecacheProvider` est étiqueté
`phil_pwa.precache_provider` par autoconfiguration, et ses chemins se joignent à la liste de préchargement :

```php
final readonly class ScreenPages implements PrecacheProvider
{
    public function urls(): iterable
    {
        yield '/screens/hall';
        yield '/screens/hall/data.json';
    }
}
```

Les chemins sont vérifiés comme ceux de la configuration. Ils font partie de la version : un fournisseur répond les
mêmes chemins à chaque appel tant que le déploiement ne change pas, sans quoi le worker se réinstalle à chaque visite.
Une liste de plus de 200 chemins est refusée.

## Des données qui changent

Une règle de cache décide de la façon dont un chemin est servi. Les données qu'un écran interroge — un horaire, des
annonces — sont en `network-first`, de sorte que l'écran montre la dernière réponse reçue pendant que le réseau est
coupé :

```yaml
phil_pwa:
    service_worker:
        cache_rules:
            - { prefix: /assets/, strategy: cache-first }
            - { prefix: /screens/, strategy: network-first }
            - { prefix: /schedule, strategy: network-first }
```

## Un écran, pour phil/digital-signage

`display: fullscreen` et une `orientation` font du site installé un écran sans l'interface du navigateur ; l'action
`fullscreen` en fait autant pour une page dans un navigateur. Une page montrée sur un second écran avec le contrôleur de
présentation reçoit ses messages sous forme d'événements, de sorte que l'écran change sans aller-retour au serveur
([JavaScript](javascript.md)).

## Un programme dans la poche, pour phil/schedule-app

`start_url` et `shortcuts` ouvrent le programme et ses parties ; les pages et les données du programme, dans la liste
de préchargement ou sous une règle `network-first`, se lisent hors ligne ; l'horaire personnel d'un visiteur reste dans
le navigateur, jamais dans les caches du worker, qui ne contiennent aucune page marquée `private`.
