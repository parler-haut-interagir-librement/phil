<!-- phil-dev source-sha256=79c1d340466cffe6770e181be8b55cd1c20846b80946f19ff5d760b8fe935e8f -->
# Comptes SAML

Les organisations dont les membres se connectent par un fournisseur d'identité SAML 2.0, comme Microsoft Entra ID ou
Okta, atteignent l'application par
[`phil/saml-sp-bundle`](https://github.com/parler-haut-interagir-librement/saml-sp-bundle). Avec `saml` activé, ces
connexions aboutissent aux comptes du [`UserProvisioner`](provisioning.md) : un seul modèle de compte, que la personne
se connecte par le fournisseur OpenID Connect ou par le fournisseur d'identité de son organisation.

`phil/saml-sp-bundle` reçoit la réponse, vérifie sa signature, son audience, sa fenêtre de temps et qu'elle n'est
utilisée qu'une fois, puis remet l'identité acceptée à ce bundle. Rien ici ne lit de XML.

## L'activer

```bash
composer require phil/saml-sp-bundle
```

```yaml
# config/packages/phil_identity_client.yaml
phil_identity_client:
    # … the OpenID Connect provider, as before
    claims: ['name', 'email', 'groups']
    saml:
        enabled: true
        verified_email_from: [acme]   # the identity providers trusted to verify addresses
```

```yaml
# config/packages/phil_saml_sp.yaml
phil_saml_sp:
    signing:
        certificate: '%env(file:resolve:SAML_SP_CERTIFICATE)%'
        private_key: '%env(file:resolve:SAML_SP_PRIVATE_KEY)%'
    identity_providers:
        acme:
            metadata_url: 'https://login.example.net/acme/federationmetadata.xml'
            preset: entra_id
            create_accounts: true
```

Le bundle nomme son service `phil_identity_client.saml_accounts` dans `phil_saml_sp.accounts` ; un `accounts` que
l'application fixe elle-même l'emporte. Le pare-feu de la connexion SAML utilise le fournisseur d'utilisateurs de ce
bundle, pour qu'une requête ultérieure retrouve le compte par `UserProvisioner::find()` :

```yaml
# config/packages/security.yaml
security:
    firewalls:
        saml:
            pattern: ^/saml
            provider: phil_identity_client
            # … the authenticator of phil/saml-sp-bundle
```

## Comptes créés à la première connexion

À chaque connexion, on demande à `UserProvisioner::find()` le sujet de la personne. S'il trouve le compte, `provision()`
le met à jour avec les revendications de cette connexion. S'il n'en trouve aucun, `provision()` crée le compte si le
fournisseur d'identité a `create_accounts: true`, et la personne est refusée sinon.

Les comptes ne sont pas approvisionnés avant la première connexion, ni retirés quand une personne quitte l'organisation :
il n'y a pas de SCIM. Un compte qui ne sert plus se repère à sa dernière connexion.

## L'identité

| `VerifiedIdentity` | Valeur |
|---|---|
| `issuer` | L'identifiant d'entité du fournisseur d'identité, tel que son assertion signée l'énonce |
| `subject` | La clé du fournisseur d'identité, `\|`, et l'identifiant stable de la personne chez lui : `acme\|5f0c1a52-…` |
| `claims` | Les champs de l'assertion sous leurs noms OpenID Connect, filtrés par `claims` |

Le sujet est unique d'un fournisseur d'identité à l'autre, et c'est l'identifiant que `find()` reçoit aux requêtes
suivantes : l'utilisateur que renvoie l'approvisionneur l'a pour identifiant d'utilisateur. L'identifiant stable est
celui que lit la table d'attributs du fournisseur d'identité, comme l'identifiant d'objet de Microsoft Entra ID ou le
`user_id` d'Okta, sinon l'identifiant de nom.

| Revendication | Tirée de l'assertion |
|---|---|
| `email` | L'adresse courriel, seulement d'un fournisseur d'identité de `verified_email_from` |
| `email_verified` | Si le fournisseur d'identité figure dans `verified_email_from` |
| `given_name`, `family_name` | Les noms |
| `name` | Le nom d'affichage |
| `groups`, `roles` | Les groupes et les rôles, sous forme de listes |
| `acr` | La classe de contexte d'authentification |
| `auth_time` | L'instant de l'authentification, en secondes depuis l'époque Unix |
| `sid` | L'indice de session chez le fournisseur d'identité |

La liste `claims` s'applique comme pour le fournisseur OpenID Connect : une revendication qu'elle ne nomme pas n'atteint
jamais l'approvisionneur. Un champ vide est omis.

## Adresses courriel

SAML n'a aucune déclaration qui atteste qu'une adresse a été vérifiée. Une adresse n'atteint l'approvisionneur que d'un
fournisseur d'identité que l'application liste dans `verified_email_from`, comme un `email` OpenID Connect n'y parvient
qu'avec `email_verified`. `VerifiedIdentity::verifiedEmail()` vaut donc null pour tout autre fournisseur d'identité, et
une organisation ne peut pas prendre un compte en envoyant l'adresse de quelqu'un d'autre. Indexez le compte sur le
sujet, jamais sur l'adresse seule.

## Groupes et rôles

`groups` et `roles` sont ce que l'organisation affirme de ses propres membres. Associez-les à des rôles de l'application
dans l'approvisionneur, ou par `group_roles` de `phil/saml-sp-bundle` ; n'accordez jamais un rôle de l'application d'après
le nom qu'une organisation envoie.
