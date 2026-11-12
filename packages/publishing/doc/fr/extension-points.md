<!-- phil-dev source-sha256=512d2004f35e61abda8143b96090c882c4a4690756241c15bd916172f01085e2 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

La bibliothèque ne déclare aucun service et ne prend aucun port. Ce sur quoi une application s'appuie :

| Pour | Appuyez-vous sur |
|---|---|
| Annoncer qu'une page passe en ligne ou hors ligne | `new PagePublished(...)` et `new PageRetired(...)`, répartis sur le répartiteur de l'application par le paquet propriétaire de la page |
| Suivre les deux de la même façon | `Contract\PageEvent` : `canonicalUrl`, `locale`, `indexable` et `ownerId`, en lecture seule |
| En suivre un seul | Un écouteur de `PagePublished` ou de `PageRetired`, par classe |
| Refuser un événement mal formé | `Exception\InvalidPageEvent`, levée par les deux constructeurs |

`PageEventFields` contient les contrôles que partagent les deux événements ; il est interne et peut changer dans
n'importe quelle version.
