<!-- phil-dev source-sha256=a8d5035cde0987ce930554d6bdc68e6397ff6df4d8fb287a2e4c6271cfd38d64 -->
# Okta

Chaque organisation crée une application dans sa propre organisation Okta ; l'application obtient un fournisseur
d'identité par organisation.

## 1. Créer l'application

Dans l'Okta Admin Console, **Applications** → **Create App Integration** → **SAML 2.0**. Dans **Configure SAML** :

| Champ | Valeur |
|---|---|
| Single sign-on URL | `https://app.example.org/saml/acme/acs`, « Use this for Recipient URL and Destination URL » coché |
| Audience URI (SP Entity ID) | `https://app.example.org/saml/acme/metadata` |
| Name ID format | Persistent, ou EmailAddress |
| Application username | Okta username |
| Response | Signed |
| Assertion Signature | Signed |
| Signature Algorithm | RSA-SHA256 |

## Déclarations d'attributs

Le préréglage `okta` lit ces déclarations ; ajoutez-les sous **Attribute Statements** :

| Nom | Valeur |
|---|---|
| `user_id` | `user.id` — l'identifiant stable sur lequel les comptes sont indexés |
| `email` | `user.email` |
| `firstName` | `user.firstName` |
| `lastName` | `user.lastName` |
| `displayName` | `user.displayName` |

et, sous **Group Attribute Statements**, `groups` avec un filtre, « Matches regex » `.*` ou un filtre plus étroit. Les
valeurs sont des noms de groupes ; associez-les avec `group_roles`.

Sans `user_id`, l'identifiant de nom est l'identifiant : avec le format Persistent, il est stable ; avec EmailAddress,
une adresse qui change donne à la personne un nouveau compte.

## 2. Configurer le fournisseur d'identité

Dans l'onglet **Sign On**, copiez la **Metadata URL** :

```yaml
phil_saml_sp:
    identity_providers:
        acme:
            metadata_url: 'https://example.okta.com/app/<application ID>/sso/saml/metadata'
            preset: okta
            binding: redirect
```

## 3. Attribuer les personnes

Dans **Assignments**, attribuez les personnes ou les groupes qui peuvent se connecter.

## Déconnexion unique

Sous **Advanced Settings**, cochez **Enable Single Logout**, définissez la **Single Logout URL** à
`https://app.example.org/saml/acme/slo`, le **SP Issuer** à l'identifiant d'entité, et téléversez le **Signature
Certificate** : le certificat de `signing`. Okta publie alors son service de déconnexion dans les métadonnées, et le
bundle transmet la déconnexion dans les deux sens.

## Chiffrement

Sous **Advanced Settings**, définissez **Assertion Encryption** à Encrypted, choisissez AES256-GCM, RSA-OAEP, et
téléversez le certificat de `encryption` ; puis définissez `want_assertions_encrypted: true`.

## Connexion lancée par le fournisseur d'identité

Une personne qui ouvre l'application depuis le tableau de bord d'Okta arrive avec une réponse non sollicitée, refusée
par défaut. Pour la permettre, définissez `allow_unsolicited: true` et, dans Okta, un **Default RelayState** qui est un
chemin local comme `/courses` ; voir [Sécurité](security.md#réponses-non-sollicitées).
