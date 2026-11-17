<!-- phil-dev source-sha256=d01c7c4b951d9b3cd8480be8e55e30c2477fd7a1cfa6eda217e88468374fdcc3 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG dit comment.

## Ce que l'application implémente

| Port | Quand | Ce qu'il fait |
|---|---|---|
| `Phil\LtiBundle\Application\Contract\LaunchMessages` | Le côté plateforme est activé | L'indice de connexion d'un membre, et le message d'un lancement pour le membre connecté. |
| `Phil\Lti\Contract\Roster` | La plateforme répond à ses services | Les contextes, les outils placés dans chacun, et leurs membres ; une liste vide d'ici là. |
| `Phil\LtiBundle\Application\Contract\LaunchUsers` | Facultatif, côté outil | Le compte de l'application pour un lancement ; un `LtiUser` sans lui. |
| `Phil\LtiBundle\Application\Contract\KeySealer` | Facultatif | Scelle les clés privées ; `KeyManagementSealer` par défaut. |

## Les magasins

Chaque port de `phil/lti` que le bundle emploie est lié par alias à son adaptateur ; une application lie le port à son
propre service pour garder une partie de l'état ailleurs :

| Port | `dbal` | `memory` |
|---|---|---|
| `Phil\Lti\Contract\Nonces` | `DbalNonces` | `InMemoryNonces` |
| `Phil\Lti\Contract\LoginStates` | `DbalLoginStates` | `InMemoryLoginStates` |
| `Phil\Lti\Contract\AccessTokens` | `DbalAccessTokens` | `InMemoryAccessTokens` |
| `Phil\Lti\Contract\LineItemStore` | `DbalLineItemStore` | `InMemoryLineItemStore` |
| `Phil\Lti\Contract\ScoreStore` | `DbalScoreStore` | `InMemoryScoreStore` |
| `Phil\Lti\Contract\KeyStore` | `DbalKeyStore` (clés `dbal`) | `InMemoryKeyStore` |
| `Phil\LtiBundle\Application\Contract\PendingScores` | `DbalPendingScores` | `InMemoryPendingScores` |
| `Phil\LtiBundle\Application\Contract\DeepLinkingReturns` | `DbalDeepLinkingReturns` | `InMemoryDeepLinkingReturns` |
| `Phil\Lti\Contract\PlatformRegistrations` | depuis `tool.platforms` | le même |
| `Phil\Lti\Contract\ToolRegistrations` | depuis `platform.tools` | le même |

Un adaptateur respecte le contrat de son port :

- `ScoreStore::record()` compare et écrit en une seule étape atomique, et répond s'il a gardé la note (voir les points
  d'extension de `phil/lti`) ;
- `PendingScores::claim()` prend chaque note en une seule étape atomique — une mise à jour gardée, ou un verrou — et la
  marque réclamée au moment donné, pour que de deux reprises simultanées chaque note aille à une seule ;
  `markDispatched()` et `markRefused()` ne font rien pour une note qui n'existe plus ; une note refusée ou envoyée par
  le bus n'est jamais réclamée ;
- `DeepLinkingReturns::save()` garde la première réponse enregistrée sous une clé ; `find()` et `unhandled()` ne
  répondent jamais une réponse expirée.

`PendingScores` a la forme d'une boîte d'envoi (*outbox*) — `save()` écrit l'entrée, `markDispatched()` l'acquitte,
`claim()` l'interroge, `refused()` et `delete()` gardent et purgent les lettres mortes — : une application qui a sa
propre boîte d'envoi implémente le port par-dessus.

## Les événements

| Événement | Côté | Ce que fait un écouteur |
|---|---|---|
| `LaunchReceived` | Outil | Répond à un lancement vérifié avec `setResponse()`. |
| `ScorePublished` | Outil | Apprend qu'une note a été prise par la plateforme. |
| `ScoreRefused` | Outil | Garde sa propre trace d'une note que la plateforme a refusée pour de bon. |
| `DeepLinkingReturned` | Plateforme | Place les éléments choisis, une fois par `key`, et répond avec `setResponse()` ; idempotent, car une réponse dont le traitement a échoué est annoncée de nouveau avec `resumed` à true. |
| `LineItemSaved`, `LineItemDeleted` | Plateforme | Tient les colonnes du carnet de notes. |
| `ScoreReceived` | Plateforme | Tient les notes du carnet de notes. |

## Les services à employer

`ScorePublisher`, `DeepLinkingResponder`, `DeepLinkingHandler`, `FormPostResponder`, `AgsClient`, `NrpsClient`,
`LaunchStarter`, `PlatformUrls` et `KeyStore` sont des services publics.

## Ce qui reste fermé

Les contrôleurs, l'authentificateur et les vérifications de `phil/lti` sont finaux. Les gabarits peuvent être
remplacés comme ceux de n'importe quel bundle, dans `templates/bundles/PhilLtiBundle/`.
