<!-- phil-dev source-sha256=b185c2904bf50e173fe7bf8d22090290708d3f64073c4c5964991366ca3e1aed -->
<!-- phil-dev source-sha256=x -->
# Autres émetteurs

Une attestation émise ailleurs est signée avec la clé de son émetteur, que l'application ne configure pas. Avec
`remote_issuers` activé, le vérificateur et la page de vérification lisent cette clé là où l'émetteur la publie : dans
son profil, à son identifiant HTTPS, tel que le décrit Open Badges 3.0; ou dans son document DID, quand son identifiant
est un `did:web`. Les clés Ed25519 et les clés RSA sont lues : un jeton qu'un autre émetteur a signé avec `EdDSA` ou
`RS256`, et un document avec une preuve `eddsa-rdfc-2022`, se vérifient donc tout autant.

```yaml
# config/packages/phil_open_badges.yaml
phil_open_badges:
    remote_issuers:
        enabled: true
        trusted: []                 # any issuer on a public host; or the only identifiers read, HTTPS or did:web
        cache: cache.app            # the pool the keys read are kept in
        ttl: 3600                   # seconds the keys of a profile are kept, 60 to 86400
        failure_ttl: 60             # seconds a profile that could not be read is not asked again, 1 to 3600
        timeout: 3.0                # seconds the issuer may keep silent, 0.1 to 60
        max_duration: 5.0           # seconds the whole read may take, redirects included, 0.1 to 120
        max_bytes: 65536            # the largest profile read, 1024 to 1048576
        user_agent: 'PhilOpenBadges/0.1'
```

## Comment une clé est trouvée

Les clés que l'application connaît passent en premier : celle de son propre émetteur, celles de `trusted_keys` et les
`did:key` Ed25519. Une méthode de vérification qu'aucune ne détient est cherchée dans le document de son identifiant,
sans le fragment :

| Méthode | Émetteur | Document lu |
|---|---|---|
| `https://partner.example/issuer#key-1` | `https://partner.example/issuer` | `https://partner.example/issuer` |
| `did:web:partner.example#key-1` | `did:web:partner.example` | `https://partner.example/.well-known/did.json` |
| `did:web:partner.example:badges:2026#key-1` | `did:web:partner.example:badges:2026` | `https://partner.example/badges/2026/did.json` |
| `did:web:partner.example%3A8443#key-1` | `did:web:partner.example%3A8443` | `https://partner.example:8443/.well-known/did.json` |

`phil/open-badges` a déjà vérifié que la méthode est sous l'identifiant de l'émetteur de l'attestation : un document ne
garantit donc jamais que ses propres attestations.

| Étape | Règle |
|---|---|
| L'adresse | Une adresse HTTPS lue par `phil/url` : sans nom d'utilisateur ni mot de passe, sans adresse IP, sans `localhost` à moins que `allow_localhost` soit vrai. Un `did:web` donne son adresse telle que `DidWeb` de `phil/open-badges` la construit d'après la spécification de la méthode did:web, refusée plutôt que réparée : un nom d'hôte, jamais une adresse IP ni `localhost`, quoi que dise `allow_localhost`; un port facultatif écrit `%3A`; des segments de chemin faits de caractères non réservés ou d'octets codés en pourcentage, aucun n'étant `.` ni `..` ni ne contenant de barre oblique; ni requête ni fragment. Tout autre `did:`, un `urn` ou du HTTP simple n'est pas lu, et reste inconnu. |
| Les émetteurs de confiance | Quand `trusted` liste des identifiants, tout autre émetteur est refusé avant que rien soit lu. |
| La lecture | `phil/safe-http` : un hôte qui est une adresse privée, de bouclage, de lien local ou réservée, ou qui s'y résout, n'est jamais joint, les redirections sont vérifiées une à une, trois au plus, et la dernière adresse lue est encore en HTTPS, et le silence, la durée et les octets sont bornés. `Accept: application/ld+json, application/json`; un autre type de média n'est pas lu. |
| Le document | Un objet JSON, de 32 niveaux de profondeur au plus, dont l'`id` est l'identifiant de l'émetteur : l'adresse lue pour un profil, le `did:web` lui-même pour un document DID. |
| Les clés | Les 20 premières entrées de `verificationMethod`; chacune dont l'`id` est un fragment de l'identifiant, écrit en entier ou comme le seul fragment, `#key-1`, dont le `controller`, quand il est donné, est l'identifiant, et, quand le document liste un `assertionMethod`, qu'il liste; une clé Ed25519 sous forme de `Multikey` avec son `publicKeyMultibase`, ou de `JsonWebKey` avec son `publicKeyJwk` (`OKP`, `Ed25519`); une clé RSA sous forme de `JsonWebKey` avec son `publicKeyJwk` (`RSA`, ses `n` et `e`, aucun membre privé, un `alg` qui, quand il est donné, est `RS256`), lue par `RsaKey::fromJwk()` de `phil/open-badges` : son module de 2048 à 8192 bits, son exposant impair, d'au moins 3 et d'au plus 64 bits. Toute autre entrée est laissée de côté. |

Les clés trouvées sont gardées dans `cache` pendant `ttl` secondes, sous `phil_open_badges.issuer.<sha256 de l'identifiant>`,
sous forme de clés Web JSON (JSON Web Keys) réduites à ce qui sert à vérifier : un vérificateur lit un document de temps
en temps, peu importe combien d'attestations il contrôle. Un document qui ne tient pas est gardé refusé pendant
`failure_ttl` secondes, pour qu'un émetteur qui ne répond pas ne soit pas interrogé de nouveau à chaque vérification.

## Les refus

Une attestation dont la clé est introuvable ne se vérifie pas, avec une raison que montre la page. Les raisons disent
« profile » aussi pour un document DID; les quatre premières nomment l'émetteur par son identifiant, le `did:web`
lui-même, et la cinquième nomme l'adresse lue :

| Raison | Quand |
|---|---|
| `The issuer "…" is not one the verifier trusts.` | `trusted` ne le liste pas. |
| `The profile of the issuer "…" could not be read: …` | L'adresse est refusée, l'hôte ne répond pas à temps, les redirections sont trop nombreuses, ou la réponse n'est pas un `2xx`. |
| `The profile of the issuer "…" was redirected away from HTTPS.` | Une redirection a mené à une adresse HTTP simple : le profil n'est pas lu, quoi qu'il contienne. |
| `The profile of the issuer "…" is not JSON within the bytes the verifier reads.` | Un autre type de média, un corps coupé à `max_bytes`, du JSON qui ne s'analyse pas ou qui n'est pas un objet. |
| `The profile read at "…" is not the profile of that issuer: its "id" differs.` | Le document lu à cette adresse nomme un autre identifiant, ou aucun. |
| `The profile of the issuer "…" publishes no key "…".` | La méthode n'est pas parmi les clés gardées. |

## Charge et abus

Chaque vérification de la page de vérification peut lire un document. Le limiteur de `verification.rate_limit` compte
déjà chaque vérification, les lectures des documents d'autres émetteurs avec elles ([Page de vérification](verification-page.md#limiter-les-vérifications));
un site qui ouvre la page à tous en nomme un. Un service de l'application qui vérifie des attestations par le service
`CredentialVerifier` lit aussi des documents, et borne ses propres appelants.

Le client ne garde rien d'une lecture entre les requêtes : il est réinitialisé avec le noyau, comme le fait un *worker*
entre deux messages. Un module RSA est borné à 8192 bits, pour qu'un document ne puisse pas faire coûter chaque
vérification une lente vérification; une clé plus grande est laissée de côté, comme celle de moins de 2048 bits.
