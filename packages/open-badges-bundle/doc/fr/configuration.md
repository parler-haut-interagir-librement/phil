<!-- phil-dev source-sha256=be1cabe69830383c4db152a41f804036d9baac7a5db5c92a39cfadbcfaf773aa -->
# Configuration

```yaml
phil_open_badges:
    issuer:
        id: https://example.org/open-badges/issuer    # required: the URL the profile is served at
        name: Example                                  # required
        url: https://example.org
        email: badges@example.org
        image: https://example.org/logo.png
    key:
        algorithm: EdDSA                               # EdDSA (a seed of 32 bytes) or RS256 (an RSA key in PEM)
        secret: '%env(base64:OPEN_BADGES_SEED)%'       # required
        passphrase: null
    securing: jwt                                      # jwt or data_integrity
    allow_localhost: false                             # whether the issuer's and the lists' addresses may name localhost
    clock: clock                                       # the PSR-20 clock
    max_age: 3600                                      # seconds the profile and the keys may be cached
    contexts:                                          # JSON-LD context documents the application provides, by URL
        'https://purl.imsglobal.org/spec/ob/v3p0/context-3.0.3.json': '%kernel.project_dir%/config/jsonld/ob-v3p0-context-3.0.3.json'
    trusted_keys:                                      # other issuers' Ed25519 keys the verifier accepts
        'did:web:partner.example#key-1': z6Mk…
    status_lists:
        service: Phil\OpenBadgesBundle\Application\Contract\StatusLists
        size: 131072                                   # bits per list, from 131072
        max_age: 300                                   # seconds a list is valid and may be cached
    verification:
        enabled: true                                  # also needs TwigBundle
        layout: '@PhilOpenBadges/layout.html.twig'
        max_upload: 2097152                            # the largest image or text checked, in bytes
        rate_limit: null                               # a limiter of phil/rate-limit-bundle the checks count against
    remote_issuers:
        enabled: false                                 # read the keys of other issuers from their profiles
        trusted: []                                    # the only issuers read, by identifier; any when empty
        cache: cache.app                               # the cache pool the keys read are kept in
        ttl: 3600                                      # seconds the keys of a profile are kept, 60 to 86400
        failure_ttl: 60                                # seconds a refused profile is not asked again, 1 to 3600
        timeout: 3.0                                   # seconds the issuer may keep silent, 0.1 to 60
        max_duration: 5.0                              # seconds the whole read may take, 0.1 to 120
        max_bytes: 65536                               # the largest profile read, 1024 to 1048576
        user_agent: 'PhilOpenBadges/0.1'               # one line: a name and a way to reach whoever runs the site
```

| Option | Vérifié à la construction du conteneur |
|---|---|
| `issuer`, `key` | `issuer.id`, `issuer.name` et `key.secret` sont réglés; sans eux, la construction s'arrête et nomme ceux qui manquent. |
| `issuer` | L'identifiant, le site Web et l'image sont lus par `phil/url` comme les lit un navigateur : `http` ou `https`, sans nom d'utilisateur ni mot de passe, sans adresse IP, sans port 0, et sans `localhost` à moins que `allow_localhost` soit vrai; le nom n'est pas vide, l'adresse de courriel en est une. |
| `verification.rate_limit` | Un nom de 1 à 64 lettres, chiffres, `_`, `.` et `-`; PhilRateLimitBundle est enregistré; ce bundle déclare le limiteur. |
| `securing: data_integrity` | La clé est une clé EdDSA. |
| `status_lists.size`, `max_age`, `verification.max_upload` | Leurs minimums. |
| `remote_issuers.trusted` | Chacun une adresse HTTPS sans fragment, lue par `phil/url`, ou un `did:web` que lit `DidWeb` de `phil/open-badges`; `localhost` refusé pour une adresse à moins que `allow_localhost` soit vrai, et toujours pour un `did:web`. |
| `remote_issuers.ttl`, `failure_ttl`, `timeout`, `max_duration`, `max_bytes`, `user_agent` | Leurs bornes; le User-Agent tient sur une ligne, sans caractère de contrôle. |

La méthode de vérification de l'émetteur est `<issuer id>#key-1` : le `kid` de ses jetons, la `verificationMethod` de
ses preuves, le Multikey de son profil, la clé de son JSON Web Key Set.

`contexts` associe l'URL d'un contexte au fichier qui contient son document, un objet JSON avec un `@context`, d'au plus
1 Mio. Les fichiers sont lus à la première utilisation de la suite Data Integrity, et un fichier absent ou qui n'est pas
un tel document l'arrête là, avec l'URL et le chemin. Les contextes que donne l'application sont lus à côté de ceux que
`phil/open-badges` embarque, et avant eux; aucune autre URL n'est jamais récupérée.

`status_lists.service` nomme les `StatusLists` de l'application ; quand aucun service de ce nom n'existe, aucune liste
n'est servie et la page ne lit pas les états. Avec une seule implémentation de l'interface chargée par ressource,
Symfony crée un alias de l'interface vers elle, et la valeur par défaut la trouve.

`remote_issuers` est décrit dans [Autres émetteurs](remote-issuers.md).
