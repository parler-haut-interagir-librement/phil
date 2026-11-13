<!-- phil-dev source-sha256=fbf8b58344d011703252856ca5eb2ff52a64a789715101f4a8b4604dafd7afbe -->
# Messenger

```php
$bus->dispatch(new WriteEpub('story', '42', 'stories/42.epub'));
```

`WriteEpub` transporte le nom d'une source de livres, un identifiant et le chemin sous lequel conserver le livre : aucun
texte, aucun renseignement personnel, rien de lourd. Le worker reconstruit le livre à partir des enregistrements de
l'application avec la source, l'écrit et le conserve. Il répartit ensuite `EpubStored` (la source, l'identifiant, le
chemin et l'endroit où le stockage le conserve) lorsqu'un répartiteur d'événements se trouve dans le conteneur.

| Échec | Ce que fait Messenger |
|---|---|
| `StorageFailed`, le `WriteFailed` de la bibliothèque | Fait une nouvelle tentative : un disque plein ou un stockage inaccessible peut être passager (`RecoverableMessageHandlingException`) |
| Toute autre exception de `phil/epub` ou du bundle : une source inconnue, un livre que la bibliothèque refuse, une police qui ne peut pas être incorporée | Ne fait pas de nouvelle tentative (`UnrecoverableMessageHandlingException`) |

Acheminez le message vers un transport asynchrone comme n'importe quel autre :

```yaml
framework:
    messenger:
        routing:
            Phil\EpubBundle\Application\Message\WriteEpub: async
```
