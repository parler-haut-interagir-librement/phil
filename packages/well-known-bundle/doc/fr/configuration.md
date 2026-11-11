<!-- phil-dev source-sha256=84dadb05b9bccee5187d861420c8b9e1c9aaa7324b2d7a5c5abfe461756bc031 -->
# Configuration

Un exemple, avec chaque document activé ; les valeurs par défaut sont dans les tableaux ci-dessous.

```yaml
phil_well_known:
    cache_max_age: 3600
    security_txt:
        contact: ['mailto:security@example.com', 'https://example.com/security']
        expires: '2027-06-30T00:00:00Z'
        encryption: ['https://example.com/pgp-key.txt']
        acknowledgments: ['https://example.com/hall-of-fame']
        preferred_languages: [fr, en]
        canonical: ['https://example.com/.well-known/security.txt']
        policy: ['https://example.com/disclosure']
        hiring: ['https://example.com/jobs']
        signer: App\Security\GpgSigner
        expiry_warning_days: 30
    change_password:
        route: account_password
        parameters: { tab: security }
    webfinger:
        resources:
            'acct:contact@example.com':
                aliases: ['https://example.com/']
                properties: { 'http://schema.org/name': Example }
                links:
                    - { rel: 'http://webfinger.net/rel/profile-page', type: text/html, href: 'https://example.com/' }
    nodeinfo:
        path: /nodeinfo/2.1
        software: { name: example, version: 1.4.0, repository: 'https://example.com/code' }
        protocols: [activitypub]
        services: { inbound: [], outbound: [rss2.0] }
        open_registrations: false
        metadata: { nodeName: Example }
        provider: App\Federation\NodeInfo
    did:
        file: '%kernel.project_dir%/config/well-known/did.json'
    did_configuration:
        files: ['%kernel.project_dir%/config/well-known/domain-linkage.jwt']
    atproto:
        did: 'did:plc:ewvi7nxzyoun6zhxrhs64oiz'
        hosts: { 'alice.example.com': 'did:plc:4c6myh2vbevdtl3jb5zn6s7a' }
    apple_app_site_association:
        document:
            applinks: { details: [{ appIDs: [ABCDE12345.com.example.app], components: [{ '/': '/events/*' }] }] }
    asset_links:
        android_apps:
            - package_name: com.example.app
              sha256_cert_fingerprints: ['14:6D:E9:83:C5:73:06:50:D8:EE:B9:95:2F:34:FC:64:16:A0:83:42:E6:1D:BE:A8:8A:04:96:B2:3F:CF:44:E5']
    documents:
        'matrix/server':
            content: '{"m.server": "matrix.example.com:443"}'
            content_type: application/json
            cors: true
```

Une section est activée par n'importe laquelle de ses clés, par `true` ou par `enabled: true` ; `enabled: false` la
désactive et garde ses valeurs, qui sont quand même vérifiées.

