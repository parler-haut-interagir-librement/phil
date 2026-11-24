<!-- phil-dev source-sha256=aca2bd28c19adba5416b6ca53687c09e7a569d0a87844ccac47d7ee2f84151d1 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Appliquer une décision de rebond à vos enregistrements | Écoutez `BounceDecided` ; dans un worker, gérez `BounceDecidedMessage` |
| Choisir la politique du site courant | Implémentez `CurrentSite` et nommez son service dans `current_site` |
| Interroger le DNS autrement | Implémentez `MailDomainResolver` de `phil/email-address` et nommez son service dans `dns.resolver` |
| Lire une autre boîte, comme IMAP, ou l'API d'un fournisseur | Implémentez `BounceMailbox` et nommez son service dans `bounces.mailbox` |
| Authentifier autrement les rapports d'abus | Implémentez `ReportAuthenticator` de `phil/bounce-parser` et nommez son service dans `bounces.authenticator` |
| Garder ailleurs l'historique des rebonds | Créez un alias de `BounceHistoryRepository` vers votre propre service |
| Ajouter des règles à l'analyseur de rebonds | Remplacez le service `Phil\BounceParser\BounceParser`, construit avec vos `Rules` |
| Réagir à ce que le navigateur a trouvé | Écoutez les événements `hint`, `applied` et `failed` du contrôleur |

Le bundle ne recueille aucun service étiqueté qui lui soit propre. `MailerEventConsumer` est étiqueté
`remote_event.consumer` une fois par nom de `bounces.remote_event_consumers` ; `ForwardBounceDecision` écoute
`BounceDecided` quand `bounces.message_bus` est défini.

Un `BounceHistoryRepository` doit revendiquer une clé une seule fois tant que sa revendication dure, compter les
rebonds temporaires d'un sujet dans la fenêtre qu'on lui donne, et les oublier sur demande ; les tests de
`CacheBounceHistoryRepository` dans le bundle montrent ce que répond chaque méthode.
