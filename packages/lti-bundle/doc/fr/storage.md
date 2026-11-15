<!-- phil-dev source-sha256=ceb8cd9494df164b10b9784d62dd98aa0276422afa31f5c87712698ec70dbf3f -->
# Stockage

Avec `store: dbal`, le bundle garde son état dans sept tables d'une même connexion DBAL, sous le préfixe
`table_prefix`. Les identifiants se comparent octet par octet : `utf8mb4_bin` sur MySQL et MariaDB, `C` sur PostgreSQL.
Les moments sont des entiers de secondes depuis l'epoch, en UTC.

| Table | Colonnes | Ce qu'elle garde |
|---|---|---|
| `phil_lti_nonce` | `scope`, `value_hash`, `expires_at` | Les valeurs employées une seule fois — les nonces des lancements et des réponses de Deep Linking, les `jti` des assertions —, par leur SHA-256. La clé primaire fait de la vérification et de l'écriture une seule étape. |
| `phil_lti_login_state` | `state_hash`, `nonce`, `issuer`, `client_id`, `target`, `expires_at` | Les états de connexion de l'outil, par le SHA-256 de l'état ; un état est pris en supprimant sa ligne. |
| `phil_lti_access_token` | `token_hash`, `client_id`, `scopes`, `expires_at` | Les jetons d'accès de la plateforme, par le SHA-256 de leur valeur seulement. |
| `phil_lti_key` | `kid`, `sealed`, `created_at`, `retired_at` | Les clés, chaque clé privée scellée par le KMS comme le JSON de sa JSON Web Key privée. |
| `phil_lti_line_item` | `id`, `client_id`, `context_id`, `score_maximum`, `label`, `resource_id`, `resource_link_id`, `tag`, `start_at`, `end_at`, `grades_released` | Les line items de la plateforme. |
| `phil_lti_score` | `line_item_id`, `user_id`, `payload`, `scored_at` | La dernière note de chaque apprenant sur chaque line item, telle qu'AGS l'écrit. |
| `phil_lti_pending_score` | `id`, `issuer`, `client_id`, `line_item`, `payload`, `created_at` | Les notes que l'outil doit encore envoyer. |

Avec l'ORM, `LtiSchemaListener` ajoute les tables au schéma que l'ORM génère pour la connexion, de sorte que
`doctrine:migrations:diff` écrit leur migration. Sans lui, `phil:lti:schema` affiche le SQL des tables manquantes, et
`--force` l'exécute ; une table qui est déjà là reste telle quelle.

## La purge

`phil:lti:purge` retire les nonces, les états de connexion et les jetons d'accès expirés ; exécutez-la chaque jour. Les
clés retirées avant le chevauchement sont supprimées par `phil:lti:rotate-keys`. Les line items et les notes restent
jusqu'à ce qu'un outil les supprime, ou l'application.

## En mémoire

Avec `store: memory`, chaque magasin est dans le processus, vidé entre les requêtes d'un *worker*, et les clés viennent
de la configuration : pour les tests, jamais pour un site.
