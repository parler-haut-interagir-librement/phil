<!-- phil-dev source-sha256=8f161d4c475e8c7f251c7e252d2eaddc51f8edf7974cb6c5f46ef456048a3866 -->
# Microsoft Entra ID

Chaque organisation enregistre l'application dans son propre locataire Microsoft Entra ID ; l'application obtient un
fournisseur d'identité par locataire.

## 1. Configurer le fournisseur d'identité

```yaml
phil_saml_sp:
    identity_providers:
        acme:
            metadata_url: 'https://login.microsoftonline.com/<tenant ID>/federationmetadata/2007-06/federationmetadata.xml?appid=<application ID>'
            preset: entra_id
```

L'adresse se remplit à l'étape 3 ; d'ici là, n'importe quelle adresse HTTPS du locataire garde la configuration valide.

## 2. Créer l'application d'entreprise

Dans le centre d'administration Microsoft Entra, **Enterprise applications** → **New application** → **Create your own
application**, « Integrate any other application you don't find in the gallery (Non-gallery) ». Puis **Single
sign-on** → **SAML**, et dans **Basic SAML Configuration** :

| Champ | Valeur |
|---|---|
| Identifier (Entity ID) | `https://app.example.org/saml/acme/metadata` |
| Reply URL (Assertion Consumer Service URL) | `https://app.example.org/saml/acme/acs` |
| Sign on URL | `https://app.example.org/saml/acme/login` |
| Logout Url | `https://app.example.org/saml/acme/slo` |

Au lieu de les saisir, **Upload metadata file** prend le fichier que sert `https://app.example.org/saml/acme/metadata`.

## 3. Lire ses métadonnées

Dans **SAML Certificates**, copiez l'**App Federation Metadata Url** dans `metadata_url`. Microsoft signe ces
métadonnées : pour l'exiger, placez le certificat du locataire — **Certificate (Base64)**, téléchargé depuis la même
section — dans `metadata_certificates`.

## 4. Attribuer les personnes

Dans **Users and groups**, attribuez les utilisateurs ou les groupes qui peuvent se connecter. Microsoft Entra ID refuse
les autres avec `AADSTS50105` avant que l'application ne les voie.

## Revendications

Les revendications par défaut correspondent au préréglage `entra_id` :

| Champ | Revendication |
|---|---|
| `identifier` | `http://schemas.microsoft.com/identity/claims/objectidentifier` — l'identifiant d'objet, stable pour la personne |
| `email` | `http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress`, puis `…/claims/name` (le nom d'utilisateur principal) |
| `given_name`, `family_name` | `…/claims/givenname`, `…/claims/surname` |
| `display_name` | `http://schemas.microsoft.com/identity/claims/displayname` |
| `groups` | `http://schemas.microsoft.com/ws/2008/06/identity/claims/groups`, après **Add a group claim** |
| `roles` | `http://schemas.microsoft.com/ws/2008/06/identity/claims/role`, les rôles d'application attribués |

L'identifiant de nom est par défaut le nom d'utilisateur principal ; l'identifiant d'objet est ce sur quoi les comptes
sont indexés. Les groupes arrivent sous forme d'identifiants d'objet, et sont omis au-delà de 150 — préférez **Groups
assigned to the application**, ou les rôles d'application, et associez-les avec `group_roles` :

```yaml
            group_roles:
                '0f5b7f6e-1111-4c2d-9e3f-000000000010': ROLE_TEACHER
                Teacher: ROLE_TEACHER          # an app role
```

## Signature et chiffrement

- **Signing Option** : « Sign SAML assertion », la valeur par défaut, correspond à `want_assertions_signed: true` ;
  « Sign SAML response and assertion » convient aussi. « Sign SAML response » seule exige
  `want_assertions_signed: false`.
- **Signing Algorithm** : SHA-256. SHA-1 est refusé sauf si `allow_sha1` est défini.
- **Token encryption** : importez le certificat du fournisseur de service — celui de `encryption` — et activez-le ;
  puis définissez `want_assertions_encrypted: true`. Microsoft Entra ID chiffre en AES-256-CBC, ce que le bundle lit.

## Renouvellement de certificat

Microsoft Entra ID publie le certificat de signature suivant dans les métadonnées avant de l'utiliser : avec
`metadata_url`, le bundle lit les deux, dans les limites de `metadata_lifetime`. Avec `metadata_file` ou
`metadata_xml`, remplacez le fichier quand le nouveau certificat apparaît ; `phil:saml:check` signale l'expiration de
chacun.

## Dépannage

| Refus (`SamlResponseRefused::$reason`) | Cause |
|---|---|
| The service provider is not an audience of the assertion. | L'Identifier (Entity ID) diffère de l'identifiant d'entité des métadonnées. |
| The destination of the response is not the assertion consumer service. | La Reply URL diffère, ou l'application est derrière un mandataire qui change le schéma ou l'hôte : configurez les mandataires de confiance de Symfony. |
| The signature does not verify with the certificates of the identity provider. | Les métadonnées sont périmées, ou le certificat a été renouvelé : lancez `phil:saml:check`. |
| The response does not answer the request of this browser. | La connexion a été lancée depuis un autre navigateur, a pris plus de `request_lifetime`, ou a été lancée depuis le portail My Apps : voir `allow_unsolicited`. |
