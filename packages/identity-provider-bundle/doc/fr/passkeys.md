<!-- phil-dev source-sha256=32baed428d54b8d89a8a72dce64413b4738f76f7ffad420b718e69721f0d78be -->
# Clés d'accès

Le module `passkeys` permet à un compte d'enregistrer des clés d'accès — des identifiants WebAuthn détectables — et de
se connecter avec elles, sans mot de passe. Une clé d'accès ne peut pas être hameçonnée : le navigateur ne signe que
pour la partie de confiance pour laquelle elle a été créée.

## Configuration

```yaml
phil_identity_provider:
    modules: { passkeys: true }
    passkeys:
        rp_id: example.com
        origins: ['https://example.com', 'https://www.example.com']
        require_user_verification: false
```

`rp_id` est le domaine enregistrable auquel appartiennent les clés d'accès ; chaque origine d'où les pages sont servies
est listée. Avec `require_user_verification`, un authentificateur doit vérifier la personne par un NIP ou une donnée
biométrique.

## Les cérémonies

| Étape | Route | Ce qui se passe |
|---|---|---|
| Options d'enregistrement | `POST /account/passkeys/options` | Connecté, après une preuve récente : un défi gardé dans la session pour une seule réponse, les options de `navigator.credentials.create()`. |
| Enregistrement | `POST /account/passkeys/register?name=` | Le JSON de `PublicKeyCredential.toJSON()`, vérifié, conservé. |
| Options de connexion | `POST /passkey/options` | Un défi ; déconnecté, aucun identifiant n'est listé, de sorte que le navigateur offre les clés d'accès qu'il détient. |
| Connexion | `POST /passkey/login` | `PasskeyAuthenticator` vérifie la réponse et connecte le compte ou, s'il est connecté, le réauthentifie. |

Les requêtes JSON portent le jeton CSRF dans l'en-tête `X-CSRF-Token`.

## Ce qui est vérifié

`RelyingParty` vérifie, à l'enregistrement :

- les données du client : la cérémonie `webauthn.create`, le défi émis, une origine permise, aucun cadre inter-origines ;
- l'objet d'attestation, lu par un lecteur CBOR borné en profondeur, en éléments et en longueurs ; aucune attestation
  n'est demandée, et une déclaration qu'un authentificateur envoie quand même n'est pas crue ;
- les données de l'authentificateur : le SHA-256 de l'identifiant de la partie de confiance, la présence de
  l'utilisateur, la vérification quand elle est exigée, l'identifiant et sa clé COSE — ES256 sur P-256, RS256 de 2048
  bits ou plus, ou EdDSA sur Ed25519.

À la connexion, les mêmes vérifications des données du client avec `webauthn.get`, la signature sur les données de
l'authentificateur et l'empreinte des données du client, le descripteur d'utilisateur quand il est donné, et le
compteur : quand l'authentificateur en tient un, une valeur qui n'avance pas est refusée, comme le montrerait un
authentificateur cloné. Chaque refus répond `passkey.refused`, de sorte qu'une page ne révèle rien dont un attaquant
pourrait se servir.

Les preuves sont `hwk` et `user` (RFC 8176), et `mfa` quand l'authentificateur a vérifié la personne.

## La liste

`/account/passkeys` liste les clés d'accès du compte avec leur nom, si elles sont synchronisées entre appareils, le
moment où elles ont été ajoutées et utilisées pour la dernière fois. Une clé d'accès se renomme sur-le-champ ; en
retirer une exige une preuve récente, et la dernière façon de se connecter — la dernière clé d'accès d'un compte sans
mot de passe — ne peut pas être retirée.

## Le contrôleur Stimulus

Les gabarits `passkey/passkeys.html.twig` et `passkey/_sign_in.html.twig` pilotent le contrôleur `passkey` de
`@phil/identity-provider-bundle`, qui utilise `PublicKeyCredential.parseCreationOptionsFromJSON()`,
`parseRequestOptionsFromJSON()` et `toJSON()` ; un navigateur qui ne les a pas affiche un message. Une page de
l'application inclut `_sign_in.html.twig` à côté de son formulaire de connexion.
