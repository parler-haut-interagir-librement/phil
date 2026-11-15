<!-- phil-dev source-sha256=d671569b4327c520f5d69289b917cad5e768bcccaa1ebba6d506bfb07dd5f116 -->
# Vérifier

## Un titre

```php
use Phil\OpenBadges\DataIntegrity\DidKeyMethods;
use Phil\OpenBadges\Verification\CredentialVerifier;

$verifier = new CredentialVerifier(new DidKeyMethods(['https://example.org/issuer#key-1' => $publicKey]));
$verified = $verifier->verify($tokenOrJson, $clock->now());

$verified->credential;   // the credential, without its proof or registered claims
$verified->securedAs;    // 'jwt' or 'data-integrity'
$verified->issuer();     // the issuer's identifier
$verified->status();     // its StatusListEntry, or null
```

`verify()` prend un JSON Web Token compact ou le JSON d'un document avec une preuve Data Integrity :

1. Un jeton : la clé est celle que nomme son `kid`, ou la méthode par défaut du vérificateur quand il n'en nomme
   aucune — jamais la JSON Web Key que transporte le jeton, que n'importe qui peut y mettre. L'algorithme est celui de
   la clé : un jeton dont l'`alg` en est un autre — `none`, `HS256` sur une clé publique, `RS256` pour une clé
   Ed25519 — est refusé avant que sa signature soit lue, et `web-token/jwt-library` vérifie la signature avec cet
   algorithme seulement. Un jeton qui marque un paramètre d'en-tête comme critique (`crit`) ou qui laisse ses
   revendications non encodées (`b64`) est refusé. Avant que la bibliothèque l'analyse, le jeton est borné :
   `JwtVerifier::MAX_LENGTH` octets (1 Mio), trois parties, un en-tête et des revendications imbriqués sur
   `JwtVerifier::MAX_DEPTH` niveaux (64) au plus. Ensuite `nbf` et `exp` sont vérifiés au moment donné : le jeton est
   valide de `nbf` inclus à `exp` exclu.
2. Un document : la preuve est une `DataIntegrityProof` de `eddsa-rdfc-2022` pour `assertionMethod`. Sa méthode de
   vérification est trouvée, sa signature est vérifiée sur les formes canoniques, puis `validFrom` et `validUntil` au
   moment donné.
3. Dans les deux cas, la clé doit être une de celles de l'émetteur du titre : la méthode de vérification est
   l'identifiant de l'émetteur, un fragment de celui-ci (`https://example.org/issuer#key-1` pour l'émetteur
   `https://example.org/issuer`), ou une méthode que le vérificateur liste pour cet émetteur. La revendication `iss`
   d'un jeton doit aussi être l'émetteur.

Sans moment, la période est vérifiée à l'heure actuelle. Un titre qui ne se vérifie pas lance `InvalidCredential`,
avec la raison. Un jeton ou un document de plus de 1 Mio est refusé avant d'être lu.

`JwtVerifier` et `EddsaRdfc2022::verify()` sont publics, pour une application qui sait comment ses titres sont
sécurisés.

## Méthodes de vérification

`DidKeyMethods` connaît les clés que l'application lui donne, par identifiant de méthode de vérification — celles de
son propre émetteur, celles d'un partenaire —, et chaque `did:key` Ed25519, dont la clé est l'identifiant. Il ne
télécharge rien : un titre signé par une clé que l'application ne connaît pas est refusé.

Une clé connue ne signe qu'au nom de son propre émetteur. Une clé est celle de l'émetteur quand sa méthode de
vérification est sous l'identifiant de l'émetteur : la clé d'un partenaire ne peut pas émettre au nom d'un autre
émetteur, et un `did:key` fabriqué ne signe que pour l'émetteur qui est ce `did:key`. Une clé publiée ailleurs, comme
`https://keys.example.org/1` pour l'émetteur `https://example.org/issuer`, est listée pour son émetteur :

```php
$verifier = new CredentialVerifier($methods, controllers: [
    'https://example.org/issuer' => ['https://keys.example.org/1'],
]);
```

