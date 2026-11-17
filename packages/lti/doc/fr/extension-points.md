<!-- phil-dev source-sha256=5d6dd41f8e06e77252b9c1d6919744542ee026f1bb28397f3135c7344dcd5481 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG dit comment.

## Les ports

| Port | Ce qu'il garde | Adaptateur en mémoire |
|---|---|---|
| `Phil\Lti\Contract\PlatformRegistrations` | Les plateformes auprès desquelles l'outil est enregistré, et leurs déploiements. | `InMemoryPlatformRegistrations` |
| `Phil\Lti\Contract\ToolRegistrations` | Les outils que la plateforme a enregistrés, et leurs déploiements. | `InMemoryToolRegistrations` |
| `Phil\Lti\Contract\LoginStates` | Les états de connexion, chacun pris une seule fois. | `InMemoryLoginStates` |
| `Phil\Lti\Contract\Nonces` | Les valeurs employées une seule fois, jusqu'à leur expiration. | `InMemoryNonces` |
| `Phil\Lti\Contract\KeyStore` | La clé de signature et le jeu de clés de ce côté. | `InMemoryKeyStore` |
| `Phil\Lti\Contract\KeySetSource` | Les jeux de clés de l'autre côté, par adresse. | `SafeHttpKeySetSource`, `CachedKeySetSource` |
| `Phil\Lti\Contract\ServiceClient` | Les requêtes aux services de l'autre côté. | `SafeHttpServiceClient`, au-dessus de `phil/safe-http` |
| `Phil\Lti\Contract\AccessTokens` | Les jetons d'accès que la plateforme a accordés, par empreinte. | `InMemoryAccessTokens` |
| `Phil\Lti\Contract\LineItemStore` | Les line items de la plateforme. | `InMemoryLineItemStore` |
| `Phil\Lti\Contract\ScoreStore` | La dernière note de chaque apprenant sur chaque line item. | `InMemoryScoreStore` |
| `Phil\Lti\Contract\Roster` | Les contextes de la plateforme, les outils placés dans chacun, et leurs membres. | `InMemoryRoster` |
| `Phil\Lti\Contract\ContentItem` | Un élément de contenu de Deep Linking. | les cinq classes d'éléments |

Un adaptateur respecte le contrat de son port :

- `LoginStates::take()` retire l'état dans l'étape qui le lit : de deux lancements avec un même état, un seul le
  trouve ;
- `Nonces::use()` vérifie et écrit en une seule étape : de deux requêtes avec une même valeur, une seule réussit ;
- `AccessTokens::find()` répond null pour un jeton expiré ;
- `ScoreStore::record()` compare le moment de la note à celui de la note gardée et écrit en une seule étape atomique —
  une écriture conditionnelle, ou un verrou qui couvre aussi deux premières notes en même temps —, et répond s'il a
  gardé la note : jamais une note ne remplace une note plus récente, quoi que fassent deux écritures simultanées ;
- `LineItemStore::list()` et `ScoreStore::scores()` répondent dans un ordre stable, pour que les pages ne se
  chevauchent pas ; `LineItemStore::delete()` retire aussi les notes du line item.

Les adaptateurs en mémoire oublient tout au `reset()`, qu'un *worker* appelle entre les requêtes.

## Ce qui reste fermé

Les vérifications d'un jeton, d'un lancement, d'une requête d'authentification, d'une assertion et d'une réponse de
Deep Linking ne sont pas des points d'extension : leurs classes sont finales, et les changer, c'est changer le paquet.
