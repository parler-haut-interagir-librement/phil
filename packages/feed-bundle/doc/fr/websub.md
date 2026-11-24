<!-- phil-dev source-sha256=0348702eac13d1a5cd373f48f7fb735facd1eebaea1779230bcb87b37f30d621 -->
# WebSub

Un concentrateur WebSub pousse un flux à ses abonnés dès qu'il change, au lieu qu'ils le redemandent sans cesse. Le
bundle est l'éditeur : chaque flux nomme ses concentrateurs, et ceux-ci sont avertis quand un flux change. Il n'héberge
aucun concentrateur ; l'application en nomme un public, ou un qu'elle exploite.

## L'activer

```yaml
phil_feed:
    websub:
        hubs: ['https://hub.example.net/']

framework:
    messenger:
        routing:
            Phil\FeedBundle\Infrastructure\WebSub\AnnounceFeedUpdate: async
```

Sans concentrateur, rien n'est enregistré : ni écouteur, ni handler, ni client HTTP.

## Ce qu'un flux nomme

| Format | Sa propre adresse | Ses concentrateurs |
|---|---|---|
| Atom | `<link rel="self" href="…"/>` | `<link rel="hub" href="…"/>` |
| RSS | `<atom:link rel="self" href="…"/>` | `<atom:link rel="hub" href="…"/>` |
| JSON Feed | `feed_url` | `hubs: [{"type": "WebSub", "url": "…"}]` |

L'adresse self est la route du flux, page 1, absolue : le sujet auquel un abonné s'abonne.

## L'annonce

1. Un paquet envoie `PagePublished` ou `PageRetired` de `phil/publishing` une fois le changement enregistré.
2. `AnnounceChangedFeeds` demande à chaque source si elle `includes()` la page, et envoie un `AnnounceFeedUpdate` par
   flux qui l'inclut. Le message contient le nom du flux et rien d'autre : ni adresse, ni titre, ni contenu.
3. `AnnounceFeedUpdateHandler`, dans le worker, écrit l'adresse du flux dans chaque format servi et avertit chaque
   concentrateur : un `POST` de `hub.mode=publish&hub.url={topic}`, `application/x-www-form-urlencoded`, par
   `phil/safe-http`. Le concentrateur lit alors le flux et le pousse à ses abonnés.

Dans un worker, le routeur écrit le sujet à partir de son contexte de requête : `framework.router.default_uri` lui
donne l'adresse du site.

## Ce qui est refusé, et ce qui est réessayé

| Cas | Ce qui arrive |
|---|---|
| Un concentrateur qui est, ou se résout en, une adresse de bouclage, privée, de lien local ou réservée | Refusé par `phil/safe-http` avant toute connexion, journalisé comme avertissement, non réessayé |
| Un concentrateur qui ne répond pas dans `timeout` ou `max_duration`, ou qui répond autre chose que `2xx` | Journalisé comme avertissement ; une fois tous les concentrateurs essayés, `HubNotificationFailed` remet le message à Messenger, qui le réessaie selon sa stratégie de reprise |
| Une redirection d'un concentrateur | Non suivie : un `POST` ne suit aucune redirection, et un `3xx` n'est pas un `2xx` |
| Un flux qui n'existe plus | Journalisé comme information, ignoré |

Une reprise avertit de nouveau les concentrateurs qui ont pris l'annonce : WebSub le permet, et un concentrateur relit
le flux. La réponse d'un concentrateur est lue jusqu'à 1 Kio ; son statut est ce qui compte. Les messages du journal
sont des constantes dont le contexte contient le concentrateur, le sujet et la raison.

Une rafale de publications envoie un message par page et par flux. Une application qui publie beaucoup de pages d'un
coup peut ajouter `DeduplicateStamp` de Messenger dans un middleware, ou retarder les messages, pour qu'un
concentrateur ne soit averti qu'une fois.