Pour vérifier des émetteurs d'une autre sorte, l'application implémente `VerificationMethods` (voyez
[Points d'extension](extension-points.md)). Elle télécharge leurs profils et les garde en cache comme elle l'entend.

## Émetteurs identifiés par `did:web`

Un émetteur identifié par un `did:web` publie ses clés dans son document DID, sur son propre site. `DidWebMethods` le
lit quand un titre nomme l'une de ses méthodes, et transmet toute autre méthode aux méthodes qu'on lui donne — par
défaut les clés de l'application et chaque `did:key` :

```php
use Phil\OpenBadges\Adapter\SafeHttpFetcher;
use Phil\OpenBadges\DataIntegrity\DidKeyMethods;
use Phil\OpenBadges\Did\DidWebMethods;
use Phil\OpenBadges\Verification\CredentialVerifier;

$methods = new DidWebMethods(new SafeHttpFetcher(), new DidKeyMethods($knownKeys));
$verified = new CredentialVerifier($methods)->verify($tokenOrJson);
```

Le document de `did:web:example.org` est à `https://example.org/.well-known/did.json` ; celui de
`did:web:example.org:issuers:42`, à `https://example.org/issuers/42/did.json` ; un port s'écrit `%3A`
(`did:web:example.org%3A8443`). Un hôte qui est une adresse IP, `localhost` ou un nom simple est refusé, tout comme un
segment `.`, `..` ou contenant une barre oblique échappée.

La clé de `did:web:example.org#key-1` est acceptée quand le document :

| Règle | Pourquoi |
|---|---|
| A pour `id` `did:web:example.org` | Un document servi pour un autre identifiant ne dit rien de celui-ci. |
| Liste la méthode dans `verificationMethod`, ou l'intègre dans `assertionMethod`, sous la forme `#key-1` ou en entier | La méthode est celle de l'émetteur. |
| La nomme dans `assertionMethod` | L'émetteur signe des titres avec elle, il ne s'en sert pas seulement pour s'authentifier. |
| Donne `controller` comme `did:web:example.org` | Personne d'autre ne contrôle la clé. |
| Contient la clé sous la forme d'une `publicKeyMultibase`, une Multikey Ed25519, ou d'une `publicKeyJwk` : Ed25519 (`OKP`) ou RSA de 2048 à 8192 bits | Les algorithmes d'Open Badges 3.0 ; un document qui publie une clé privée est refusé. |
| Est un objet JSON d'au plus 256 Kio et 16 niveaux | Un document est lu dans des limites. |

`SafeHttpFetcher` lit le document par `phil/safe-http` : en HTTPS seulement, un hôte d'un réseau privé refusé, chaque
redirection vérifiée et maintenue en HTTPS, avec 5 secondes de silence et 10 au total, 256 Kio au plus, et seulement
quand la réponse est un 200 complet. Il s'identifie comme `PhilOpenBadges/0.1` ; on donne à son constructeur un client
de `phil/safe-http` aux autres limites.

Chaque vérification relit le document : `DidWebMethods` ne garde rien entre deux appels, et il est donc sûr dans un
worker de longue durée. Une application qui vérifie souvent décore son `DocumentFetcher` d'un cache, les documents
gardés quelques minutes, et appelle `reset()` de `SafeHttpFetcher` entre les messages d'un worker. Un document qui ne
peut être lu, ou qui ne donne pas la clé, lance `InvalidCredential` avec la raison : le titre ne se vérifie pas.

## Le destinataire

Un titre vérifié prouve qui l'a émis, pas qui le présente. `Recipient::identifies()` vérifie que le sujet est la
personne devant le vérificateur : son DID, ou l'adresse courriel ou l'URL dont le titre contient l'empreinte salée,
comparée en temps constant.

```php
use Phil\OpenBadges\Recipient;

Recipient::identifies($verified->credential['credentialSubject'], $emailTheyGive);
```

## L'état

Un titre qui a une entrée d'état peut être révoqué. `verify()` ne lit pas l'état, donc un titre révoqué se vérifie
encore : voyez [Révocation](revocation.md) pour lire sa liste d'état.

## Le sous-ensemble de JSON-LD

Le processeur lit le JSON-LD 1.1 qu'utilisent les titres vérifiables : les contextes par URL fournis par le chargeur,
les contextes intégrés, `@vocab`, `@propagate`, les termes avec `@id`, `@type`, `@container` (`@set`, `@list`,
`@graph`), `@context` et `@prefix`, les contextes limités à un type ou à une propriété, les alias de mots-clés et les
IRI compacts, les objets-valeurs avec un type ou une langue. Il refuse plutôt que de lire autrement : `@import`,
`@base`, un `@language` ou un `@direction` par défaut, les propriétés inverses, l'imbrication, les tables d'index et de
langue, les littéraux JSON, les listes de listes. `@protected` est accepté sans être appliqué.

Les contextes viennent d'un `ContextLoader`. `BundledContexts` sert Verifiable Credentials 2.0, Open Badges 3.0.3 et
CLR 2.0.1, ainsi que ceux que l'application ajoute, et refuse toute autre URL.

Chaque document est borné : les nœuds s'imbriquent sur au plus 32 niveaux, les contextes se chargent les uns les
autres sur au plus 8 niveaux, un document produit au plus 10 000 quads. La canonicalisation refuse un jeu de données
qui exige plus de 2 000 empreintes de degré n, ou qui permute plus de 5 nœuds vides indiscernables à la fois.