| Option | Valeur par défaut | Rôle |
|---|---|---|
| `cache_max_age` | `3600` | Les secondes pendant lesquelles les navigateurs et les caches partagés peuvent garder une réponse réussie ; `0` ne la laisse dans aucun ([Documents](documents.md#en-têtes)) |

## security_txt

| Option | Valeur par défaut | Rôle |
|---|---|---|
| `contact` | `[]` | Des URI `mailto:`, `tel:` ou `https://` ; au moins une |
| `expires` | `null` | Obligatoire : une date comme `2027-06-30` ou `2027-06-30T00:00:00Z`, lue en UTC sans décalage, dans le futur à la construction du conteneur ; une date relative comme `+1 year` est refusée |
| `encryption` | `[]` | Des URI `https://`, `dns:` ou `openpgp4fpr:` de la clé avec laquelle les signalements sont chiffrés |
| `acknowledgments` | `[]` | Des URL `https://` de la page qui remercie les personnes qui signalent |
| `preferred_languages` | `[]` | Des étiquettes de langue, écrites sur une seule ligne |
| `canonical` | `[]` | Des URL `https://` où le fichier est publié |
| `policy` | `[]` | Des URL `https://` de la politique de divulgation |
| `hiring` | `[]` | Des URL `https://` d'emplois en sécurité |
| `signer` | `null` | L'identifiant d'un service `SecurityTxtSigner` ; le fichier est servi signé par lui |
| `expiry_warning_days` | `30` | De 1 à 365 : les jours avant `Expires` à partir desquels la vérification de santé est dégradée ([Documents](documents.md#lexpiration-comme-vérification-de-santé)) |

Aucune valeur ne contient d'espace ni de caractère de contrôle, et aucune ne dépasse 2 048 octets. La RFC 9116
conseille un `Expires` à moins d'un an : un déploiement qui le renouvelle renouvelle le fichier. Comme la date est
vérifiée à la construction du conteneur, un conteneur construit avant la date et gardé après elle sert un fichier
expiré ; le reconstruire échoue.

## change_password

| Option | Valeur par défaut | Rôle |
|---|---|---|
| `route` | `null` | La route de la page où un utilisateur change son mot de passe |
| `parameters` | `[]` | Les paramètres de cette route |
| `url` | `null` | Ou une URL : `https://`, ou un chemin qui commence par `/` mais pas par `//` |

L'une de `route` et `url` est obligatoire.

## webfinger

| Option | Valeur par défaut | Rôle |
|---|---|---|
| `resources` | `{}` | Des ressources fixes, indexées par une URI `acct:` ou `https:`, avec `subject`, `aliases`, `properties` et `links` (`rel`, `type`, `href`, `template`, `titles`, `properties`) |

Une clé est lue comme une ressource : son schéma et son hôte sont écrits en minuscules, comme les requêtes. Voir
[WebFinger](webfinger.md).

## nodeinfo

| Option | Valeur par défaut | Rôle |
|---|---|---|
| `path` | `/nodeinfo/2.1` | Où le document 2.1 est servi : un chemin absolu |
| `software` | `phil`, `0.1.0` | `name`, `version`, `repository` et `homepage`, quand aucun fournisseur ne les dit |
| `protocols` | `[activitypub]` | Des protocoles du schéma 2.1 ; au moins un |
| `services.inbound`, `services.outbound` | `[]` | Des services du schéma 2.1 |
| `open_registrations` | `false` | Si le site accepte de nouveaux utilisateurs, quand aucun fournisseur ne le dit |
| `metadata` | `{}` | Des métadonnées libres, un tableau associatif, quand aucun fournisseur ne les dit |
| `provider` | `null` | L'identifiant d'un service `NodeInfoProvider`, qui dit alors le logiciel, l'utilisation, les inscriptions et les métadonnées |

## did et apple_app_site_association

| Option | Valeur par défaut | Rôle |
|---|---|---|
| `document` | `null` | Le document, en tableau associatif |
| `file` | `null` | Ou un fichier JSON qui le contient, lu à la construction du conteneur et surveillé pour ses modifications |

L'un des deux est obligatoire. L'`id` du document DID est un identifiant `did:web`.

## did_configuration

| Option | Valeur par défaut | Rôle |
|---|---|---|
| `linked_dids` | `[]` | Des attestations de lien de domaine : des JWT, ou des attestations JSON-LD en tableaux associatifs |
| `files` | `[]` | Des fichiers qui contiennent chacun une attestation : un JWT, ou un objet JSON |

Au moins une attestation est obligatoire. Le bundle sert les attestations ; il ne les émet ni ne les vérifie.

## atproto

| Option | Valeur par défaut | Rôle |
|---|---|---|
| `did` | `null` | Le DID de l'identifiant propre au site |
| `hosts` | `{}` | Le DID d'autres hôtes pour lesquels le site répond, comme des identifiants sur des sous-domaines |

Au moins un DID est obligatoire.

## asset_links

| Option | Valeur par défaut | Rôle |
|---|---|---|
| `android_apps` | `[]` | `package_name`, `sha256_cert_fingerprints` (32 octets en hexadécimal majuscule, séparés par des deux-points) et `relations`, `delegate_permission/common.handle_all_urls` par défaut |
| `statements` | `[]` | D'autres déclarations, écrites telles qu'elles sont servies |

Au moins une application ou une déclaration est obligatoire.

## documents

Indexés par leur chemin sous `/.well-known/`, comme `matrix/server`.

| Option | Valeur par défaut | Rôle |
|---|---|---|
| `content` | `null` | Le corps |
| `file` | `null` | Ou un fichier qui le contient, lu à la construction du conteneur |
| `content_type` | | Obligatoire : son type de média |
| `cors` | `false` | Si les navigateurs d'autres sites peuvent le lire |
