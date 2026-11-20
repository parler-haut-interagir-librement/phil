<!-- phil-dev source-sha256=bfb539c074183107d6bb4695c3f10d3e35a3ba45d6abb78c543c039e8e84bb33 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données ni réseau : le domaine — les
encodages, TOTP contre les vecteurs de la RFC 6238, PKCE contre ceux de la RFC 7636, les clés et les signatures JSON
Web, le lecteur CBOR et les vérifications WebAuthn contre un authentificateur logiciel —, les services d'application sur
des magasins en mémoire, les magasins sur SQLite, les adaptateurs et la configuration. Dans le monodépôt, Infection
maintient le paquet à 100 % de mutants couverts.

Les tests fonctionnels, sous `tests/Functional`, démarrent une petite application avec tous les modules activés et
jouent les parcours dans un navigateur : inscription et vérification, verrou, mot de passe oublié, deuxième étape,
réauthentification, clés d'accès, sessions fermées depuis un autre appareil, liens de connexion, numéros de téléphone,
un fournisseur fédéré, les commandes, et tout le parcours OpenID Connect avec découverte, consentement, jetons,
userinfo, introspection, révocation et déconnexion.

Un test qui exige un vrai serveur lit son URL dans une variable d'environnement et est ignoré sans elle : les magasins
repassent sur MySQL 8.4 avec `PHIL_TEST_MYSQL_URL` et sur PostgreSQL 17 avec `PHIL_TEST_POSTGRESQL_URL`
(`tests/Integration`).

## Bancs d'essai

`tests/Benchmark/JwsBench.php` signe un jeton d'identité et vérifie un jeton d'accès avec chaque algorithme, le travail
de chaque requête de jeton et de chaque appel à userinfo :

```sh
vendor-bin/phpbench/vendor/bin/phpbench run packages/identity-provider-bundle/tests/Benchmark --report=phil
```

## La suite de conformité OpenID

`tests/Conformance/run.sh` joue la suite de conformité de l'OpenID Foundation contre le fournisseur dans Docker ; voir
[Conformité OpenID](openid-conformance.md).

## Fuzzing

Les lecteurs de ce qu'envoient les navigateurs, les authentificateurs et les clients déclarent des cibles de fuzzing
dans `phil-dev.php`, lancées par `composer fuzz` dans le monodépôt à partir des germes de `tests/Fuzz/corpus/<target>` :

| Cible | Ce qu'elle lit |
|---|---|
| `cbor` | Des éléments CBOR et des clés COSE |
| `authenticator-data` | Les données d'authentificateur de WebAuthn |
| `client-data` | Les données du client de WebAuthn |
| `jwt` | Des jetons JSON Web, contre une seule clé connue |
| `authorization-request` | La requête d'une demande d'autorisation |
| `attestation` | Les déclarations d'attestation des nouvelles clés d'accès, `none` et `packed` |
