<!-- phil-dev source-sha256=6da538d5de69146d30de76f2a11cb0cccc22b306480374fcf63331031591baad -->
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

## Exécuter le *worker*

Construire un long livre prend du temps. Donnez à `WriteEpub` un transport à part quand d'autres messages partagent le
*worker*, pour qu'un livre ne retarde jamais les messages qu'un utilisateur attend, comme un courriel :

```yaml
framework:
    messenger:
        transports:
            epub: '%env(EPUB_TRANSPORT_DSN)%'
        routing:
            Phil\EpubBundle\Application\Message\WriteEpub: epub
```

```sh
php bin/console messenger:consume epub --time-limit=3600
```

Un *worker* vérifie `--time-limit` entre les messages et n'arrête jamais un livre en cours : fixez-le plus long que le
livre le plus lent, pour qu'un *worker* ne redémarre pas après presque chaque message.

Pour écrire plus de livres à la fois, lancez plusieurs *workers* sur le transport plutôt que
`messenger:consume --concurrency`. Tant que [symfony/symfony#68459](https://github.com/symfony/symfony/pull/68459)
n'est pas fusionnée, un gestionnaire de plus de quinze secondes environ retient le processus parent de `--concurrency`
jusqu'à sa fin, et aucun autre message ne démarre entre-temps : gardez `--concurrency` pour les transports dont les
gestionnaires sont courts.
