<!-- phil-dev source-sha256=50fd66092c84339204d02e86530b09beaef906520858ebae82a6b7c4cde06acb -->
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
| `Phil\Lti\Contract\PlatformRegistrations` | depuis `tool.platforms` | le même |
| `Phil\Lti\Contract\ToolRegistrations` | depuis `platform.tools` | le même |

## Les événements

| Événement | Côté | Ce que fait un écouteur |
|---|---|---|
| `LaunchReceived` | Outil | Répond à un lancement vérifié avec `setResponse()`. |
| `ScorePublished` | Outil | Apprend qu'une note a été prise par la plateforme. |
| `DeepLinkingReturned` | Plateforme | Place les éléments choisis et répond avec `setResponse()`. |
| `LineItemSaved`, `LineItemDeleted` | Plateforme | Tient les colonnes du carnet de notes. |
| `ScoreReceived` | Plateforme | Tient les notes du carnet de notes. |

## Les services à employer

`ScorePublisher`, `DeepLinkingResponder`, `FormPostResponder`, `AgsClient`, `NrpsClient`, `LaunchStarter`,
`PlatformUrls` et `KeyStore` sont des services publics.

## Ce qui reste fermé

Les contrôleurs, l'authentificateur et les vérifications de `phil/lti` sont finaux. Les gabarits peuvent être
remplacés comme ceux de n'importe quel bundle, dans `templates/bundles/PhilLtiBundle/`.
