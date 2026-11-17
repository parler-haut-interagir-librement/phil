<!-- phil-dev source-sha256=1eda74a1b51e9ff0addf1cc2568511ddbbafca5f8c6c8aa8486bcc97c4e893d6 -->
# Stockage

Avec `store: dbal`, le bundle garde son état dans huit tables d'une même connexion DBAL, sous le préfixe
`table_prefix`. Les identifiants se comparent octet par octet : `utf8mb4_bin` sur MySQL et MariaDB, `C` sur PostgreSQL.
Les moments sont des entiers de secondes depuis l'epoch, en UTC, sauf le moment d'une note, en microsecondes.

| Table | Colonnes | Ce qu'elle garde |
|---|---|---|
| `phil_lti_nonce` | `scope`, `value_hash`, `expires_at` | Les valeurs employées une seule fois — les nonces des lancements et des réponses de Deep Linking, les `jti` des assertions —, par leur SHA-256. La clé primaire fait de la vérification et de l'écriture une seule étape. |
| `phil_lti_login_state` | `state_hash`, `nonce`, `issuer`, `client_id`, `target`, `expires_at` | Les états de connexion de l'outil, par le SHA-256 de l'état ; un état est pris en supprimant sa ligne. |
| `phil_lti_access_token` | `token_hash`, `client_id`, `scopes`, `expires_at` | Les jetons d'accès de la plateforme, par le SHA-256 de leur valeur seulement. |
| `phil_lti_key` | `kid`, `sealed`, `created_at`, `retired_at` | Les clés, chaque clé privée scellée par le KMS comme le JSON de sa JSON Web Key privée. |
| `phil_lti_line_item` | `id`, `client_id`, `context_id`, `score_maximum`, `label`, `resource_id`, `resource_link_id`, `tag`, `start_at`, `end_at`, `grades_released` | Les line items de la plateforme. |
| `phil_lti_score` | `line_item_id`, `user_id`, `payload`, `scored_at` | La dernière note de chaque apprenant sur chaque line item, telle qu'AGS l'écrit. Une note est écrite par une mise à jour gardée par `scored_at`, sinon par une insertion, et de nouveau par la mise à jour gardée quand une autre écriture a inséré en premier : aucune écriture ne remplace une note par une plus ancienne. |
| `phil_lti_pending_score` | `id`, `issuer`, `client_id`, `line_item`, `payload`, `created_at`, `dispatched_at`, `claimed_at`, `refused_at`, `refusal` | Les notes que l'outil doit encore envoyer : quand le bus a pris le message, quand une reprise a réclamé la note en dernier — par une mise à jour gardée par ce moment —, et quand et pourquoi la plateforme l'a refusée pour de bon. |
| `phil_lti_deep_linking` | `id`, `client_id`, `payload`, `received_at`, `expires_at`, `handled_at` | Les réponses de Deep Linking que la plateforme a vérifiées, par leur clé : leurs revendications en JSON, jusqu'à leur expiration, et quand l'application les a traitées. |

Avec l'ORM, `LtiSchemaListener` ajoute les tables au schéma que l'ORM génère pour la connexion, de sorte que
`doctrine:migrations:diff` écrit leur migration. Sans lui, `phil:lti:schema` affiche le SQL qui crée les tables
manquantes et ajoute aux tables qui sont là les colonnes et les index qui leur manquent, et `--force` l'exécute ;
aucune colonne n'est changée ni supprimée.

## Mise à niveau depuis 0.1

La version 0.2 ajoute la table `phil_lti_deep_linking`, les colonnes nullables `dispatched_at`, `claimed_at`,
`refused_at` et `refusal` de `phil_lti_pending_score`, et ses index `phil_lti_pending_score_created` et
`phil_lti_pending_score_refused` ; les autres tables restent telles quelles.

- Avec l'ORM, `doctrine:migrations:diff` écrit la migration, et `doctrine:migrations:migrate` l'exécute.
- Sans lui, `phil:lti:schema` affiche les instructions, et `phil:lti:schema --force` les exécute.

Les notes en attente à la mise à niveau n'ont pas de `dispatched_at` : une fois le délai de `phil:lti:recover-scores`
écoulé, la reprise envoie de nouveau leur message, sans conséquence pour celles dont le message a déjà été pris — une
note envoyée deux fois est gardée une fois par son horodatage, et un message dont la note a été envoyée ne trouve rien à
envoyer.

## La purge

`phil:lti:purge` retire les nonces, les états de connexion, les jetons d'accès et les réponses de Deep Linking
expirés, et les notes refusées pour de bon avant `tool.refused_score_retention` ; exécutez-la chaque jour.
`phil:lti:recover-scores` renvoie les notes dont le message n'a jamais été pris ; exécutez-la toutes les quelques
minutes. Les clés retirées avant le chevauchement sont supprimées par `phil:lti:rotate-keys`. Les line items et les notes restent
jusqu'à ce qu'un outil les supprime, ou l'application.

## En mémoire

Avec `store: memory`, chaque magasin est dans le processus, vidé entre les requêtes d'un *worker*, et les clés viennent
de la configuration : pour les tests, jamais pour un site.
