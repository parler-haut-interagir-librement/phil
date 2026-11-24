<!-- phil-dev source-sha256=f90df73a1fd195a019880168e1a65a9699208d4277d5d52fe802bcc80bf849f3 -->
# Tests

Les tests sont des tests PHPUnit dans `tests/Unit`, lancés depuis la racine du monodépôt avec `composer test`, ou seuls :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

`tests/Integration` lance `DbalReplayGuard` sur MySQL 8.4 et PostgreSQL 17, lus dans `PHIL_TEST_MYSQL_URL` et
`PHIL_TEST_POSTGRESQL_URL` — `composer test:services` à la racine les démarre —, et est ignoré sans eux : la seconde
insertion refusée par le serveur, un seul gagnant sur deux connexions, la purge, et une expiration écrite dans un autre
fuseau horaire lue en UTC. `tests/Unit` lance la même garde sur SQLite en mémoire.

- L'horloge est le `MockClock` de Symfony, une minute après l'émission des jeux de données d'essai, le 2026-11-19 à
  12 h UTC.
- Les réserves de cache sont l'`ArrayAdapter` de Symfony, le client HTTP son `MockHttpClient`, avec l'hôte résolu vers
  une adresse publique, ou vers une adresse privée pour la voir refusée.

## Clés d'essai

`tests/Fixtures/keys` contient des clés RSA et des certificats auto-signés faits pour ces tests seulement, avec
OpenSSL :

```sh
openssl req -x509 -newkey rsa:2048 -nodes -keyout entra.key -out entra.crt -subj "/CN=Test Entra ID signing" \
    -not_before 20260101000000Z -not_after 21260101000000Z -sha256
```

`entra` et `okta` signent comme les deux fournisseurs d'identité, `sp` signe et déchiffre comme le fournisseur de
service, `attacker` signe ce qui doit être refusé ; `weak` est une clé de 1024 bits et `expired` a expiré le
2021-01-01. Elles ne protègent rien : ne les utilisez jamais ailleurs.

## Jeux de données d'essai

`tests/Fixtures/responses` contient des réponses faites comme Microsoft Entra ID et Okta les envoient — espaces de noms
par défaut et URI de revendications pour l'un, préfixes `saml2`, un format d'`Issuer` et des valeurs d'attribut typées
dont le préfixe `xs` n'est conservé que par une liste `InclusiveNamespaces` pour l'autre —, signées et chiffrées, ainsi
que les attaques :

| Jeu de données | Ce qu'il contient |
|---|---|
| `entra-signed-assertion.xml` | Microsoft Entra ID par défaut : l'assertion signée, la réponse non. |
| `entra-signed-response-and-assertion.xml` | Les deux signées. |
| `entra-encrypted.xml` | L'assertion signée chiffrée en AES-256-CBC, la clé en RSA-OAEP à l'intérieur des données. |
| `entra-error-status.xml` | Un statut `Responder`, non signé. |
| `okta-signed.xml` | L'assertion signée avec le préfixe `xs` conservé, puis la réponse. |
| `okta-encrypted.xml` | AES-256-GCM, la clé en `xmlenc11#rsa-oaep` sur SHA-256 à côté des données, la réponse signée. |
| `attack-xsw-extensions.xml` | L'assertion signée déplacée dans une extension, une assertion falsifiée à sa place. |
| `attack-xsw-duplicate-id.xml` | Un élément portant l'ID de la réponse signée planté à côté d'elle. |
| `attack-signature-moved.xml` | La signature de la réponse déplacée dans l'assertion. |
| `attack-modified.xml` | L'identifiant de nom changé après la signature. |
| `attack-comment.xml` | Un commentaire planté dans l'identifiant de nom après la signature. |
| `attack-attacker-key.xml` | Signée par une autre clé, son certificat dans `KeyInfo`. |
| `attack-unsigned.xml` | Rien de signé. |
| `attack-sha1.xml` | RSA-SHA1 avec une empreinte SHA-1. |
| `weak-key.xml` | Signée par la clé de 1024 bits. |

Ils sont écrits par `tests/Fixtures/generate.php` — `php packages/saml-sp/tests/Fixtures/generate.php` depuis la racine
du monodépôt — avec le signataire et le chiffreur de `tests/Support`, écrits pour les tests à part de la bibliothèque,
de sorte que le vérificateur soit éprouvé sur des signatures que la bibliothèque n'a pas écrites. Les cas d'expiration,
d'audience, de destinataire, de destination et de rejeu sont construits par les tests à partir des mêmes formes.

## Cible de fuzzing

`saml-xml`, dans `tests/Fuzz/xml.php`, lancée par `composer fuzz` : l'entrée va à `SafeXml` dans un budget de 8 Kio,
16 niveaux et 512 nœuds, au processeur de réponses comme champ posté, au lecteur de métadonnées et au lecteur d'une
requête redirigée. Quoi qu'elle contienne, chacun se termine par un résultat ou une `SamlException`. Les germes sont
dans `tests/Fuzz/corpus/saml-xml`.

## Banc d'essai

`tests/Benchmark/ResponseBench.php`, lancé par `composer bench` : une requête signée sur la redirection, une réponse
dont l'assertion est signée, une dont la réponse et l'assertion sont signées, et une assertion chiffrée.
