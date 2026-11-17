<!-- phil-dev source-sha256=fef8e710aa14b385d7691a4acca53d8408708ac2d29221208704aa06109436b1 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `PhilApiContractBundle` | Chaque service, argument, alias, étiquette et décoration ; les valeurs par défaut ; aucune connexion Doctrine sans DoctrineBundle ; le pool de cache et sa fabrique de verrous |
| `ProblemDetailsListener`, `ProblemException`, `ProblemDetailsResponse` | Les problèmes partout, les exceptions HTTP et les autres sous les chemins, le mode débogage, la borne `400` |
| `IdempotencyListener`, `Idempotent`, `IdempotencyReservation` | Réservation et expiration, rejeu avec les attributs de l'original et sans arguments, une clé nouvelle qui laisse le contrôleur et ses arguments, clé réutilisée, requête en cours avec son `Retry-After`, clé échouée exécutée de nouveau, clés manquantes et erronées, `5xx` et flux marqués échoués, réservation perdue au profit d'une autre requête (`409`, ou le `5xx` laissé tel quel), en-têtes rejoués, absence de stockage |
| `CallerScopeResolver`, `ActiveTenantResolver`, `TokenAttributeClientResolver`, `AnonymousCallers` | Le locataire, le client et l'utilisateur hachés ensemble ; deux locataires de `phil/tenancy-bundle`, deux clients, un locataire et un client du même nom gardés distincts ; un client sans utilisateur ; les appelants anonymes refusés, ou rattachés à leur adresse dans leur locataire ; l'attribut du jeton, absent, vide, qui n'est pas une chaîne ou non lu |
| `IdempotencySchemaListener` | La table de `phil/idempotency` dans le schéma de sa connexion, et pas d'une autre ; son SQL sur MySQL, MariaDB, PostgreSQL et SQLite, `utf8mb4_bin` sur MySQL et MariaDB ; sur de vrais MySQL et PostgreSQL, les portées et les clés qui diffèrent par la casse ou l'accent gardées comme des lignes distinctes. Les stockages eux-mêmes sont testés dans `phil/idempotency` |
| `DeprecatedEndpoint`, `DeprecationListener` | Les en-têtes d'un contrôleur obsolète, aucun sur un autre |
| `CacheObservedDeprecations`, `DeprecationObservingHttpClient`, `DeprecationsCommand` | Une fois par jour et par point de terminaison, le cache, les points de terminaison sans identifiants, le chemin lu comme le lit un navigateur (informations d'utilisateur, barres obliques inverses, segments à points, hôtes internationaux, adresses IP, URL relatives), une lecture par réponse, la sortie et le statut de la commande |
| `VerifyCommand`, `DocumentReader`, `ContractVerifierFactory` | JSON et YAML lus, les documents illisibles et la borne de taille, les règles de l'application et celles qui sont écartées, la sortie en texte et en JSON avec le texte du document échappé, les codes de sortie, les valeurs par défaut configurées, les options refusées |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite, SecurityBundle avec deux
utilisateurs et le bundle, un locataire par hôte, puis envoie des requêtes aux points de terminaison de `ApiController` :
une commande créée une fois par clé et rejouée, une clé réutilisée, une clé manquante, un échec exécuté de nouveau, deux
locataires et deux utilisateurs sur un même stockage avec la même clé, une permission révoquée durant la rétention et
refusée par `#[IsGranted]` sans le corps conservé, une requête refusée qui ne réserve aucune clé, un appelant anonyme
refusé, des problèmes sous `/api/` et pas ailleurs, les en-têtes d'obsolescence, sur un rejeu aussi ; le vérificateur est
construit et sa commande vérifie un document dans la console de l'application. Lorsque le bundle est installé seul, FrameworkBundle n'étant qu'un prérequis de
développement, la suite est sautée.

```sh
vendor/bin/phpunit packages/api-contract-bundle/tests
```

La suite d'intégration s'exécute contre les MySQL et PostgreSQL de `phil-dev services`, lus dans `PHIL_TEST_MYSQL_URL` et
`PHIL_TEST_POSTGRESQL_URL`, et est sautée sans eux :

```sh
vendor/bin/phil-dev services up
eval "$(vendor/bin/phil-dev services env)"
vendor/bin/phpunit packages/api-contract-bundle/tests/Integration
```
