<!-- phil-dev source-sha256=c5d6cfc254fcb15d3e1d302abffa145be6b85d541b4f09fc6cf51f0027a6bda3 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `PhilApiContractBundle` | Chaque service, argument, alias, étiquette et décoration ; les valeurs par défaut ; aucune connexion Doctrine sans DoctrineBundle ; le pool de cache et sa fabrique de verrous |
| `ProblemDetailsListener`, `ProblemException`, `ProblemDetailsResponse` | Les problèmes partout, les exceptions HTTP et les autres sous les chemins, le mode débogage, la borne `400` |
| `IdempotencyListener`, `Idempotent`, `IdempotencyReservation` | Réservation et expiration, rejeu, clé réutilisée, requête en cours avec son `Retry-After`, clé échouée exécutée de nouveau, clés manquantes et erronées, `5xx` et flux marqués échoués, en-têtes rejoués, absence de stockage |
| `CallerScopeResolver` | L'utilisateur, l'adresse, ni l'un ni l'autre |
| `IdempotencySchemaListener` | La table de `phil/idempotency` dans le schéma de sa connexion, et pas d'une autre ; les stockages eux-mêmes sont testés dans `phil/idempotency` |
| `DeprecatedEndpoint`, `DeprecationListener` | Les en-têtes d'un contrôleur obsolète, aucun sur un autre |
| `CacheObservedDeprecations`, `DeprecationObservingHttpClient`, `DeprecationsCommand` | Une fois par jour et par point de terminaison, le cache, les points de terminaison sans identifiants, le chemin lu comme le lit un navigateur (informations d'utilisateur, barres obliques inverses, segments à points, hôtes internationaux, adresses IP, URL relatives), une lecture par réponse, la sortie et le statut de la commande |
| `VerifyCommand`, `DocumentReader`, `ContractVerifierFactory` | JSON et YAML lus, les documents illisibles et la borne de taille, les règles de l'application et celles qui sont écartées, la sortie en texte et en JSON avec le texte du document échappé, les codes de sortie, les valeurs par défaut configurées, les options refusées |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite et le bundle, puis envoie des
requêtes aux points de terminaison de `ApiController` : une commande créée une fois par clé et rejouée, une clé
réutilisée, une clé manquante, un échec exécuté de nouveau, des problèmes sous `/api/` et pas ailleurs, les en-têtes
d'obsolescence, sur un rejeu aussi ; le vérificateur est construit et sa commande vérifie un document dans la console de
l'application. Lorsque le bundle est installé seul, FrameworkBundle n'étant qu'un prérequis de
développement, la suite est sautée.

```sh
vendor/bin/phpunit packages/api-contract-bundle/tests
```
