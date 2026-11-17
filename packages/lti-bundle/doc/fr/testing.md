<!-- phil-dev source-sha256=6a76c7f924941ebee9fade4920dd0d199efae1061092f4ab4dc3c0705a86edec -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

| Suite | Ce qu'elle couvre |
|---|---|
| `tests/Unit` | La configuration et ses services, les valeurs par défaut de la passe du compilateur ; un contrat des magasins, tenu sur SQLite en mémoire ; les clés fabriquées, renouvelées et élaguées ; le scelleur sur un KMS local ; le publicateur de notes avec et sans bus, les reprises de son *handler*, la reprise des notes jamais envoyées par le bus et les refus gardés ; les réponses de Deep Linking gardées et reprises ; deux écritures d'une même note entrelacées exprès, par un middleware DBAL ; l'utilisateur d'un lancement et son fournisseur ; les commandes. |
| `tests/Functional` | Une application qui est une plateforme et un outil enregistré auprès d'elle-même : la plateforme démarre un lancement, l'outil initie la connexion, la plateforme signe le lancement pour son membre, l'outil le vérifie et connecte le membre ; les refus ; le jeu de clés ; le point de terminaison des jetons ; les services de la plateforme appelés avec des jetons de chaque portée ; une réponse de Deep Linking renvoyée, et une dont l'écouteur a échoué, reprise sans son jeton ; les line items paginés par défaut et au maximum ; le schéma de la première version mis à niveau. |
| `tests/Integration` | Le même contrat des magasins sur MySQL 8.4 et PostgreSQL 17, avec les écritures entrelacées d'une note et la mise à niveau du schéma. |

Les clés des tests sont générées à la première exécution et gardées dans le répertoire temporaire, de sorte qu'aucune
clé privée n'est versée au dépôt. Les tests d'intégration lisent l'URL de leur serveur dans `PHIL_LTI_TEST_MYSQL_URL` et
`PHIL_LTI_TEST_POSTGRESQL_URL`, sinon dans `PHIL_TEST_MYSQL_URL` et `PHIL_TEST_POSTGRESQL_URL`, que `phil-dev services
up` et `phil-dev services env` donnent pour le MySQL 8.4 et le PostgreSQL 17 qu'il démarre dans Docker ; ils sont
ignorés sans elle, et créent leurs tables sous le préfixe `phil_lti_test_`, en les supprimant d'abord.

## Tester une application

Configurez le magasin `memory` et des clés de la configuration dans l'environnement de test : les magasins en mémoire
tiennent le même contrat. Pour tester un lancement de bout en bout, donnez à l'application une source de jeu de clés qui
répond ses propres clés publiques, comme le fait l'application de test du bundle, et construisez le lancement avec
`LaunchIssuer`.
