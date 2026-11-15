<!-- phil-dev source-sha256=64f455e0b6ceb30d80774e1c6ed75944aa0c1757770431740f4945aad7b5cfa5 -->
# Guide du réviseur

Pour la personne qui revoit la sécurité du bundle avant son utilisation en production. Lisez d'abord le
[modèle de menaces](threat-model.md) : il nomme les actifs, les frontières de confiance, les défenses et les risques
ouverts que cette revue vérifie.

## Portée

| Dans la portée | Où |
|---|---|
| Les JSON Web Signatures et les clés du fournisseur, sur `web-token/jwt-library` | `src/Application/Jose`, `src/Domain/Jose` (les algorithmes) |
| Le protocole OpenID Connect : demande d'autorisation, PKCE, portées | `src/Domain/Oidc` |
| Codes, jetons, rotation de l'actualisation, révocation, introspection, clés et leur rotation, clients | `src/Application/Oidc` |
| Les points de terminaison du fournisseur | `src/Infrastructure/Http/Oidc` |
| WebAuthn : CBOR, données d'authentificateur, données du client, clés COSE, signatures | `src/Domain/WebAuthn` |
| Pièces du pare-feu : authentificateurs, deuxième étape, réauthentification, sessions | `src/Infrastructure/Security` |
| Comptes, mots de passe, liens, verrou | `src/Application`, `src/Domain/Security`, `src/Domain/Totp` |

Hors de la portée : ce que [le modèle de menaces](threat-model.md#ce-que-lapplication-garde) laisse à l'application — les
limites de débit, la sécurité du transport, les en-têtes, le coffre à secrets, la base de données —, et
`web-token/jwt-library` elle-même au-delà de la façon dont le bundle l'appelle.

## Points d'entrée

L'entrée non fiable atteint le bundle à ces endroits ; chaque ligne nomme le premier code qui la lit.

| Entrée | Route | Premier lecteur |
|---|---|---|
| Demande d'autorisation (requête ou formulaire) | `/oidc/authorize` | `Domain\Oidc\AuthorizationRequestParser::parse()` |
| Réponse de consentement | `/oidc/consent` | `AuthorizeController::consent()`, CSRF vérifié d'abord |
| Requête de jeton, identifiants du client | `/oidc/token` | `ClientCredentials::from()`, puis `Tokens::authenticateClient()` |
| Jeton d'accès (en-tête ou corps de formulaire) | `/oidc/userinfo` | `UserinfoController`, puis `Tokens::bearer()` et `Jws::decode()` |
| Jeton à révoquer ou à introspecter | `/oidc/revoke`, `/oidc/introspect` | `Tokens::revoke()`, `Tokens::introspect()` après l'authentification du client |
| `id_token_hint` | `/oidc/logout` | `Tokens::idTokenHint()`, puis `Jws::decode()` |
| Enregistrement et assertion d'une clé d'accès | `/account/passkeys/register`, la connexion par clé d'accès | `Domain\WebAuthn\RelyingParty`, `Cbor` |
| Codes à usage unique | la deuxième étape, la réauthentification | `OneTimeCodeAuthenticator`, `Totp`, `RecoveryCodes` |
| Liens signés | `/verify-email`, `/reset-password`, liens de connexion | `SignedLinks`, l'authentificateur `login_link` |

Où regarder d'abord dans la couche JOSE :

- `Jws::decode()` : les bornes avant la bibliothèque, la recherche du `kid`, l'algorithme qui doit être celui de la clé,
  l'`AlgorithmManager` qui contient cet algorithme seul, chaque exception de la bibliothèque changée en refus ;
- `PrivateKey::of()` : les vérifications sur une clé relue du magasin — type, courbe, membres privés, taille RSA ;
- `SigningKeys::publicKey()` et `jwks()` : seules les clés publiées vérifient, les clés retirées jusqu'à la fin de leur
  rétention ;
- `Tokens::exchangeCode()` et `AuthorizationRequestParser` : PKCE exigé par client, et aucun vérificateur accepté pour
  un code émis sans défi.

## Exécuter les tests

Dans le monodépôt :

```sh
composer install
vendor/bin/phpunit -c phpunit.dist.xml packages/identity-provider-bundle/tests   # unit and functional suites
composer test:next                     # the tests on the next PHP, in Docker
composer phpstan                       # PHPStan at the maximum level
composer fuzz                          # the fuzz targets, among them jwt and authorization-request
composer infection                     # mutation testing
vendor-bin/phpbench/vendor/bin/phpbench run packages/identity-provider-bundle/tests/Benchmark --report=phil
```

Les cibles de fuzzing et leurs germes sont listées dans [Tests](testing.md#fuzzing).

## Exécuter la suite de conformité

```sh
packages/identity-provider-bundle/tests/Conformance/run.sh
```

Docker démarre la suite de l'OpenID Foundation à une version fixée et le fournisseur derrière https, prépare un compte
d'essai et deux clients, et joue les plans Basic OP et Config OP ; [Conformité OpenID](openid-conformance.md) explique la
mise en place, comment suivre l'exécution à `https://localhost.emobix.co.uk:8443`, et les résultats auxquels comparer.

## Signaler

Déposez en privé ce que vous trouvez, comme l'explique la [politique de sécurité](../../SECURITY.md), jamais dans un
ticket public. Nommez le fichier et la ligne, l'entrée qui le montre et la ligne du modèle de menaces qu'il contredit.
